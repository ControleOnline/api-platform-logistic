<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Address;
use ControleOnline\Entity\DeviceConfig;
use ControleOnline\Entity\Email;
use ControleOnline\Entity\Order;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Phone;
use ControleOnline\Entity\Status;
use ControleOnline\Service\Client\WebsocketClient;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class QuoteLogisticsService
{
    private const PROVIDER_DEFINITIONS = [
        'ifood' => [
            'label' => 'iFood',
            'app' => Order::APP_IFOOD,
        ],
        'uber' => [
            'label' => 'Uber',
            'app' => 'Uber',
        ],
        'food99' => [
            'label' => '99 Food',
            'app' => Order::APP_FOOD99,
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly PeopleRoleService $peopleRoleService,
        private readonly StatusService $statusService,
        private readonly IntegrationService $integrationService,
        private readonly Food99Service $food99Service,
        private readonly iFoodService $iFoodService,
        private readonly UberService $uberService,
        private readonly WebsocketClient $websocketClient,
    ) {
    }

    public function show(int|string $orderReference): array
    {
        $rootOrder = $this->resolveRootOrder($orderReference);
        $this->assertAccess($rootOrder);
        $provider = $this->resolveOrderProvider($rootOrder);

        $pickupAddress = $this->resolvePickupAddress($rootOrder, false);
        $dropoffAddress = $this->resolveDropoffAddress($rootOrder);
        $providers = $this->resolveDisplayProviders($provider);
        $quotes = $this->loadQuoteOrders($rootOrder);
        $eligibleProviders = $this->resolveEligibleProviders($provider);

        return [
            'order' => $this->normalizeOrderSummary($rootOrder),
            'route' => [
                'pickupAddress' => $this->normalizeAddressSnapshot($pickupAddress),
                'dropoffAddress' => $this->normalizeAddressSnapshot($dropoffAddress),
                'pickupContact' => $this->normalizePeopleSnapshot($rootOrder->getRetrieveContact()),
                'dropoffContact' => $this->normalizePeopleSnapshot($rootOrder->getDeliveryContact()),
            ],
            'management' => [
                'mode' => 'quote',
                'managedByStore' => true,
                'label' => 'Cotacoes da loja',
                'source' => $this->normalizeText($rootOrder->getApp()),
                'mainOrderId' => $rootOrder->getId(),
            ],
            'providers' => $providers,
            'quotes' => $quotes,
            'selection' => $this->normalizeSelection($rootOrder),
            'quoteStatus' => $this->normalizeQuoteStatus($providers, $quotes),
            'canQuote' => count($eligibleProviders) > 0,
        ];
    }

    public function quote(int|string $orderReference): array
    {
        $rootOrder = $this->resolveRootOrder($orderReference);
        $this->assertAccess($rootOrder);
        $provider = $this->resolveOrderProvider($rootOrder);

        $pickupAddress = $this->resolvePickupAddress($rootOrder, true);
        $dropoffAddress = $this->resolveDropoffAddress($rootOrder);
        if (!$pickupAddress instanceof Address) {
            throw new BadRequestHttpException('Pedido sem endereco de coleta valido.');
        }

        if (!$dropoffAddress instanceof Address) {
            throw new BadRequestHttpException('Pedido sem endereco de entrega valido.');
        }

        $providers = $this->resolveEligibleProviders($provider);
        if ($providers === []) {
            throw new BadRequestHttpException('Nenhuma integracao conectada disponivel para cotacao.');
        }

        $batchId = $this->buildBatchId($rootOrder);
        $rootLogistics = $this->extractLogisticsState($rootOrder);
        $rootLogistics['quote_requested_at'] = date('Y-m-d H:i:s');
        $rootLogistics['quote_batch_id'] = $batchId;
        $rootLogistics['pickup_address_id'] = $pickupAddress->getId();
        $rootLogistics['dropoff_address_id'] = $dropoffAddress->getId();
        $rootLogistics['quote_requested_by_people_id'] = $this->peopleRoleService->getCurrentPeople()?->getId();
        $rootLogistics['quote_state'] = 'requested';
        $this->persistLogisticsState($rootOrder, $rootLogistics);

        $preparedQuotes = [];
        foreach ($providers as $provider) {
            $quoteOrder = $this->findQuoteOrder($rootOrder, (string) $provider['key']);
            $isNew = false;

            if (!$quoteOrder instanceof Order) {
                $quoteOrder = new Order();
                $isNew = true;
            }

            if ($this->isLockedQuote($quoteOrder)) {
                continue;
            }

            $this->prepareQuoteOrder(
                $quoteOrder,
                $rootOrder,
                $provider,
                $pickupAddress,
                $dropoffAddress,
                $batchId
            );

            $this->manager->persist($quoteOrder);
            $preparedQuotes[] = $quoteOrder;

            if ($isNew) {
                $this->manager->flush();
                $this->broadcastOrderChange($quoteOrder, 'order.created');
            }
        }

        $this->manager->flush();

        foreach ($preparedQuotes as $quoteOrder) {
            if (!$quoteOrder instanceof Order) {
                continue;
            }

            $this->dispatchQuoteJob($quoteOrder);
            $this->broadcastOrderChange($quoteOrder, 'order.updated');
        }

        $this->broadcastOrderChange($rootOrder, 'order.updated');

        return [
            'errno' => 0,
            'errmsg' => 'ok',
            'data' => [
                'order_id' => $rootOrder->getId(),
                'batch_id' => $batchId,
                'quotes' => $this->loadQuoteOrders($rootOrder),
                'selection' => $this->normalizeSelection($rootOrder),
            ],
        ];
    }

    public function selectQuote(int|string $orderReference, int|string $quoteOrderReference): array
    {
        $rootOrder = $this->resolveRootOrder($orderReference);
        $this->assertAccess($rootOrder);

        $quoteOrder = $this->resolveQuoteOrder($rootOrder, $quoteOrderReference);
        $quoteState = $this->extractLogisticsState($quoteOrder);
        $quoteProviderKey = $this->normalizeProviderKey($quoteOrder->getApp());

        if ($quoteProviderKey === '') {
            throw new BadRequestHttpException('Cotacao sem provider valido.');
        }

        if (!$this->isQuoteReady($quoteOrder, $quoteState)) {
            throw new BadRequestHttpException('Cotacao ainda nao foi concluida.');
        }

        $result = match ($quoteProviderKey) {
            'ifood' => $this->iFoodService->requestDeliveryFromQuote($quoteOrder),
            'uber' => $this->uberService->requestDeliveryFromQuote($quoteOrder),
            'food99' => $this->food99Service->requestDeliveryFromQuote($quoteOrder),
            default => throw new BadRequestHttpException('Provider de cotacao invalido.'),
        };

        if ((int) ($result['errno'] ?? 0) !== 0) {
            throw new BadRequestHttpException(
                $this->normalizeText($result['errmsg'] ?? 'Nao foi possivel transformar a cotacao em entrega.')
            );
        }

        $requestedAt = date('Y-m-d H:i:s');
        $selectedTrackingUrl = $this->normalizeText(
            $result['data']['tracking_url']
                ?? $result['tracking_url']
                ?? $quoteState['tracking_url']
                ?? ''
        );

        $quoteState['quote_state'] = 'selected';
        $quoteState['selected_at'] = $requestedAt;
        $quoteState['requested_at'] = $requestedAt;
        $quoteState['tracking_url'] = $selectedTrackingUrl !== '' ? $selectedTrackingUrl : ($quoteState['tracking_url'] ?? null);
        $quoteState['remote_order_id'] = $this->normalizeText(
            $result['data']['order_id']
                ?? $result['data']['remote_order_id']
                ?? $quoteState['remote_order_id']
                ?? ''
        );
        $quoteState['delivery_response'] = $result['data'] ?? $result;
        $quoteOrder->setStatus($this->statusService->discoveryStatus('open', 'requested', 'order'));
        $quoteOrder->setAlterDate(new DateTime('now'));
        $quoteOrder->setOtherInformations($this->replaceLogisticsState($quoteOrder, $quoteState));
        $this->manager->persist($quoteOrder);

        $rootState = $this->extractLogisticsState($rootOrder);
        $rootState['quote_state'] = 'selected';
        $rootState['selected_quote_order_id'] = $quoteOrder->getId();
        $rootState['selected_provider_key'] = $quoteProviderKey;
        $rootState['selected_at'] = $requestedAt;
        $rootState['selected_tracking_url'] = $selectedTrackingUrl !== '' ? $selectedTrackingUrl : null;
        $rootState['selected_price'] = $this->normalizeQuotePrice($quoteOrder);
        $rootOrder->setAlterDate(new DateTime('now'));
        $rootOrder->setOtherInformations($this->replaceLogisticsState($rootOrder, $rootState));
        $this->manager->persist($rootOrder);
        $this->manager->flush();

        $this->broadcastOrderChange($quoteOrder, 'order.updated');
        $this->broadcastOrderChange($rootOrder, 'order.updated');

        return [
            'errno' => 0,
            'errmsg' => 'ok',
            'data' => [
                'order_id' => $rootOrder->getId(),
                'quote_order_id' => $quoteOrder->getId(),
                'provider_key' => $quoteProviderKey,
                'tracking_url' => $selectedTrackingUrl !== '' ? $selectedTrackingUrl : null,
                'price' => $this->normalizeQuotePrice($quoteOrder),
                'selection' => $this->normalizeSelection($rootOrder),
            ],
        ];
    }

    public function requestDelivery(int|string $orderReference, array $payload): array
    {
        $quoteOrderId = $payload['quote_order_id'] ?? $payload['quoteOrderId'] ?? null;
        if ($quoteOrderId !== null && $quoteOrderId !== '') {
            return $this->selectQuote($orderReference, $quoteOrderId);
        }

        return $this->quote($orderReference);
    }

    public function broadcastOrderChange(Order $order, string $event, array $extra = []): void
    {
        $provider = $order->getProvider();
        if (!$provider instanceof People) {
            return;
        }

        $deviceConfigs = $this->manager->getRepository(DeviceConfig::class)->findBy([
            'people' => $provider,
        ]);

        $logistics = $this->extractLogisticsState($order);
        $payload = [[
            'store' => 'orders',
            'event' => $event,
            'company' => $provider->getId(),
            'order' => $order->getId(),
            'mainOrderId' => $order->getMainOrderId(),
            'orderType' => $this->normalizeText($order->getOrderType()),
            'app' => $this->normalizeText($order->getApp()),
            'status' => $this->normalizeText($order->getStatus()?->getStatus()),
            'realStatus' => $this->normalizeText($order->getStatus()?->getRealStatus()),
            'price' => $this->normalizeQuotePrice($order),
            'quoteState' => $this->normalizeText($logistics['quote_state'] ?? null),
            'providerKey' => $this->normalizeText($logistics['provider_key'] ?? $this->normalizeProviderKey($order->getApp())),
            'sentAt' => date(DATE_ATOM),
        ]];

        if ($extra !== []) {
            $payload[0] = array_merge($payload[0], $extra);
        }

        $serialized = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($serialized === false) {
            return;
        }

        $sentDevices = [];
        foreach ($deviceConfigs as $deviceConfig) {
            if (!$deviceConfig instanceof DeviceConfig) {
                continue;
            }

            $device = $deviceConfig->getDevice();
            $deviceId = $device?->getId();
            if (!$deviceId || isset($sentDevices[$deviceId])) {
                continue;
            }

            $sentDevices[$deviceId] = true;
            $this->websocketClient->push($device, $serialized);
        }
    }

    private function resolveRootOrder(int|string $orderReference): Order
    {
        $orderId = $this->normalizeEntityId($orderReference);
        if ($orderId === null) {
            throw new NotFoundHttpException('Pedido nao encontrado.');
        }

        $order = $this->manager->getRepository(Order::class)->find($orderId);
        if (!$order instanceof Order) {
            throw new NotFoundHttpException('Pedido nao encontrado.');
        }

        $visited = [];
        $resolved = $order;
        while ($resolved->getMainOrderId()) {
            $resolvedId = $resolved->getId();
            if ($resolvedId && isset($visited[$resolvedId])) {
                break;
            }

            if ($resolvedId) {
                $visited[$resolvedId] = true;
            }

            $mainOrder = $resolved->getMainOrder();
            if (!$mainOrder instanceof Order) {
                $mainOrder = $this->manager->getRepository(Order::class)->find((int) $resolved->getMainOrderId());
            }

            if (!$mainOrder instanceof Order) {
                break;
            }

            $resolved = $mainOrder;
        }

        return $resolved;
    }

    private function resolveQuoteOrder(Order $rootOrder, int|string $quoteOrderReference): Order
    {
        $quoteOrderId = $this->normalizeEntityId($quoteOrderReference);
        if ($quoteOrderId === null) {
            throw new NotFoundHttpException('Cotacao nao encontrada.');
        }

        $quoteOrder = $this->manager->getRepository(Order::class)->find($quoteOrderId);
        if (!$quoteOrder instanceof Order || (int) $quoteOrder->getMainOrderId() !== (int) $rootOrder->getId()) {
            throw new NotFoundHttpException('Cotacao nao encontrada.');
        }

        return $quoteOrder;
    }

    private function assertAccess(Order $order): void
    {
        $provider = $order->getProvider();
        $currentPeople = $this->peopleRoleService->getCurrentPeople();

        if (
            $provider instanceof People
            && !$this->peopleRoleService->canAccessCompany($provider, $currentPeople)
        ) {
            throw new AccessDeniedHttpException('Acesso negado ao pedido informado.');
        }
    }

    private function resolvePickupAddress(Order $order, bool $persistIfDiscovered): ?Address
    {
        $pickupAddress = $order->getAddressOrigin();
        if ($pickupAddress instanceof Address) {
            return $pickupAddress;
        }

        $provider = $order->getProvider();
        if (!$provider instanceof People) {
            return null;
        }

        $pickupAddress = $this->resolvePeoplePrimaryAddress($provider);
        if ($pickupAddress instanceof Address && $persistIfDiscovered) {
            $order->setAddressOrigin($pickupAddress);
            $order->setAlterDate(new DateTime('now'));
            $this->manager->persist($order);
            $this->manager->flush();
        }

        return $pickupAddress;
    }

    private function resolveDropoffAddress(Order $order): ?Address
    {
        $dropoffAddress = $order->getAddressDestination();

        return $dropoffAddress instanceof Address ? $dropoffAddress : null;
    }

    private function resolvePeoplePrimaryAddress(?People $people): ?Address
    {
        if (!$people instanceof People) {
            return null;
        }

        foreach ($people->getAddress() as $address) {
            if ($address instanceof Address) {
                return $address;
            }
        }

        return null;
    }

    private function resolveOrderProvider(Order $order): People
    {
        $provider = $order->getProvider();
        if (!$provider instanceof People) {
            throw new BadRequestHttpException('Pedido sem empresa vinculada para cotacao.');
        }

        return $provider;
    }

    private function resolveDisplayProviders(People $provider): array
    {
        $states = $this->resolveProviderStates($provider);
        $providers = [];

        foreach ($states as $providerKey => $providerState) {
            if (($providerState['connected'] ?? false) !== true) {
                continue;
            }

            $providers[] = [
                'key' => $providerKey,
                'label' => self::PROVIDER_DEFINITIONS[$providerKey]['label'],
                'connected' => true,
                'online' => (bool) ($providerState['online'] ?? false),
            ];
        }

        return $providers;
    }

    private function resolveEligibleProviders(People $provider): array
    {
        $states = $this->resolveProviderStates($provider);
        $eligible = [];

        foreach ($states as $providerKey => $providerState) {
            if (($providerState['connected'] ?? false) !== true) {
                continue;
            }

            $eligible[] = [
                'key' => $providerKey,
                'label' => self::PROVIDER_DEFINITIONS[$providerKey]['label'],
                'connected' => true,
                'online' => (bool) ($providerState['online'] ?? false),
            ];
        }

        return $eligible;
    }

    private function resolveProviderStates(People $provider): array
    {
        return [
            'ifood' => $this->normalizeProviderState(
                'ifood',
                self::PROVIDER_DEFINITIONS['ifood']['label'],
                $this->iFoodService->getStoredIntegrationState($provider)
            ),
            'uber' => $this->normalizeProviderState(
                'uber',
                self::PROVIDER_DEFINITIONS['uber']['label'],
                $this->uberService->getStoredIntegrationState($provider)
            ),
            'food99' => $this->normalizeProviderState(
                'food99',
                self::PROVIDER_DEFINITIONS['food99']['label'],
                $this->food99Service->getStoredIntegrationState($provider)
            ),
        ];
    }

    private function normalizeProviderState(string $providerKey, string $label, array $state): array
    {
        return [
            'key' => $providerKey,
            'label' => $label,
            'connected' => (bool) ($state['connected'] ?? false),
            'online' => (bool) ($state['online'] ?? false),
            'state' => $state,
        ];
    }

    private function findQuoteOrder(Order $rootOrder, string $providerKey): ?Order
    {
        $quoteOrders = $this->manager->getRepository(Order::class)->findBy([
            'mainOrderId' => $rootOrder->getId(),
            'orderType' => Order::ORDER_TYPE_DELIVERY,
            'app' => self::PROVIDER_DEFINITIONS[$providerKey]['app'] ?? $providerKey,
        ], [
            'alterDate' => 'DESC',
            'id' => 'DESC',
        ]);

        foreach ($quoteOrders as $quoteOrder) {
            if ($quoteOrder instanceof Order) {
                return $quoteOrder;
            }
        }

        return null;
    }

    private function prepareQuoteOrder(
        Order $quoteOrder,
        Order $rootOrder,
        array $provider,
        Address $pickupAddress,
        Address $dropoffAddress,
        string $batchId
    ): void {
        $providerKey = (string) $provider['key'];
        $providerLabel = (string) $provider['label'];
        $logistics = $this->extractLogisticsState($quoteOrder);
        $providerApp = self::PROVIDER_DEFINITIONS[$providerKey]['app'];

        $quoteOrder->setMainOrder($rootOrder);
        $quoteOrder->setMainOrderId($rootOrder->getId());
        $quoteOrder->setProvider($rootOrder->getProvider());
        $quoteOrder->setClient($rootOrder->getClient());
        $quoteOrder->setPayer($rootOrder->getPayer());
        $quoteOrder->setAddressOrigin($pickupAddress);
        $quoteOrder->setAddressDestination($dropoffAddress);
        $quoteOrder->setRetrieveContact($rootOrder->getRetrieveContact());
        $quoteOrder->setDeliveryContact($rootOrder->getDeliveryContact());
        $quoteOrder->setOrderType(Order::ORDER_TYPE_DELIVERY);
        $quoteOrder->setApp($providerApp);
        $quoteOrder->setPrice(0);

        $quoteOrder->setStatus($this->statusService->discoveryStatus('pending', 'quote', 'order'));

        $logistics['flow'] = 'quote';
        $logistics['batch_id'] = $batchId;
        $logistics['main_order_id'] = $rootOrder->getId();
        $logistics['provider_key'] = $providerKey;
        $logistics['provider_label'] = $providerLabel;
        $logistics['quote_state'] = 'pending';
        $logistics['quote_requested_at'] = date('Y-m-d H:i:s');
        $logistics['quote_updated_at'] = date('Y-m-d H:i:s');
        $logistics['price'] = null;
        $logistics['eta'] = null;
        $logistics['tracking_url'] = null;
        $logistics['quote_response'] = null;
        $logistics['delivery_response'] = null;
        $logistics['provider_state'] = $provider['state'] ?? [];

        $quoteOrder->setAlterDate(new DateTime('now'));
        $quoteOrder->setOtherInformations($this->replaceLogisticsState($quoteOrder, $logistics));
    }

    private function loadQuoteOrders(Order $rootOrder): array
    {
        $rootProvider = $rootOrder->getProvider();
        $providerStates = $rootProvider instanceof People
            ? $this->resolveProviderStates($rootProvider)
            : [];

        $quoteOrders = $this->manager->getRepository(Order::class)->findBy([
            'mainOrderId' => $rootOrder->getId(),
            'orderType' => Order::ORDER_TYPE_DELIVERY,
        ], [
            'alterDate' => 'DESC',
            'id' => 'DESC',
        ]);

        $quotes = [];
        $seenProviders = [];
        foreach ($quoteOrders as $quoteOrder) {
            if (!$quoteOrder instanceof Order) {
                continue;
            }

            $providerKey = $this->normalizeProviderKey($quoteOrder->getApp());
            if ($providerKey === '' || isset($seenProviders[$providerKey])) {
                continue;
            }

            $seenProviders[$providerKey] = true;
            $quotes[] = $this->normalizeQuoteSummary(
                $quoteOrder,
                $providerStates[$providerKey] ?? null
            );
        }

        return $this->sortQuoteSummaries($quotes);
    }

    private function normalizeQuoteSummary(Order $quoteOrder, ?array $providerState = null): array
    {
        $logistics = $this->extractLogisticsState($quoteOrder);
        $providerKey = $this->normalizeProviderKey($quoteOrder->getApp());
        $providerDefinition = self::PROVIDER_DEFINITIONS[$providerKey];

        $quoteState = $this->normalizeText($logistics['quote_state'] ?? 'pending');
        $price = $this->normalizeQuotePrice($quoteOrder, $logistics);
        $trackingUrl = $this->normalizeText($logistics['tracking_url'] ?? '');
        $eta = $this->normalizeText($logistics['eta'] ?? '');
        $status = $quoteOrder->getStatus();
        $stateConnected = $providerState['connected'] ?? false;
        $stateOnline = $providerState['online'] ?? false;

        return [
            'id' => $quoteOrder->getId(),
            'mainOrderId' => $quoteOrder->getMainOrderId(),
            'orderType' => $quoteOrder->getOrderType(),
            'app' => $quoteOrder->getApp(),
            'providerKey' => $providerKey,
            'providerLabel' => $providerDefinition['label'],
            'price' => $price,
            'eta' => $eta !== '' ? $eta : null,
            'status' => $this->normalizeStatusSnapshot($status),
            'quoteState' => $quoteState !== '' ? $quoteState : 'pending',
            'quoteStateLabel' => $this->resolveQuoteStateLabel($quoteState, $price),
            'quoteMessage' => $this->normalizeText($logistics['quote_message'] ?? ''),
            'trackingUrl' => $trackingUrl !== '' ? $trackingUrl : null,
            'selected' => in_array($quoteState, ['selected', 'requested'], true),
            'available' => !in_array($quoteState, ['unavailable', 'error'], true),
            'connected' => (bool) ($stateConnected ?? false),
            'online' => (bool) ($stateOnline ?? false),
            'requestable' => in_array($quoteState, ['ready', 'selected', 'requested'], true),
            'summary' => $this->resolveQuoteSummary($quoteState, $price, $eta, $trackingUrl),
            'otherInformations' => [
                'logistics' => $logistics,
            ],
            'providerState' => $providerState['state'] ?? [],
        ];
    }

    private function normalizeOrderSummary(Order $order): array
    {
        return [
            'id' => $order->getId(),
            'orderType' => $order->getOrderType(),
            'app' => $order->getApp(),
            'mainOrderId' => $order->getMainOrderId(),
            'price' => (float) $order->getPrice(),
            'status' => $this->normalizeStatusSnapshot($order->getStatus()),
            'provider' => $this->normalizePeopleSnapshot($order->getProvider()),
            'client' => $this->normalizePeopleSnapshot($order->getClient()),
            'payer' => $this->normalizePeopleSnapshot($order->getPayer()),
            'addressOrigin' => $this->normalizeAddressSnapshot($order->getAddressOrigin()),
            'addressDestination' => $this->normalizeAddressSnapshot($order->getAddressDestination()),
            'retrieveContact' => $this->normalizePeopleSnapshot($order->getRetrieveContact()),
            'deliveryContact' => $this->normalizePeopleSnapshot($order->getDeliveryContact()),
            'comments' => $this->normalizeText($order->getComments()),
            'otherInformations' => [
                'logistics' => $this->extractLogisticsState($order),
            ],
        ];
    }

    private function normalizeSelection(Order $order): array
    {
        $logistics = $this->extractLogisticsState($order);

        return [
            'quoteOrderId' => $this->normalizeEntityId($logistics['selected_quote_order_id'] ?? null),
            'providerKey' => $this->normalizeText($logistics['selected_provider_key'] ?? ''),
            'price' => isset($logistics['selected_price']) ? (float) $logistics['selected_price'] : null,
            'trackingUrl' => $this->normalizeText($logistics['selected_tracking_url'] ?? ''),
            'selectedAt' => $this->normalizeText($logistics['selected_at'] ?? ''),
        ];
    }

    private function normalizeQuoteStatus(array $providers, array $quotes): array
    {
        $counts = [
            'providers' => count($providers),
            'quotes' => count($quotes),
            'ready' => 0,
            'pending' => 0,
            'selected' => 0,
            'unavailable' => 0,
        ];

        foreach ($quotes as $quote) {
            $state = $this->normalizeText($quote['quoteState'] ?? 'pending');
            if (isset($counts[$state])) {
                $counts[$state]++;
            } else {
                $counts['pending']++;
            }
        }

        return $counts;
    }

    private function normalizeQuotePrice(Order $quoteOrder, ?array $logistics = null): ?float
    {
        $logistics ??= $this->extractLogisticsState($quoteOrder);
        $quoteState = $this->normalizeText($logistics['quote_state'] ?? '');
        if (!in_array($quoteState, ['ready', 'selected', 'requested'], true)) {
            return null;
        }

        return round((float) $quoteOrder->getPrice(), 2);
    }

    private function resolveQuoteStateLabel(string $quoteState, ?float $price): string
    {
        return match ($quoteState) {
            'ready' => $price !== null ? 'Cotacao pronta' : 'Cotacao concluida',
            'selected', 'requested' => 'Entrega solicitada',
            'unavailable' => 'Cotacao indisponivel',
            'error' => 'Erro na cotacao',
            default => 'Aguardando cotacao',
        };
    }

    private function resolveQuoteSummary(string $quoteState, ?float $price, string $eta, string $trackingUrl): string
    {
        if (in_array($quoteState, ['selected', 'requested'], true)) {
            return 'Entrega solicitada';
        }

        if ($quoteState === 'ready' && $price !== null) {
            return 'Cotacao pronta';
        }

        if ($quoteState === 'unavailable') {
            return 'Cotacao indisponivel';
        }

        if ($eta !== '') {
            return $eta;
        }

        if ($trackingUrl !== '') {
            return 'Rastreio disponivel';
        }

        return 'Aguardando cotacao';
    }

    private function normalizeStatusSnapshot(?Status $status): ?array
    {
        if (!$status instanceof Status) {
            return null;
        }

        return [
            'id' => $status->getId(),
            'status' => $status->getStatus(),
            'realStatus' => $status->getRealStatus(),
            'color' => $status->getColor(),
        ];
    }

    private function normalizePeopleSnapshot(?People $people): ?array
    {
        if (!$people instanceof People) {
            return null;
        }

        return [
            'id' => $people->getId(),
            'name' => $this->normalizeText($people->getName()),
            'alias' => $this->normalizeText($people->getAlias()),
            'peopleType' => $this->normalizeText($people->getPeopleType()),
            'phone' => $this->normalizePhoneEntries($people),
            'email' => $this->normalizeEmailEntries($people),
        ];
    }

    private function normalizePhoneEntries(People $people): array
    {
        $phones = [];
        foreach ($people->getPhone() as $phone) {
            if (!$phone instanceof Phone) {
                continue;
            }

            $phones[] = [
                'ddi' => $this->normalizeText($phone->getDdi()),
                'ddd' => $this->normalizeText($phone->getDdd()),
                'phone' => $this->normalizeText($phone->getPhone()),
            ];
        }

        return $phones;
    }

    private function normalizeEmailEntries(People $people): array
    {
        $emails = [];
        foreach ($people->getEmail() as $email) {
            if (!$email instanceof Email) {
                continue;
            }

            $emails[] = [
                'email' => $this->normalizeText($email->getEmail()),
            ];
        }

        return $emails;
    }

    private function normalizeAddressSnapshot(?Address $address): ?array
    {
        if (!$address instanceof Address) {
            return null;
        }

        $street = $address->getStreet();
        $district = $street?->getDistrict();
        $city = $district?->getCity();
        $state = $city?->getState();
        $cep = $street?->getCep();

        return [
            'id' => $address->getId(),
            'number' => $address->getNumber(),
            'nickname' => $this->normalizeText($address->getNickname()),
            'complement' => $this->normalizeText($address->getComplement()),
            'latitude' => $address->getLatitude(),
            'longitude' => $address->getLongitude(),
            'locator' => $this->normalizeText($address->getLocator()),
            'street' => [
                'street' => $this->normalizeText($street?->getStreet()),
                'district' => [
                    'district' => $this->normalizeText($district?->getDistrict()),
                    'city' => [
                        'city' => $this->normalizeText($city?->getCity()),
                        'state' => [
                            'uf' => $this->normalizeText($state?->getUf()),
                            'state' => $this->normalizeText($state?->getState()),
                        ],
                    ],
                ],
                'cep' => [
                    'cep' => $this->normalizeText($cep?->getCep()),
                ],
            ],
        ];
    }

    private function extractLogisticsState(Order $order): array
    {
        $otherInformations = $order->getOtherInformations(true);
        if (is_object($otherInformations)) {
            $otherInformations = (array) $otherInformations;
        }

        if (!is_array($otherInformations)) {
            return [];
        }

        $logistics = $otherInformations['logistics'] ?? [];

        return is_array($logistics) ? $logistics : [];
    }

    private function replaceLogisticsState(Order $order, array $logistics): array
    {
        $otherInformations = $order->getOtherInformations(true);
        if (is_object($otherInformations)) {
            $otherInformations = (array) $otherInformations;
        }

        if (!is_array($otherInformations)) {
            $otherInformations = [];
        }

        $otherInformations['logistics'] = $logistics;

        return $otherInformations;
    }

    private function persistLogisticsState(Order $order, array $logistics): void
    {
        $order->setOtherInformations($this->replaceLogisticsState($order, $logistics));
        $order->setAlterDate(new DateTime('now'));
        $this->manager->persist($order);
        $this->manager->flush();
    }

    private function dispatchQuoteJob(Order $quoteOrder): void
    {
        $provider = $quoteOrder->getProvider();
        $payload = [
            'quote_order_id' => $quoteOrder->getId(),
            'main_order_id' => $quoteOrder->getMainOrderId(),
            'provider_key' => $this->normalizeProviderKey($quoteOrder->getApp()),
            'batch_id' => $this->normalizeText($this->extractLogisticsState($quoteOrder)['batch_id'] ?? ''),
        ];

        $this->integrationService->addIntegration(
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            'LogisticsQuote',
            null,
            null,
            $provider instanceof People ? $provider : null
        );
    }

    private function normalizeProviderKey(?string $value): string
    {
        $normalized = $this->normalizeText($value);
        if ($normalized === '99food') {
            return 'food99';
        }

        return strtolower($normalized);
    }

    private function normalizeEntityId(mixed $value): ?int
    {
        if (is_object($value) && method_exists($value, 'getId')) {
            $value = $value->getId();
        }

        $normalized = preg_replace('/\D+/', '', (string) $value);
        if ($normalized === '') {
            return null;
        }

        return (int) $normalized;
    }

    private function normalizeText(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function buildBatchId(Order $rootOrder): string
    {
        return sprintf('logistics:%d:%s', (int) $rootOrder->getId(), date('YmdHis'));
    }

    private function isLockedQuote(Order $quoteOrder): bool
    {
        $logistics = $this->extractLogisticsState($quoteOrder);
        $quoteState = $this->normalizeText($logistics['quote_state'] ?? '');

        return in_array($quoteState, ['selected', 'requested'], true)
            || $this->normalizeText($logistics['remote_order_id'] ?? '') !== '';
    }

    private function isQuoteReady(Order $quoteOrder, ?array $quoteState = null): bool
    {
        $quoteState ??= $this->extractLogisticsState($quoteOrder);
        $state = $this->normalizeText($quoteState['quote_state'] ?? '');

        return in_array($state, ['ready', 'selected', 'requested'], true)
            || $this->normalizeQuotePrice($quoteOrder, $quoteState) !== null;
    }

    /**
     * @param array<int, array<string, mixed>> $quotes
     * @return array<int, array<string, mixed>>
     */
    private function sortQuoteSummaries(array $quotes): array
    {
        $priority = array_flip(array_keys(self::PROVIDER_DEFINITIONS));

        usort($quotes, function (array $left, array $right) use ($priority): int {
            $leftHasPrice = isset($left['price']) && $left['price'] !== null && is_numeric($left['price']);
            $rightHasPrice = isset($right['price']) && $right['price'] !== null && is_numeric($right['price']);

            if ($leftHasPrice !== $rightHasPrice) {
                return $leftHasPrice ? -1 : 1;
            }

            if ($leftHasPrice && $rightHasPrice) {
                $leftPrice = round((float) $left['price'], 2);
                $rightPrice = round((float) $right['price'], 2);
                if ($leftPrice !== $rightPrice) {
                    return $leftPrice <=> $rightPrice;
                }
            }

            $leftKey = $left['providerKey'] ?? '';
            $rightKey = $right['providerKey'] ?? '';
            $leftPriority = $priority[$leftKey] ?? 999;
            $rightPriority = $priority[$rightKey] ?? 999;

            if ($leftPriority !== $rightPriority) {
                return $leftPriority <=> $rightPriority;
            }

            return (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
        });

        return $quotes;
    }
}
