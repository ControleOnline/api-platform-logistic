<?php

/*
 * Contract imported from MODOS_OPERACAO.md
 * - DELIVERY delivery-rate tables are immutable versions.
 * - A standalone authenticated user may create the first version even before any courier/company link exists.
 * - Courier owns and versions the table snapshot; companies never edit table contents directly.
 * - Company links are tracked separately and toggled by managers without mutating history.
 * - All reads must flow through `securityFilter()` and the same predicate is reused by write helpers.
 */

namespace ControleOnline\Service;

use ControleOnline\Entity\DeliveryTax;
use ControleOnline\Entity\DeliveryTaxGroup;
use ControleOnline\Entity\DeliveryTaxGroupCompany;
use ControleOnline\Entity\People;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as Security;

class DeliveryTaxGroupService
{
    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security,
        private PeopleService $peopleService,
        private PeopleRoleService $peopleRoleService,
        RequestStack $requestStack,
    ) {
        $this->request = $requestStack->getCurrentRequest();
    }

    private ?\Symfony\Component\HttpFoundation\Request $request;

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

        $companyJoinAlias = 'deliveryTaxGroupCompanyFilter';
        if ($this->hasJoinAlias($queryBuilder, $rootAlias, $companyJoinAlias)) {
            return;
        }

        $currentPeople = $this->peopleService->getMyPeople();
        $companyIds = array_map(
            static fn (People $company): int => (int) $company->getId(),
            $this->peopleService->getMyCompanies()
        );

        if (!$currentPeople instanceof People && $companyIds === []) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $queryBuilder->distinct();
        $queryBuilder->leftJoin(sprintf('%s.companies', $rootAlias), $companyJoinAlias);

        $conditions = [];
        if ($currentPeople instanceof People) {
            $conditions[] = sprintf('%s.courier = :delivery_current_people', $rootAlias);
            $queryBuilder->setParameter(
                'delivery_current_people',
                $currentPeople->getId()
            );
        }

        if ($companyIds !== []) {
            $conditions[] = sprintf('%s.company IN (:delivery_company_ids)', $companyJoinAlias);
            $queryBuilder->setParameter('delivery_company_ids', $companyIds);
        }

        if ($conditions !== []) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->orX(...$conditions)
            );
        }

        $company = $this->normalizeReferenceId($this->request?->query->get('company'));
        if ($company !== null) {
            if (!in_array($company, $companyIds, true)) {
                $queryBuilder->andWhere('1 = 0');

                return;
            }

            $queryBuilder->andWhere(sprintf('%s.company = :delivery_company_filter', $companyJoinAlias));
            $queryBuilder->setParameter('delivery_company_filter', $company);
        }

        $courier = $this->normalizeReferenceId($this->request?->query->get('courier'));
        if ($courier !== null) {
            if (!$currentPeople instanceof People || (int) $currentPeople->getId() !== $courier) {
                $queryBuilder->andWhere('1 = 0');

                return;
            }

            $queryBuilder->andWhere(sprintf('%s.courier = :delivery_courier_filter', $rootAlias));
            $queryBuilder->setParameter('delivery_courier_filter', $courier);
        }

        $code = trim((string) $this->request?->query->get('code', ''));
        if ($code !== '') {
            $queryBuilder->andWhere(sprintf('%s.code = :delivery_code', $rootAlias));
            $queryBuilder->setParameter('delivery_code', $code);
        }

        $vehicleType = trim((string) $this->request?->query->get('vehicleType', ''));
        if ($vehicleType !== '') {
            $queryBuilder->andWhere(sprintf('%s.vehicleType = :delivery_vehicle_type', $rootAlias));
            $queryBuilder->setParameter('delivery_vehicle_type', strtolower($vehicleType));
        }
    }

    public function saveFromPayload(array $payload): DeliveryTaxGroup
    {
        $currentPeople = $this->requireCurrentPeople();
        $sourceGroup = $this->resolveSourceGroup($payload['cloneFrom'] ?? $payload['sourceGroup'] ?? null);
        if ($sourceGroup instanceof DeliveryTaxGroup) {
            $this->assertCourierOwnership($sourceGroup, $currentPeople);
        } elseif (!$this->canCreateInitialGroup($currentPeople)) {
            throw new AccessDeniedHttpException('Somente o motoboy pode criar uma nova tabela.');
        }

        $group = new DeliveryTaxGroup();
        $group->setCourier($currentPeople);
        $group->setGroupName($this->normalizeText($payload['groupName'] ?? $sourceGroup?->getGroupName() ?? ''));
        if ($group->getGroupName() === '') {
            throw new BadRequestHttpException('O nome da tabela e obrigatorio.');
        }

        $group->setVehicleType($this->resolveVehicleType($payload['vehicleType'] ?? $sourceGroup?->getVehicleType()));
        if ($group->getVehicleType() === null) {
            throw new BadRequestHttpException('Informe moto ou bicicleta.');
        }

        $group->setCode($this->normalizeText($payload['code'] ?? $sourceGroup?->getCode() ?? ''));
        if ($group->getCode() === null || $group->getCode() === '') {
            $group->setCode($this->buildGroupCode($currentPeople, $group->getGroupName()));
        }

        $nextVersion = $sourceGroup instanceof DeliveryTaxGroup
            ? ($sourceGroup->getVersionNumber() + 1)
            : 1;

        $group->setVersionNumber($nextVersion);
        $group->setPreviousGroup($sourceGroup);
        $group->setCreationDate(new \DateTime('now'));
        $group->setAlterDate(new \DateTime('now'));

        $this->manager->persist($group);

        $taxPayloads = $this->normalizeTaxPayloads($payload);
        if ($taxPayloads === [] && $sourceGroup instanceof DeliveryTaxGroup) {
            $taxPayloads = $this->cloneTaxesToPayload($sourceGroup);
        }

        foreach ($taxPayloads as $index => $taxPayload) {
            $tax = $this->buildTaxFromPayload($group, $taxPayload, $currentPeople, $index);
            $this->manager->persist($tax);
            $group->addTax($tax);
        }

        $companyIds = $this->normalizeCompanyIds($payload['companyIds'] ?? $payload['companies'] ?? []);
        if ($companyIds === [] && $sourceGroup instanceof DeliveryTaxGroup) {
            $companyIds = $this->collectCompanyIdsFromGroup($sourceGroup);
        }

        foreach ($companyIds as $companyId) {
            $company = $this->resolveCompanyReference($companyId);
            if (!$company instanceof People) {
                continue;
            }

            $link = new DeliveryTaxGroupCompany();
            $link->setDeliveryTaxGroup($group);
            $link->setCompany($company);
            $link->setEnabled(false);
            $link->setActivatedAt(null);
            $link->setActivatedBy(null);
            $link->setDeactivatedAt(null);
            $link->setCreationDate(new \DateTime('now'));
            $link->setAlterDate(new \DateTime('now'));
            $this->manager->persist($link);
            $group->addCompany($link);
        }

        $this->manager->flush();

        return $group;
    }

    public function associateCompanies(DeliveryTaxGroup $group, array $companyIds): DeliveryTaxGroup
    {
        $currentPeople = $this->requireCurrentPeople();
        $this->assertCourierOwnership($group, $currentPeople);

        $normalizedCompanyIds = $this->normalizeCompanyIds($companyIds);
        foreach ($normalizedCompanyIds as $companyId) {
            $company = $this->resolveCompanyReference($companyId);
            if (!$company instanceof People) {
                continue;
            }

            $this->assertCompanyAccess($company);

            if ($this->findCompanyLink($group, $company) instanceof DeliveryTaxGroupCompany) {
                continue;
            }

            $link = new DeliveryTaxGroupCompany();
            $link->setDeliveryTaxGroup($group);
            $link->setCompany($company);
            $link->setEnabled(false);
            $link->setCreationDate(new \DateTime('now'));
            $link->setAlterDate(new \DateTime('now'));
            $this->manager->persist($link);
            $group->addCompany($link);
        }

        $group->setAlterDate(new \DateTime('now'));
        $this->manager->flush();

        return $group;
    }

    public function toggleCompanyActivation(DeliveryTaxGroup $group, People $company, bool $enabled): DeliveryTaxGroupCompany
    {
        $this->assertCompanyAccess($company);
        $link = $this->findCompanyLink($group, $company);
        if (!$link instanceof DeliveryTaxGroupCompany) {
            throw new NotFoundHttpException('A empresa ainda nao foi associada a esta tabela.');
        }

        $currentPeople = $this->requireCurrentPeople();
        $link->setEnabled($enabled);
        $link->setAlterDate(new \DateTime('now'));
        $link->setActivatedBy($enabled ? $currentPeople : $link->getActivatedBy());
        $link->setActivatedAt($enabled ? new \DateTime('now') : $link->getActivatedAt());
        $link->setDeactivatedAt($enabled ? null : new \DateTime('now'));
        $group->setAlterDate(new \DateTime('now'));

        $this->manager->persist($link);
        $this->manager->flush();

        return $link;
    }

    public function getAccessibleGroup(mixed $reference): ?DeliveryTaxGroup
    {
        $id = $this->normalizeReferenceId($reference);
        if ($id === null) {
            return null;
        }

        $queryBuilder = $this->manager->createQueryBuilder()
            ->select('deliveryTaxGroup')
            ->from(DeliveryTaxGroup::class, 'deliveryTaxGroup')
            ->andWhere('deliveryTaxGroup.id = :delivery_group_id')
            ->setParameter('delivery_group_id', $id);

        $this->securityFilter($queryBuilder, DeliveryTaxGroup::class, 'item', 'deliveryTaxGroup');

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    private function hasJoinAlias(QueryBuilder $queryBuilder, string $rootAlias, string $joinAlias): bool
    {
        $joins = $queryBuilder->getDQLPart('join');
        $rootJoins = is_array($joins) ? ($joins[$rootAlias] ?? []) : [];
        if (!is_iterable($rootJoins)) {
            return false;
        }

        foreach ($rootJoins as $key => $join) {
            if ($key === $joinAlias) {
                return true;
            }

            if (is_array($join) && (($join['alias'] ?? null) === $joinAlias)) {
                return true;
            }

            if (is_object($join) && method_exists($join, 'getAlias') && $join->getAlias() === $joinAlias) {
                return true;
            }
        }

        return false;
    }

    public function getSnapshot(DeliveryTaxGroup $group): array
    {
        $taxes = [];
        foreach ($group->getTaxes() as $tax) {
            if (!$tax instanceof DeliveryTax) {
                continue;
            }

            $taxes[] = $this->toTaxSnapshot($tax);
        }

        $companies = [];
        foreach ($group->getCompanies() as $link) {
            if (!$link instanceof DeliveryTaxGroupCompany) {
                continue;
            }

            $companies[] = $this->toCompanySnapshot($link);
        }

        return [
            'id' => $group->getId(),
            '@id' => $group->getId() ? '/delivery_tax_groups/' . $group->getId() : null,
            'code' => $group->getCode(),
            'groupName' => $group->getGroupName(),
            'vehicleType' => $group->getVehicleType(),
            'versionNumber' => $group->getVersionNumber(),
            'courier' => $this->toPeopleSnapshot($group->getCourier()),
            'previousGroup' => $this->toGroupReferenceSnapshot($group->getPreviousGroup()),
            'creationDate' => $this->formatDateTime($group->getCreationDate()),
            'alterDate' => $this->formatDateTime($group->getAlterDate()),
            'taxesCount' => $group->getTaxesCount(),
            'companiesCount' => $group->getCompaniesCount(),
            'activeCompaniesCount' => $group->getActiveCompaniesCount(),
            'taxes' => $taxes,
            'companies' => $companies,
        ];
    }

    public function toTaxSnapshot(DeliveryTax $tax): array
    {
        return [
            'id' => $tax->getId(),
            '@id' => $tax->getId() ? '/delivery_taxes/' . $tax->getId() : null,
            'taxName' => $tax->getTaxName(),
            'taxDescription' => $tax->getTaxDescription(),
            'kmFrom' => $tax->getKmFrom(),
            'kmTo' => $tax->getKmTo(),
            'pricePerKm' => $tax->getPricePerKm(),
            'minimumTripValue' => $tax->getMinimumTripValue(),
            'minimumDailyValue' => $tax->getMinimumDailyValue(),
            'taxType' => $tax->getTaxType(),
            'taxSubtype' => $tax->getTaxSubtype(),
            'taxOrder' => $tax->getTaxOrder(),
            'price' => $tax->getPrice(),
            'minimumPrice' => $tax->getMinimumPrice(),
            'optional' => $tax->isOptional(),
            'deadline' => $tax->getDeadline(),
            'finalWeight' => $tax->getFinalWeight(),
            'people' => $this->toPeopleSnapshot($tax->getPeople()),
            'creationDate' => $this->formatDateTime($tax->getCreationDate()),
            'alterDate' => $this->formatDateTime($tax->getAlterDate()),
        ];
    }

    public function toCompanySnapshot(DeliveryTaxGroupCompany $link): array
    {
        return [
            'id' => $link->getId(),
            '@id' => $link->getId() ? '/delivery_tax_group_companies/' . $link->getId() : null,
            'deliveryTaxGroup' => $this->toGroupReferenceSnapshot($link->getDeliveryTaxGroup()),
            'company' => $this->toPeopleSnapshot($link->getCompany()),
            'enabled' => $link->isEnabled(),
            'activatedBy' => $this->toPeopleSnapshot($link->getActivatedBy()),
            'activatedAt' => $this->formatDateTime($link->getActivatedAt()),
            'deactivatedAt' => $this->formatDateTime($link->getDeactivatedAt()),
            'creationDate' => $this->formatDateTime($link->getCreationDate()),
            'alterDate' => $this->formatDateTime($link->getAlterDate()),
        ];
    }

    private function normalizeTaxPayloads(array $payload): array
    {
        $taxes = $payload['taxes'] ?? $payload['bands'] ?? [];
        if (!is_array($taxes)) {
            $taxes = [];
        }

        $normalized = [];
        foreach ($taxes as $index => $tax) {
            if (!is_array($tax)) {
                continue;
            }

            $normalized[] = $tax + ['taxOrder' => $index];
        }

        if ($normalized !== []) {
            return $normalized;
        }

        $singleRowKeys = ['kmFrom', 'kmTo', 'pricePerKm', 'minimumTripValue', 'minimumDailyValue'];
        foreach ($singleRowKeys as $key) {
            if (array_key_exists($key, $payload)) {
                return [[
                    'taxName' => $payload['taxName'] ?? '',
                    'taxDescription' => $payload['taxDescription'] ?? null,
                    'kmFrom' => $payload['kmFrom'] ?? null,
                    'kmTo' => $payload['kmTo'] ?? null,
                    'pricePerKm' => $payload['pricePerKm'] ?? null,
                    'minimumTripValue' => $payload['minimumTripValue'] ?? null,
                    'minimumDailyValue' => $payload['minimumDailyValue'] ?? null,
                ]];
            }
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cloneTaxesToPayload(DeliveryTaxGroup $group): array
    {
        $payload = [];
        foreach ($group->getTaxes() as $tax) {
            if (!$tax instanceof DeliveryTax) {
                continue;
            }

            $payload[] = [
                'taxName' => $tax->getTaxName(),
                'taxDescription' => $tax->getTaxDescription(),
                'kmFrom' => $tax->getKmFrom(),
                'kmTo' => $tax->getKmTo(),
                'pricePerKm' => $tax->getPricePerKm(),
                'minimumTripValue' => $tax->getMinimumTripValue(),
                'minimumDailyValue' => $tax->getMinimumDailyValue(),
                'taxOrder' => $tax->getTaxOrder(),
            ];
        }

        return $payload;
    }

    private function buildTaxFromPayload(
        DeliveryTaxGroup $group,
        array $payload,
        People $courier,
        int $index
    ): DeliveryTax {
        $tax = new DeliveryTax();
        $tax->setDeliveryTaxGroup($group);
        $tax->setPeople($courier);
        $tax->setTaxOrder((int) ($payload['taxOrder'] ?? $index));
        $tax->setTaxName($this->resolveBandName($payload, $index));
        $tax->setTaxDescription($this->normalizeText($payload['taxDescription'] ?? null));
        $tax->setKmFrom($payload['kmFrom'] ?? null);
        $tax->setKmTo($payload['kmTo'] ?? null);
        $tax->setPricePerKm($payload['pricePerKm'] ?? null);
        $tax->setMinimumTripValue($payload['minimumTripValue'] ?? null);
        $tax->setMinimumDailyValue($payload['minimumDailyValue'] ?? null);
        $tax->setTaxType((string) ($payload['taxType'] ?? 'km'));
        $tax->setTaxSubtype((string) ($payload['taxSubtype'] ?? 'band'));
        $tax->setOptional((bool) ($payload['optional'] ?? false));
        $tax->setDeadline((int) ($payload['deadline'] ?? 0));
        $tax->setFinalWeight($payload['finalWeight'] ?? null);
        $tax->setCreationDate(new \DateTime('now'));
        $tax->setAlterDate(new \DateTime('now'));

        return $tax;
    }

    private function resolveBandName(array $payload, int $index): string
    {
        $name = $this->normalizeText($payload['taxName'] ?? '');
        if ($name !== '') {
            return $name;
        }

        $from = $this->normalizeText($payload['kmFrom'] ?? '');
        $to = $this->normalizeText($payload['kmTo'] ?? '');
        if ($from !== '' || $to !== '') {
            return trim(sprintf('%s km - %s km', $from !== '' ? $from : '0', $to !== '' ? $to : '+'));
        }

        return sprintf('Faixa %d', $index + 1);
    }

    private function normalizeCompanyIds(mixed $companyIds): array
    {
        if (!is_array($companyIds)) {
            $companyIds = [$companyIds];
        }

        $normalized = [];
        foreach ($companyIds as $companyId) {
            $id = $this->normalizeReferenceId($companyId);
            if ($id === null) {
                continue;
            }

            $normalized[$id] = $id;
        }

        return array_values($normalized);
    }

    private function collectCompanyIdsFromGroup(DeliveryTaxGroup $group): array
    {
        $companyIds = [];
        foreach ($group->getCompanies() as $companyLink) {
            if (!$companyLink instanceof DeliveryTaxGroupCompany) {
                continue;
            }

            $company = $companyLink->getCompany();
            if (!$company instanceof People) {
                continue;
            }

            $companyIds[] = (int) $company->getId();
        }

        return array_values(array_unique($companyIds));
    }

    private function findCompanyLink(DeliveryTaxGroup $group, People $company): ?DeliveryTaxGroupCompany
    {
        foreach ($group->getCompanies() as $companyLink) {
            if (
                $companyLink instanceof DeliveryTaxGroupCompany
                && $companyLink->getCompany() instanceof People
                && (int) $companyLink->getCompany()->getId() === (int) $company->getId()
            ) {
                return $companyLink;
            }
        }

        return null;
    }

    private function resolveSourceGroup(mixed $reference): ?DeliveryTaxGroup
    {
        $id = $this->normalizeReferenceId($reference);
        if ($id === null) {
            return null;
        }

        return $this->getAccessibleGroup($id);
    }

    private function resolveCompanyReference(mixed $reference): ?People
    {
        $id = $this->normalizeReferenceId($reference);
        if ($id === null) {
            return null;
        }

        return $this->manager->getRepository(People::class)->find($id);
    }

    private function requireCurrentPeople(): People
    {
        $people = $this->peopleService->getMyPeople();
        if (!$people instanceof People) {
            throw new AccessDeniedHttpException('Motoboy nao autenticado.');
        }

        return $people;
    }

    private function assertCourierOwnership(DeliveryTaxGroup $group, People $currentPeople): void
    {
        $courier = $group->getCourier();
        if (!$courier instanceof People || (int) $courier->getId() !== (int) $currentPeople->getId()) {
            throw new AccessDeniedHttpException('Esta tabela pertence a outro motoboy.');
        }
    }

    private function assertCompanyAccess(People $company): void
    {
        $companies = $this->peopleService->getMyCompanies();
        foreach ($companies as $accessibleCompany) {
            if (
                (int) $accessibleCompany->getId() === (int) $company->getId()
                && $this->peopleRoleService->companyHasPanelAccess($company)
            ) {
                return;
            }
        }

        throw new AccessDeniedHttpException('Empresa fora do seu contexto de painel.');
    }

    private function canCreateInitialGroup(People $people): bool
    {
        $roles = array_values(array_filter(
            $this->peopleRoleService->getAllRoles($people),
            static fn (string $role): bool => $role !== 'guest'
        ));

        return $roles === [] || in_array('courier', $roles, true);
    }

    private function resolveVehicleType(mixed $vehicleType): ?string
    {
        $normalizedVehicleType = strtolower(trim((string) $vehicleType));

        return in_array($normalizedVehicleType, DeliveryTaxGroup::VEHICLE_TYPES, true)
            ? $normalizedVehicleType
            : null;
    }

    private function buildGroupCode(People $currentPeople, string $groupName): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($groupName)) ?: 'delivery';
        $slug = trim($slug, '-');

        $suffix = substr(str_replace('.', '', uniqid('', true)), -6);

        return strtoupper(substr(sprintf('%s-%s-%s-%s', $slug, $currentPeople->getId(), date('YmdHis'), $suffix), 0, 50));
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

    private function toGroupReferenceSnapshot(?DeliveryTaxGroup $group): ?array
    {
        if (!$group instanceof DeliveryTaxGroup) {
            return null;
        }

        return [
            'id' => $group->getId(),
            '@id' => '/delivery_tax_groups/' . $group->getId(),
            'code' => $group->getCode(),
            'groupName' => $group->getGroupName(),
            'versionNumber' => $group->getVersionNumber(),
        ];
    }

    private function formatDateTime(?\DateTimeInterface $dateTime): ?string
    {
        return $dateTime ? $dateTime->format(DATE_ATOM) : null;
    }

    private function normalizeText(mixed $value): string
    {
        return trim((string) $value);
    }

    private function normalizeReferenceId(mixed $reference): ?int
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        if ($reference instanceof People) {
            return (int) $reference->getId();
        }

        if ($reference instanceof DeliveryTaxGroup) {
            return (int) $reference->getId();
        }

        $id = preg_replace('/\D+/', '', (string) $reference);

        return $id !== '' ? (int) $id : null;
    }
}
