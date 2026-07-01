<?php

/*
 * Contract imported from MODOS_OPERACAO.md
 * - Vehicle registration is a dedicated courier-owned table separate from delivery-rate versions.
 * - The vehicle record stores a rich identity snapshot with brand, model, year, plate, and optional color.
 * - The first vehicle can be registered by a standalone authenticated user before any company link exists.
 * - Only physical-person accounts can manage this record; legal-entity accounts are blocked.
 */

namespace ControleOnline\Service;

use ControleOnline\Entity\DeliveryCourierVehicle;
use ControleOnline\Entity\People;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as Security;

class DeliveryCourierVehicleService
{
    private ?\Symfony\Component\HttpFoundation\Request $request;

    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly Security $security,
        private readonly PeopleService $peopleService,
        private readonly PeopleRoleService $peopleRoleService,
        RequestStack $requestStack,
    ) {
        $this->request = $requestStack->getCurrentRequest();
    }

    public function securityFilter(
        QueryBuilder $queryBuilder,
        $resourceClass = null,
        $applyTo = null,
        $rootAlias = null
    ): void {
        $rootAlias ??= $queryBuilder->getRootAliases()[0] ?? null;
        if (!$rootAlias) {
            return;
        }

        $currentPeople = $this->peopleService->getMyPeople();
        if (!$currentPeople instanceof People) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $queryBuilder->distinct();
        $queryBuilder->andWhere(sprintf('%s.courier = :delivery_vehicle_courier', $rootAlias));
        $queryBuilder->setParameter('delivery_vehicle_courier', $currentPeople->getId());

        $courier = $this->normalizeReferenceId($this->request?->query->get('courier'));
        if ($courier !== null && (int) $currentPeople->getId() !== $courier) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $vehicleType = trim((string) $this->request?->query->get('vehicleType', ''));
        if ($vehicleType !== '') {
            $queryBuilder->andWhere(sprintf('%s.vehicleType = :delivery_vehicle_type', $rootAlias));
            $queryBuilder->setParameter('delivery_vehicle_type', strtolower($vehicleType));
        }

        foreach (['brand', 'model', 'plate', 'color'] as $field) {
            $value = trim((string) $this->request?->query->get($field, ''));
            if ($value === '') {
                continue;
            }

            $queryBuilder->andWhere(sprintf('LOWER(%s.%s) LIKE :delivery_vehicle_%s', $rootAlias, $field, $field));
            $queryBuilder->setParameter(
                sprintf('delivery_vehicle_%s', $field),
                '%' . strtolower($value) . '%'
            );
        }

        $year = preg_replace('/\D+/', '', (string) $this->request?->query->get('year', ''));
        if ($year !== '') {
            $queryBuilder->andWhere(sprintf('%s.year = :delivery_vehicle_year', $rootAlias));
            $queryBuilder->setParameter('delivery_vehicle_year', (int) $year);
        }
    }

    public function saveFromPayload(array $payload): DeliveryCourierVehicle
    {
        $currentPeople = $this->requireCurrentPeople();
        if (!$this->canManageVehicle($currentPeople)) {
            throw new AccessDeniedHttpException('Somente pessoa física pode registrar o veículo.');
        }

        $vehicle = $this->resolveCurrentVehicle($currentPeople) ?? new DeliveryCourierVehicle();
        $vehicle->setCourier($currentPeople);
        $vehicleType = $this->resolveVehicleType(
            $payload['vehicleType'] ?? $payload['vehicle_type'] ?? $vehicle->getVehicleType()
        );

        if ($vehicleType === null) {
            throw new BadRequestHttpException('Informe moto ou bicicleta.');
        }

        $vehicle->setVehicleType($vehicleType);
        $vehicle->setBrand($this->resolveVehicleText(
            $payload['brand'] ?? $payload['make'] ?? $vehicle->getBrand()
        ));
        $vehicle->setModel($this->resolveVehicleText(
            $payload['model'] ?? $payload['vehicleModel'] ?? $vehicle->getModel()
        ));
        $vehicle->setPlate($this->resolveVehiclePlate(
            $payload['plate'] ?? $payload['licensePlate'] ?? $vehicle->getPlate()
        ));
        $vehicle->setYear($this->resolveVehicleYear(
            $payload['year'] ?? $payload['vehicleYear'] ?? $vehicle->getYear()
        ));
        $vehicle->setColor($this->resolveVehicleText(
            $payload['color'] ?? $payload['vehicleColor'] ?? $vehicle->getColor()
        ));

        if (!$this->isRichVehicleComplete($vehicle)) {
            throw new BadRequestHttpException('Informe marca, modelo, ano e placa do veículo.');
        }

        $now = new \DateTime('now');
        if (!$vehicle->getCreationDate() instanceof \DateTimeInterface) {
            $vehicle->setCreationDate($now);
        }
        $vehicle->setAlterDate($now);

        $this->manager->persist($vehicle);
        $this->manager->flush();

        return $vehicle;
    }

    public function getSnapshot(DeliveryCourierVehicle $vehicle): array
    {
        return [
            'id' => $vehicle->getId(),
            '@id' => $vehicle->getId() ? '/delivery_courier_vehicles/' . $vehicle->getId() : null,
            'courier' => $this->toPeopleSnapshot($vehicle->getCourier()),
            'vehicleType' => $vehicle->getVehicleType(),
            'brand' => $vehicle->getBrand(),
            'model' => $vehicle->getModel(),
            'plate' => $vehicle->getPlate(),
            'year' => $vehicle->getYear(),
            'color' => $vehicle->getColor(),
            'creationDate' => $this->formatDateTime($vehicle->getCreationDate()),
            'alterDate' => $this->formatDateTime($vehicle->getAlterDate()),
        ];
    }

    public function getCurrentVehicle(): ?DeliveryCourierVehicle
    {
        $currentPeople = $this->requireCurrentPeople();

        return $this->resolveCurrentVehicle($currentPeople);
    }

    private function resolveCurrentVehicle(People $currentPeople): ?DeliveryCourierVehicle
    {
        $queryBuilder = $this->manager->createQueryBuilder()
            ->select('vehicle')
            ->from(DeliveryCourierVehicle::class, 'vehicle')
            ->andWhere('vehicle.courier = :courier')
            ->setParameter('courier', $currentPeople->getId())
            ->setMaxResults(1);

        /** @var DeliveryCourierVehicle|null $vehicle */
        $vehicle = $queryBuilder->getQuery()->getOneOrNullResult();

        return $vehicle;
    }

    private function canManageVehicle(People $people): bool
    {
        return strtoupper(trim((string) $people->getPeopleType())) !== 'J';
    }

    private function requireCurrentPeople(): People
    {
        $people = $this->peopleService->getMyPeople();
        if (!$people instanceof People) {
            throw new AccessDeniedHttpException('Motoboy nao autenticado.');
        }

        return $people;
    }

    private function normalizeReferenceId(mixed $reference): ?int
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        if ($reference instanceof People) {
            return (int) $reference->getId();
        }

        $id = preg_replace('/\D+/', '', (string) $reference);

        return $id !== '' ? (int) $id : null;
    }

    private function resolveVehicleType(mixed $vehicleType): ?string
    {
        $normalizedVehicleType = strtolower(trim((string) $vehicleType));

        return in_array($normalizedVehicleType, DeliveryCourierVehicle::VEHICLE_TYPES, true)
            ? $normalizedVehicleType
            : null;
    }

    private function resolveVehicleText(mixed $value): ?string
    {
        $normalizedValue = trim((string) $value);

        return $normalizedValue !== '' ? $normalizedValue : null;
    }

    private function resolveVehiclePlate(mixed $plate): ?string
    {
        $normalizedPlate = strtoupper(preg_replace('/\s+/', '', trim((string) $plate)));

        return $normalizedPlate !== '' ? $normalizedPlate : null;
    }

    private function resolveVehicleYear(mixed $year): ?int
    {
        $normalizedYear = preg_replace('/\D+/', '', (string) $year);

        return $normalizedYear !== '' ? (int) $normalizedYear : null;
    }

    private function isRichVehicleComplete(DeliveryCourierVehicle $vehicle): bool
    {
        return trim((string) $vehicle->getVehicleType()) !== ''
            && trim((string) $vehicle->getBrand()) !== ''
            && trim((string) $vehicle->getModel()) !== ''
            && trim((string) $vehicle->getPlate()) !== ''
            && $vehicle->getYear() !== null
            && $vehicle->getYear() > 0;
    }

    private function toPeopleSnapshot(?People $people): ?array
    {
        if (!$people instanceof People) {
            return null;
        }

        return [
            'id' => $people->getId(),
            '@id' => '/people/' . $people->getId(),
            'name' => $people->getName(),
            'alias' => $people->getAlias(),
            'enabled' => $people->getEnabled(),
        ];
    }

    private function formatDateTime(?\DateTimeInterface $dateTime): ?string
    {
        return $dateTime ? $dateTime->format(DATE_ATOM) : null;
    }
}
