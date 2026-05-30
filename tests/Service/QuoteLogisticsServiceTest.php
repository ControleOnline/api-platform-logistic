<?php

namespace ControleOnline\Logistic\Tests\Service;

use ControleOnline\Entity\Address;
use ControleOnline\Entity\Cep;
use ControleOnline\Entity\DeviceConfig;
use ControleOnline\Entity\City;
use ControleOnline\Entity\District;
use ControleOnline\Entity\Order;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Status;
use ControleOnline\Entity\State;
use ControleOnline\Entity\Street;
use ControleOnline\Service\Food99Service;
use ControleOnline\Service\iFoodService;
use ControleOnline\Service\IntegrationService;
use ControleOnline\Service\PeopleRoleService;
use ControleOnline\Service\StatusService;
use ControleOnline\Service\QuoteLogisticsService;
use ControleOnline\Service\UberService;
use ControleOnline\Service\Client\WebsocketClient;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class QuoteLogisticsServiceTest extends TestCase
{
    public function testValidateQuoteRouteRejectsEqualAddresses(): void
    {
        $service = (new \ReflectionClass(QuoteLogisticsService::class))->newInstanceWithoutConstructor();

        $pickupAddress = $this->createComparableAddressStub();
        $dropoffAddress = $this->createComparableAddressStub();

        self::assertSame(
            'Endereco de coleta e entrega nao podem ser iguais.',
            $this->invokePrivateMethod($service, 'validateQuoteRoute', $pickupAddress, $dropoffAddress)
        );
    }

    public function testValidateQuoteRouteRejectsIncompleteDropoffAddress(): void
    {
        $service = (new \ReflectionClass(QuoteLogisticsService::class))->newInstanceWithoutConstructor();

        $pickupAddress = $this->createComparableAddressStub();
        $dropoffAddress = $this->createIncompleteAddressStub();

        self::assertSame(
            'Pedido sem endereco de entrega valido.',
            $this->invokePrivateMethod($service, 'validateQuoteRoute', $pickupAddress, $dropoffAddress)
        );
    }

    public function testSelectQuoteAdvancesWithFakeRouteAndMockedIfoodSuccess(): void
    {
        $provider = $this->createStub(People::class);
        $provider->method('getId')->willReturn(3);

        $status = $this->createStub(Status::class);
        $status->method('getId')->willReturn(104);
        $status->method('getStatus')->willReturn('quote');
        $status->method('getRealStatus')->willReturn('pending');
        $status->method('getColor')->willReturn('');

        $rootOtherInformations = [
            'logistics' => [],
        ];
        $rootOrder = $this->createStub(Order::class);
        $rootOrder->method('getId')->willReturn(71103);
        $rootOrder->method('getMainOrderId')->willReturn(null);
        $rootOrder->method('getProvider')->willReturn($provider);
        $rootOrder->method('getApp')->willReturn('POS');
        $rootOrder->method('getStatus')->willReturn(null);
        $rootOrder->method('getOtherInformations')->willReturnCallback(static function (...$args) use (&$rootOtherInformations) {
            return $rootOtherInformations;
        });
        $rootOrder->method('setOtherInformations')->willReturnCallback(static function (mixed $value) use (&$rootOtherInformations): void {
            $rootOtherInformations = is_array($value) ? $value : [];
        });

        $quoteOtherInformations = [
            'logistics' => [
                'quote_state' => 'ready',
                'quote_id' => 'fake-quote',
                'price' => 12.90,
                'eta' => '20 min',
                'tracking_url' => null,
            ],
        ];
        $quoteOrder = $this->createStub(Order::class);
        $quoteOrder->method('getId')->willReturn(71424);
        $quoteOrder->method('getMainOrderId')->willReturn(71103);
        $quoteOrder->method('getProvider')->willReturn($provider);
        $quoteOrder->method('getApp')->willReturn('iFood');
        $quoteOrder->method('getStatus')->willReturn($status);
        $quoteOrder->method('getPrice')->willReturn(12.90);
        $quoteOrder->method('getOtherInformations')->willReturnCallback(static function (...$args) use (&$quoteOtherInformations) {
            return $quoteOtherInformations;
        });
        $quoteOrder->method('setOtherInformations')->willReturnCallback(static function (mixed $value) use (&$quoteOtherInformations): void {
            $quoteOtherInformations = is_array($value) ? $value : [];
        });
        $quoteOrder->method('getAddressOrigin')->willReturn($this->createAddressStub(
            'Alameda Yayá',
            424,
            '07060000',
            'Guarulhos',
            'Jardim Aida',
            'SP',
            -23.4434,
            -46.5123
        ));
        $quoteOrder->method('getAddressDestination')->willReturn($this->createAddressStub(
            'Rua dos Testes',
            123,
            '01001000',
            'Sao Paulo',
            'Centro',
            'SP',
            -23.5505,
            -46.6333
        ));

        $orderRepository = $this->createStub(EntityRepository::class);
        $orderRepository->method('find')->willReturnCallback(static function (mixed $id) use ($rootOrder, $quoteOrder) {
            return (int) $id === 71103 ? $rootOrder : ((int) $id === 71424 ? $quoteOrder : null);
        });

        $deviceRepository = $this->createStub(EntityRepository::class);
        $deviceRepository->method('findBy')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnCallback(static function (string $class) use ($orderRepository, $deviceRepository) {
            return match ($class) {
                Order::class => $orderRepository,
                DeviceConfig::class => $deviceRepository,
                default => $deviceRepository,
            };
        });
        $entityManager->expects(self::exactly(2))->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $peopleRoleService = $this->createStub(PeopleRoleService::class);
        $peopleRoleService->method('canAccessCompany')->willReturn(true);
        $peopleRoleService->method('getCurrentPeople')->willReturn($provider);

        $statusService = $this->createStub(StatusService::class);
        $statusService->method('discoveryStatus')->willReturn($status);

        $integrationService = $this->createStub(IntegrationService::class);
        $food99Service = $this->createStub(Food99Service::class);

        $iFoodService = $this->createMock(iFoodService::class);
        $iFoodService->expects(self::once())
            ->method('requestDeliveryFromQuote')
            ->with($quoteOrder)
            ->willReturn([
                'errno' => 0,
                'errmsg' => 'ok',
                'data' => [
                    'order_id' => 999,
                    'remote_order_id' => 'ifood-remote-1',
                    'tracking_url' => 'https://example.com/tracking',
                ],
            ]);

        $uberService = $this->createStub(UberService::class);
        $websocketClient = $this->createStub(WebsocketClient::class);

        $service = new QuoteLogisticsService(
            $entityManager,
            $peopleRoleService,
            $statusService,
            $integrationService,
            $food99Service,
            $iFoodService,
            $uberService,
            $websocketClient
        );

        $result = $service->selectQuote(71103, 71424);

        self::assertSame(0, $result['errno']);
        self::assertSame(71424, $result['data']['quote_order_id']);
        self::assertSame('ifood', $result['data']['provider_key']);
        self::assertSame(71424, $result['data']['selection']['quoteOrderId']);
    }

    private function invokePrivateMethod(object $object, string $methodName, mixed ...$arguments): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $arguments);
    }

    private function createComparableAddressStub(): Address
    {
        $state = $this->createStub(State::class);
        $state->method('getUf')->willReturn('SP');
        $state->method('getState')->willReturn('Sao Paulo');

        $city = $this->createStub(City::class);
        $city->method('getCity')->willReturn('Guarulhos');
        $city->method('getState')->willReturn($state);

        $district = $this->createStub(District::class);
        $district->method('getDistrict')->willReturn('Jardim Aida');
        $district->method('getCity')->willReturn($city);

        $cep = $this->createStub(Cep::class);
        $cep->method('getCep')->willReturn('07060000');

        $street = $this->createStub(Street::class);
        $street->method('getStreet')->willReturn('Alameda Yayá');
        $street->method('getDistrict')->willReturn($district);
        $street->method('getCep')->willReturn($cep);

        $address = $this->createStub(Address::class);
        $address->method('getStreet')->willReturn($street);
        $address->method('getNumber')->willReturn(424);
        $address->method('getComplement')->willReturn(null);
        $address->method('getLatitude')->willReturn(-23.4434);
        $address->method('getLongitude')->willReturn(-46.5123);

        return $address;
    }

    private function createIncompleteAddressStub(): Address
    {
        $address = $this->createStub(Address::class);
        $address->method('getStreet')->willReturn(null);
        $address->method('getNumber')->willReturn(null);
        $address->method('getComplement')->willReturn(null);
        $address->method('getLatitude')->willReturn(0.0);
        $address->method('getLongitude')->willReturn(0.0);

        return $address;
    }

    private function createAddressStub(
        string $streetName,
        int $number,
        string $cepValue,
        string $cityName,
        string $districtName,
        string $stateUf,
        float $latitude,
        float $longitude
    ): Address {
        $state = $this->createStub(State::class);
        $state->method('getUf')->willReturn($stateUf);
        $state->method('getState')->willReturn($stateUf);

        $city = $this->createStub(City::class);
        $city->method('getCity')->willReturn($cityName);
        $city->method('getState')->willReturn($state);

        $district = $this->createStub(District::class);
        $district->method('getDistrict')->willReturn($districtName);
        $district->method('getCity')->willReturn($city);

        $cep = $this->createStub(Cep::class);
        $cep->method('getCep')->willReturn($cepValue);

        $street = $this->createStub(Street::class);
        $street->method('getStreet')->willReturn($streetName);
        $street->method('getDistrict')->willReturn($district);
        $street->method('getCep')->willReturn($cep);

        $address = $this->createStub(Address::class);
        $address->method('getStreet')->willReturn($street);
        $address->method('getNumber')->willReturn($number);
        $address->method('getComplement')->willReturn(null);
        $address->method('getLatitude')->willReturn($latitude);
        $address->method('getLongitude')->willReturn($longitude);

        return $address;
    }
}
