<?php

/*
 * Contract imported from MODOS_OPERACAO.md
 * - Vehicle setup is a dedicated courier table.
 * - The courier vehicle payload must include brand, model, year, and plate before the save succeeds.
 * - A standalone authenticated person can register the first vehicle.
 * - Legal-entity accounts cannot create or overwrite courier vehicles.
 */

namespace ControleOnline\Logistic\Tests\Service;

use ControleOnline\Entity\DeliveryCourierVehicle;
use ControleOnline\Entity\People;
use ControleOnline\Service\DeliveryCourierVehicleService;
use ControleOnline\Service\PeopleRoleService;
use ControleOnline\Service\PeopleService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[AllowMockObjectsWithoutExpectations]
class DeliveryCourierVehicleServiceTest extends TestCase
{
    public function testSaveFromPayloadAllowsStandaloneUserToRegisterVehicle(): void
    {
        $currentPeople = $this->createMock(People::class);
        $currentPeople->method('getId')->willReturn(7);
        $currentPeople->method('getPeopleType')->willReturn('F');
        $currentPeople->method('getName')->willReturn('Motoboy Teste');
        $currentPeople->method('getAlias')->willReturn('Motoboy Teste');
        $currentPeople->method('getEnabled')->willReturn(true);

        $query = $this->createMock(Query::class);
        $query->expects(self::once())
            ->method('getOneOrNullResult')
            ->willReturn(null);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['select', 'from', 'andWhere', 'setParameter', 'setMaxResults', 'getQuery'])
            ->getMock();
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $peopleService = $this->createMock(PeopleService::class);
        $peopleService->expects(self::once())->method('getMyPeople')->willReturn($currentPeople);

        $peopleRoleService = $this->createStub(PeopleRoleService::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $entityManager->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (mixed $entity) use ($currentPeople): bool {
                return $entity instanceof DeliveryCourierVehicle
                    && $entity->getCourier() === $currentPeople
                    && $entity->getVehicleType() === DeliveryCourierVehicle::VEHICLE_TYPE_BIKE;
            }));
        $entityManager->expects(self::once())->method('flush');

        $service = new DeliveryCourierVehicleService(
            $entityManager,
            $this->createStub(TokenStorageInterface::class),
            $peopleService,
            $peopleRoleService,
            new RequestStack()
        );

        $vehicle = $service->saveFromPayload([
            'vehicleType' => 'bike',
            'brand' => 'Honda',
            'model' => 'CG 160',
            'plate' => 'abc1d23',
            'year' => '2024',
            'color' => 'Preta',
        ]);

        self::assertSame($currentPeople, $vehicle->getCourier());
        self::assertSame(DeliveryCourierVehicle::VEHICLE_TYPE_BIKE, $vehicle->getVehicleType());
        self::assertSame('Honda', $vehicle->getBrand());
        self::assertSame('CG 160', $vehicle->getModel());
        self::assertSame('ABC1D23', $vehicle->getPlate());
        self::assertSame(2024, $vehicle->getYear());
        self::assertSame('Preta', $vehicle->getColor());
    }

    public function testSaveFromPayloadRejectsIncompleteVehicleData(): void
    {
        $currentPeople = $this->createMock(People::class);
        $currentPeople->method('getId')->willReturn(7);
        $currentPeople->method('getPeopleType')->willReturn('F');

        $query = $this->createMock(Query::class);
        $query->expects(self::once())
            ->method('getOneOrNullResult')
            ->willReturn(null);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['select', 'from', 'andWhere', 'setParameter', 'setMaxResults', 'getQuery'])
            ->getMock();
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $peopleService = $this->createMock(PeopleService::class);
        $peopleService->expects(self::once())->method('getMyPeople')->willReturn($currentPeople);

        $peopleRoleService = $this->createStub(PeopleRoleService::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $service = new DeliveryCourierVehicleService(
            $entityManager,
            $this->createStub(TokenStorageInterface::class),
            $peopleService,
            $peopleRoleService,
            new RequestStack()
        );

        $this->expectException(BadRequestHttpException::class);
        $service->saveFromPayload([
            'vehicleType' => 'moto',
            'brand' => 'Honda',
        ]);
    }

    public function testSaveFromPayloadRejectsLegalEntityAccount(): void
    {
        $currentPeople = $this->createMock(People::class);
        $currentPeople->method('getId')->willReturn(9);
        $currentPeople->method('getPeopleType')->willReturn('J');

        $peopleService = $this->createMock(PeopleService::class);
        $peopleService->expects(self::once())->method('getMyPeople')->willReturn($currentPeople);

        $peopleRoleService = $this->createStub(PeopleRoleService::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('createQueryBuilder');
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $service = new DeliveryCourierVehicleService(
            $entityManager,
            $this->createStub(TokenStorageInterface::class),
            $peopleService,
            $peopleRoleService,
            new RequestStack()
        );

        $this->expectException(AccessDeniedHttpException::class);
        $service->saveFromPayload([
            'vehicleType' => 'moto',
        ]);
    }
}
