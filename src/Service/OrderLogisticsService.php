<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Email;
use ControleOnline\Entity\Order;
use ControleOnline\Entity\People;
use ControleOnline\Entity\PeopleLink;
use ControleOnline\Entity\Phone;
use ControleOnline\Entity\Status;
use ControleOnline\Service\Marketplace\MarketplaceOrderSnapshotProviderInterface;
use ControleOnline\Service\Marketplace\MarketplaceProviderRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderLogisticsService
{
    private const MARKETPLACE_KEYS = ['uber', 'ifood', 'food99'];
    private const INTEGRATED_ORDER_APPS = [Order::APP_IFOOD, Order::APP_FOOD99];

    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly PeopleRoleService $peopleRoleService,
        private readonly Food99Service $food99Service,
        private readonly iFoodService $iFoodService,
        private readonly UberService $uberService,
        private readonly ?MarketplaceProviderRegistry $marketplaceProviderRegistry = null,
    ) {
    }

    public function buildPayload(int|string $orderReference): array
    {
        $order = $this->resolveOrder($orderReference);
        $provider = $order->getProvider();
        $currentPeople = $this->peopleRoleService->getCurrentPeople();

        if (
            $provider instanceof People
            && !$this->peopleRoleService->canAccessCompany($provider, $currentPeople)
        ) {
            throw new AccessDeniedHttpException('Acesso negado ao pedido informado.');
        }

        $managedByStore = !$this->isMarketplaceManaged($order);
        $orderOtherInformations = $this->decodeOtherInformations($order);
        $logisticsState = is_array($orderOtherInformations['logistics'] ?? null)
            ? $orderOtherInformations['logistics']
            : [];
        $uberState = $this->resolveUberState($orderOtherInformations);
        $couriers = $managedByStore && $provider instanceof People
            ? $this->resolveCouriers($provider, (int) ($order->getDeliveryPeople()?->getId() ?? 0))
            : [];
        $iFoodState = $this->resolveMarketplaceOrderIntegrationState('ifood', $order);
        $iFoodSnapshot = $this->resolveMarketplaceOrderSnapshot('ifood', $order);
        $food99State = $this->resolveMarketplaceOrderIntegrationState('food99', $order);
        $food99Snapshot = $this->resolveMarketplaceOrderSnapshot('food99', $order);
        $marketplaceCards = $this->buildMarketplaceCards(
            $order,
            $managedByStore,
            $uberState,
            $iFoodState,
            $iFoodSnapshot,
            $food99State,
            $food99Snapshot,
            $logisticsState
        );

        return [
            'order' => $this->normalizeOrderSummary($order),
            'management' => [
                'mode' => $managedByStore ? 'store' : 'integration',
                'managedByStore' => $managedByStore,
                'source' => $this->resolveManagementSource($order),
                'label' => $managedByStore
                    ? 'Gerenciada pela loja'
                    : 'Gerenciada por integracao',
            ],
            'delivery' => [
                'type' => $this->normalizeText($logisticsState['type'] ?? ($order->getDeliveryPeople() ? 'courier' : '')),
                'status' => $this->normalizeText($logisticsState['status'] ?? ''),
                'requestedAt' => $this->normalizeText($logisticsState['requested_at'] ?? ''),
                'trackingUrl' => $this->normalizeText(
                    $logisticsState['tracking_url']
                        ?? $uberState['tracking_url']
                        ?? $uberState['last_webhook_tracking_url']
                        ?? ''
                ),
                'deliveryPeopleId' => $order->getDeliveryPeople()?->getId(),
                'deliveryPeople' => $this->normalizePeople($order->getDeliveryPeople()),
                'currentIntegrationKey' => $this->resolveCurrentIntegrationKey($order, $uberState, $iFoodState, $food99State),
            ],
            'couriers' => $couriers,
            'integrations' => $marketplaceCards,
            'currentIntegration' => $this->resolveCurrentIntegrationCard($marketplaceCards),
        ];
    }

    public function requestDelivery(int|string $orderReference, array $payload): array
    {
        $order = $this->resolveOrder($orderReference);
        $provider = $order->getProvider();
        $currentPeople = $this->peopleRoleService->getCurrentPeople();

        if (
            $provider instanceof People
            && !$this->peopleRoleService->canAccessCompany($provider, $currentPeople)
        ) {
            throw new AccessDeniedHttpException('Acesso negado ao pedido informado.');
        }

        if ($this->isMarketplaceManaged($order)) {
            throw new BadRequestHttpException('Pedido gerenciado por marketplace nao aceita solicitação da loja.');
        }

        $type = $this->normalizeText($payload['type'] ?? $payload['provider'] ?? $payload['integration'] ?? '');

        return match ($type) {
            'courier' => $this->requestCourier($order, $payload),
            'uber' => $this->requestUber($order),
            'ifood', 'food99' => [
                'errno' => 501,
                'errmsg' => 'Solicitacao ainda nao implementada para esta integracao.',
                'data' => [
                    'order_id' => $order->getId(),
                    'provider' => $type,
                ],
            ],
            default => throw new BadRequestHttpException('Tipo de solicitacao invalido.'),
        };
    }

    private function requestCourier(Order $order, array $payload): array
    {
        $provider = $order->getProvider();
        if (!$provider instanceof People) {
            throw new BadRequestHttpException('Pedido sem provider valido.');
        }

        $courierId = (int) ($payload['delivery_people_id'] ?? $payload['deliveryPeopleId'] ?? 0);
        if ($courierId <= 0) {
            throw new BadRequestHttpException('Entregador invalido.');
        }

        $courier = $this->manager->getRepository(People::class)->find($courierId);
        if (!$courier instanceof People) {
            throw new NotFoundHttpException('Entregador nao encontrado.');
        }

        $link = $this->manager->getRepository(PeopleLink::class)->findOneBy([
            'company' => $provider,
            'people' => $courier,
            'linkType' => 'courier',
            'enable' => 1,
        ]);

        if (!$link instanceof PeopleLink) {
            throw new BadRequestHttpException('Entregador nao esta vinculado a loja.');
        }

        $order->setDeliveryPeople($courier);
        $order->setAlterDate(new \DateTime('now'));
        $state = $this->decodeOtherInformations($order);
        $state['logistics'] = [
            'type' => 'courier',
            'status' => 'requested',
            'requested_at' => date('Y-m-d H:i:s'),
            'delivery_people_id' => $courier->getId(),
            'requested_by_people_id' => $this->peopleRoleService->getCurrentPeople()?->getId(),
        ];
        $order->setOtherInformations($state);

        $this->manager->persist($order);
        $this->manager->flush();

        return [
            'errno' => 0,
            'errmsg' => 'ok',
            'data' => [
                'order_id' => $order->getId(),
                'delivery_people_id' => $courier->getId(),
                'courier' => $this->normalizePeople($courier),
            ],
        ];
    }

    private function requestUber(Order $order): array
    {
        $result = $this->uberService->requestDriver($order);
        if ((int) ($result['errno'] ?? 0) !== 0) {
            return $result;
        }

        return $result;
    }

    private function buildMarketplaceCards(
        Order $order,
        bool $managedByStore,
        array $uberState,
        array $iFoodState,
        array $iFoodSnapshot,
        array $food99State,
        array $food99Snapshot,
        array $logisticsState,
    ): array {
        $currentKey = $this->resolveCurrentIntegrationKey($order, $uberState, $iFoodState, $food99State);

        return [
            $this->buildUberCard($order, $managedByStore, $uberState, $logisticsState, $currentKey === 'uber'),
            $this->buildIfoodCard($managedByStore, $iFoodState, $iFoodSnapshot, $currentKey === 'ifood'),
            $this->buildFood99Card($managedByStore, $food99State, $food99Snapshot, $currentKey === 'food99'),
        ];
    }

    private function buildUberCard(
        Order $order,
        bool $managedByStore,
        array $uberState,
        array $logisticsState,
        bool $active,
    ): array {
        $trackingUrl = $this->normalizeText(
            $logisticsState['tracking_url']
                ?? $uberState['tracking_url']
                ?? $uberState['last_webhook_tracking_url']
                ?? ''
        );
        $status = $this->normalizeText(
            $uberState['status']
                ?? $uberState['order_status']
                ?? $uberState['delivery_status']
                ?? $uberState['state']
                ?? ($trackingUrl !== '' ? 'requested' : '')
        );
        $price = $this->extractNumericValue([
            $uberState['estimate_response'] ?? [],
            $uberState['quote_response'] ?? [],
            $uberState['delivery_response'] ?? [],
        ]);
        $eta = $this->normalizeText(
            $logisticsState['eta']
                ?? $uberState['rider_to_store_eta']
                ?? $uberState['expected_arrived_eta']
                ?? ''
        );
        $requestable = $managedByStore && !$active;

        return $this->buildMarketplaceCard(
            'uber',
            'Uber',
            $active,
            true,
            $requestable,
            $price,
            $eta,
            $status,
            $trackingUrl,
            $this->buildMarketplaceSummary($requestable, $status, $price),
            $uberState
        );
    }

    private function buildIfoodCard(
        bool $managedByStore,
        array $state,
        array $snapshot,
        bool $active,
    ): array {
        $price = $this->normalizeNumeric(
            $snapshot['financial']['delivery_fee']
                ?? $snapshot['financial']['store_charged_delivery_price']
                ?? $snapshot['financial']['customer_total']
                ?? null
        );
        $eta = $this->normalizeText(
            $state['delivery_date_time']
                ?? $state['scheduled_start']
                ?? $state['scheduled_end']
                ?? ''
        );
        $status = $this->normalizeText(
            $state['remote_order_state']
                ?? $state['delivery_mode']
                ?? $state['takeout_mode']
                ?? ($active ? 'active' : '')
        );
        $trackingUrl = $this->normalizeText(
            $state['handover_page_url']
                ?? $state['handover_confirmation_url']
                ?? ''
        );

        return $this->buildMarketplaceCard(
            'ifood',
            'iFood',
            $active,
            true,
            false,
            $price,
            $eta,
            $status,
            $trackingUrl,
            $managedByStore && !$active
                ? 'Opção disponivel para integracao futura.'
                : $this->buildMarketplaceSummary(false, $status, $price),
            $state,
            $snapshot
        );
    }

    private function buildFood99Card(
        bool $managedByStore,
        array $state,
        array $snapshot,
        bool $active,
    ): array {
        $price = $this->normalizeNumeric(
            $snapshot['financial']['delivery_fee']
                ?? $snapshot['financial']['store_charged_delivery_price']
                ?? $snapshot['financial']['customer_total']
                ?? null
        );
        $eta = $this->normalizeText(
            $state['expected_arrived_eta']
                ?? $state['rider_to_store_eta']
                ?? ''
        );
        $status = $this->normalizeText(
            $state['remote_order_state']
                ?? $state['remote_delivery_status']
                ?? $state['delivery_type']
                ?? ($active ? 'active' : '')
        );
        $trackingUrl = $this->normalizeText(
            $state['handover_page_url']
                ?? $state['locator']
                ?? ''
        );

        return $this->buildMarketplaceCard(
            'food99',
            '99 Food',
            $active,
            true,
            false,
            $price,
            $eta,
            $status,
            $trackingUrl,
            $managedByStore && !$active
                ? 'Opção disponivel para integracao futura.'
                : $this->buildMarketplaceSummary(false, $status, $price),
            $state,
            $snapshot
        );
    }

    private function buildMarketplaceCard(
        string $key,
        string $label,
        bool $active,
        bool $available,
        bool $requestable,
        ?float $price,
        string $eta,
        string $status,
        string $trackingUrl,
        string $summary,
        array $state = [],
        array $snapshot = [],
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'active' => $active,
            'available' => $available,
            'requestable' => $requestable,
            'price' => $price,
            'eta' => $eta !== '' ? $eta : null,
            'status' => $status !== '' ? $status : null,
            'trackingUrl' => $trackingUrl !== '' ? $trackingUrl : null,
            'summary' => $summary !== '' ? $summary : null,
            'state' => $state,
            'snapshot' => $snapshot,
            'request' => [
                'enabled' => $requestable,
                'type' => $key,
            ],
        ];
    }

    private function buildMarketplaceSummary(bool $requestable, string $status, ?float $price): string
    {
        if ($status !== '') {
            return $status;
        }

        if ($requestable) {
            return 'Disponivel para solicitacao';
        }

        if ($price !== null) {
            return 'Cotacao disponivel';
        }

        return 'Cotacao indisponivel';
    }

    private function resolveCurrentIntegrationCard(array $cards): ?array
    {
        foreach ($cards as $card) {
            if (($card['active'] ?? false) === true) {
                return $card;
            }
        }

        return null;
    }

    private function resolveCouriers(People $provider, int $selectedCourierId = 0): array
    {
        $links = $this->manager->getRepository(PeopleLink::class)->findBy([
            'company' => $provider,
            'linkType' => 'courier',
            'enable' => 1,
        ], ['id' => 'DESC']);

        $couriers = [];
        foreach ($links as $link) {
            if (!$link instanceof PeopleLink) {
                continue;
            }

            $courier = $link->getPeople();
            if (!$courier instanceof People) {
                continue;
            }

            $couriers[] = [
                'id' => (int) $link->getId(),
                'peopleId' => (int) $courier->getId(),
                'companyId' => (int) $provider->getId(),
                'linkType' => $link->getLinkType(),
                'enabled' => (bool) $link->getEnabled(),
                'selected' => $selectedCourierId > 0 && (int) $courier->getId() === $selectedCourierId,
                'label' => $this->normalizeText($courier->getAlias() ?: $courier->getName()),
                'name' => $this->normalizeText($courier->getName()),
                'alias' => $this->normalizeText($courier->getAlias()),
                'peopleType' => $this->normalizeText($courier->getPeopleType()),
                'phone' => $this->normalizePeoplePhone($courier),
                'email' => $this->normalizePeopleEmail($courier),
                'contact' => $this->normalizePeople($courier),
            ];
        }

        usort(
            $couriers,
            static fn(array $left, array $right): int => strcmp($left['label'], $right['label'])
        );

        return $couriers;
    }

    private function normalizeOrderSummary(Order $order): array
    {
        $status = $order->getStatus();
        $client = $order->getClient();
        $provider = $order->getProvider();
        $deliveryPeople = $order->getDeliveryPeople();

        return [
            'id' => $order->getId(),
            'app' => $this->normalizeText($order->getApp()),
            'orderType' => $this->normalizeText($order->getOrderType()),
            'orderDate' => $this->formatDateTime($order->getOrderDate()),
            'alterDate' => $this->formatDateTime($order->getAlterDate()),
            'price' => (float) $order->getPrice(),
            'status' => $status instanceof Status ? [
                'id' => $status->getId(),
                'status' => $this->normalizeText($status->getStatus()),
                'realStatus' => $this->normalizeText($status->getRealStatus()),
                'color' => $this->normalizeText($status->getColor()),
            ] : null,
            'client' => $this->normalizePeople($client),
            'provider' => $this->normalizePeople($provider),
            'deliveryPeople' => $this->normalizePeople($deliveryPeople),
            'deliveryPeopleId' => $deliveryPeople instanceof People ? $deliveryPeople->getId() : null,
            'displayId' => $this->resolveDisplayId($order),
        ];
    }

    private function resolveDisplayId(Order $order): string
    {
        $otherInformations = $this->decodeOtherInformations($order);
        foreach ([
            $otherInformations['displayId'] ?? null,
            $otherInformations['order_index'] ?? null,
            $otherInformations['code'] ?? null,
            $otherInformations['pickup_code'] ?? null,
            $otherInformations['handover_code'] ?? null,
            $order->getId(),
        ] as $candidate) {
            $value = $this->normalizeText($candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function resolveManagementSource(Order $order): string
    {
        return $this->isMarketplaceManaged($order)
            ? $this->normalizeText($order->getApp())
            : 'store';
    }

    private function resolveCurrentIntegrationKey(
        Order $order,
        array $uberState,
        array $iFoodState,
        array $food99State,
    ): ?string {
        if ($this->isMarketplaceManaged($order)) {
            return in_array($this->normalizeText($order->getApp()), self::INTEGRATED_ORDER_APPS, true)
                ? strtolower($this->normalizeText($order->getApp()))
                : null;
        }

        if ($uberState !== []) {
            return 'uber';
        }

        if ($this->normalizeText($iFoodState['remote_order_state'] ?? '') !== '') {
            return 'ifood';
        }

        if ($this->normalizeText($food99State['remote_order_state'] ?? '') !== '') {
            return 'food99';
        }

        if (($uberState['requested_at'] ?? '') !== '') {
            return 'uber';
        }

        return null;
    }

    private function resolveOrder(int|string $orderReference): Order
    {
        $orderId = (int) preg_replace('/\D+/', '', (string) $orderReference);
        if ($orderId <= 0) {
            throw new BadRequestHttpException('Pedido invalido.');
        }

        $order = $this->manager->getRepository(Order::class)->find($orderId);
        if (!$order instanceof Order) {
            throw new NotFoundHttpException('Pedido nao encontrado.');
        }

        return $order;
    }

    private function isMarketplaceManaged(Order $order): bool
    {
        return in_array($this->normalizeText($order->getApp()), self::INTEGRATED_ORDER_APPS, true);
    }

    private function resolveMarketplaceOrderSnapshotProvider(string $providerKey): ?MarketplaceOrderSnapshotProviderInterface
    {
        $normalizedProviderKey = $this->normalizeText($providerKey);

        if ($this->marketplaceProviderRegistry instanceof MarketplaceProviderRegistry) {
            $snapshotProvider = $this->marketplaceProviderRegistry->resolveOrderSnapshotProvider($normalizedProviderKey);
            if ($snapshotProvider instanceof MarketplaceOrderSnapshotProviderInterface) {
                return $snapshotProvider;
            }
        }

        return match ($normalizedProviderKey) {
            'ifood' => $this->iFoodService,
            'food99' => $this->food99Service,
            default => null,
        };
    }

    private function resolveMarketplaceOrderIntegrationState(string $providerKey, Order $order): array
    {
        $snapshotProvider = $this->resolveMarketplaceOrderSnapshotProvider($providerKey);
        if (!$snapshotProvider instanceof MarketplaceOrderSnapshotProviderInterface) {
            return [];
        }

        return $snapshotProvider->getStoredOrderIntegrationState($order);
    }

    private function resolveMarketplaceOrderSnapshot(string $providerKey, Order $order): array
    {
        $snapshotProvider = $this->resolveMarketplaceOrderSnapshotProvider($providerKey);
        if (!$snapshotProvider instanceof MarketplaceOrderSnapshotProviderInterface) {
            return [];
        }

        return $snapshotProvider->getOrderHomologationSnapshot($order);
    }

    private function resolveUberState(array $otherInformations): array
    {
        foreach (['Uber', 'uber'] as $key) {
            if (is_array($otherInformations[$key] ?? null)) {
                return $otherInformations[$key];
            }
        }

        return [];
    }

    private function decodeOtherInformations(Order $order): array
    {
        try {
            $otherInformations = $order->getOtherInformations(true);
            if (is_object($otherInformations)) {
                return json_decode(json_encode($otherInformations, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR) ?: [];
            }

            if (is_array($otherInformations)) {
                return $otherInformations;
            }
        } catch (\Throwable) {
            // Ignora payload corrompido e segue com estado vazio.
        }

        return [];
    }

    private function normalizePeople(?People $people): ?array
    {
        if (!$people instanceof People) {
            return null;
        }

        return [
            'id' => $people->getId(),
            'name' => $this->normalizeText($people->getName()),
            'alias' => $this->normalizeText($people->getAlias()),
            'label' => $this->normalizeText($people->getAlias() ?: $people->getName()),
            'peopleType' => $this->normalizeText($people->getPeopleType()),
            'phone' => $this->normalizePeoplePhone($people),
            'email' => $this->normalizePeopleEmail($people),
        ];
    }

    private function normalizePeoplePhone(People $people): ?array
    {
        $phones = $people->getPhone();
        if (!is_iterable($phones)) {
            return null;
        }

        foreach ($phones as $phone) {
            if (!$phone instanceof Phone) {
                continue;
            }

            $phoneNumber = $this->normalizeText($phone->getPhone());
            if ($phoneNumber === '') {
                continue;
            }

            return [
                'id' => $phone->getId(),
                'ddi' => $phone->getDdi(),
                'ddd' => $phone->getDdd(),
                'phone' => $phoneNumber,
                'display' => $this->formatPhoneDisplay($phone->getDdi(), $phone->getDdd(), $phoneNumber),
            ];
        }

        return null;
    }

    private function normalizePeopleEmail(People $people): ?array
    {
        $emails = $people->getEmail();
        if (!is_iterable($emails)) {
            return null;
        }

        foreach ($emails as $email) {
            if (!$email instanceof Email) {
                continue;
            }

            $emailValue = $this->normalizeText($email->getEmail());
            if ($emailValue === '') {
                continue;
            }

            return [
                'id' => $email->getId(),
                'email' => $emailValue,
            ];
        }

        return null;
    }

    private function extractNumericValue(array $payloads): ?float
    {
        foreach ($payloads as $payload) {
            if (!is_array($payload)) {
                continue;
            }

            $candidate = $this->searchNumericValue($payload, ['delivery_fee', 'store_charged_delivery_price', 'estimate_fee', 'fee', 'price']);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return null;
    }

    private function searchNumericValue(array $data, array $preferredKeys): ?float
    {
        foreach ($preferredKeys as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];
            if (is_numeric($value)) {
                return round((float) $value, 2);
            }
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }

            $nested = $this->searchNumericValue($value, $preferredKeys);
            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }

    private function normalizeNumeric(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    private function normalizeText(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $text = $this->normalizeText($value);
        return $text !== '' ? $text : null;
    }

    private function formatPhoneDisplay(int|string|null $ddi, int|string|null $ddd, string $phone): string
    {
        $ddiValue = $this->normalizeText($ddi);
        $dddValue = str_pad($this->normalizeText($ddd), 2, '0', STR_PAD_LEFT);
        $digits = preg_replace('/\D+/', '', $phone) ?: $phone;

        return trim(sprintf('+%s (%s) %s', $ddiValue, $dddValue, $digits));
    }
}
