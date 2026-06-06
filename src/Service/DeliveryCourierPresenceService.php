<?php

/*
 * Contract imported from MODOS_OPERACAO.md
 * - Courier presence is scoped by the courier/company homologation contract.
 * - Automatic mode resolves against reusable weekday windows and manual mode stores the override.
 * - All state transitions are audited in the shared log table so both apps can render history.
 */

namespace ControleOnline\Service;

use ControleOnline\Entity\DeliveryCourierCompanyPresence;
use ControleOnline\Entity\DeliveryCourierCompanyPresenceSchedule;
use ControleOnline\Entity\DeliveryCourierSchedule;
use ControleOnline\Entity\People;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as Security;

class DeliveryCourierPresenceService
{
    private ?\Symfony\Component\HttpFoundation\Request $request;

    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly Security $security,
        private readonly PeopleService $peopleService,
        private readonly PeopleRoleService $peopleRoleService,
        private readonly SystemLogWriter $systemLogWriter,
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
        $companyIds = array_map(
            static fn (People $company): int => (int) $company->getId(),
            $this->peopleService->getMyCompanies()
        );

        if (!$currentPeople instanceof People && $companyIds === []) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $queryBuilder->distinct();

        if ($resourceClass === DeliveryCourierSchedule::class) {
            if (!$currentPeople instanceof People) {
                $queryBuilder->andWhere('1 = 0');

                return;
            }

            $queryBuilder->andWhere(sprintf('%s.courier = :delivery_courier_schedule_courier', $rootAlias));
            $queryBuilder->setParameter('delivery_courier_schedule_courier', $currentPeople->getId());

            $courier = $this->normalizeReferenceId($this->request?->query->get('courier'));
            if ($courier !== null && (int) $currentPeople->getId() !== $courier) {
                $queryBuilder->andWhere('1 = 0');

                return;
            }

            return;
        }

        if ($resourceClass !== DeliveryCourierCompanyPresence::class) {
            return;
        }

        $conditions = [];
        if ($currentPeople instanceof People) {
            $conditions[] = sprintf('%s.courier = :delivery_presence_courier', $rootAlias);
            $queryBuilder->setParameter('delivery_presence_courier', $currentPeople->getId());
        }

        if ($companyIds !== []) {
            $conditions[] = sprintf('%s.company IN (:delivery_presence_company_ids)', $rootAlias);
            $queryBuilder->setParameter('delivery_presence_company_ids', $companyIds);
        }

        if ($conditions !== []) {
            $queryBuilder->andWhere($queryBuilder->expr()->orX(...$conditions));
        }

        $company = $this->normalizeReferenceId($this->request?->query->get('company'));
        if ($company !== null) {
            if (!in_array($company, $companyIds, true)) {
                $queryBuilder->andWhere('1 = 0');

                return;
            }

            $queryBuilder->andWhere(sprintf('%s.company = :delivery_presence_company_filter', $rootAlias));
            $queryBuilder->setParameter('delivery_presence_company_filter', $company);
        }

        $courier = $this->normalizeReferenceId($this->request?->query->get('courier'));
        if ($courier !== null) {
            if (!$currentPeople instanceof People || (int) $currentPeople->getId() !== $courier) {
                $queryBuilder->andWhere('1 = 0');

                return;
            }

            $queryBuilder->andWhere(sprintf('%s.courier = :delivery_presence_courier_filter', $rootAlias));
            $queryBuilder->setParameter('delivery_presence_courier_filter', $courier);
        }

        $availabilityMode = trim((string) $this->request?->query->get('availabilityMode', ''));
        if ($availabilityMode !== '') {
            $queryBuilder->andWhere(sprintf('%s.availabilityMode = :delivery_presence_mode', $rootAlias));
            $queryBuilder->setParameter('delivery_presence_mode', strtolower($availabilityMode));
        }

        $isOnline = $this->request?->query->get('isOnline');
        if ($isOnline !== null && $isOnline !== '') {
            $queryBuilder->andWhere(sprintf('%s.isOnline = :delivery_presence_is_online', $rootAlias));
            $queryBuilder->setParameter('delivery_presence_is_online', $this->normalizeBoolean($isOnline));
        }
    }

    public function saveScheduleFromPayload(array $payload): DeliveryCourierSchedule
    {
        $currentPeople = $this->requireCurrentPeople();
        $this->assertCourierRole($currentPeople);

        $schedule = $this->resolveScheduleReference($payload['id'] ?? $payload['scheduleId'] ?? null);
        $isNew = !$schedule instanceof DeliveryCourierSchedule;
        if (!$schedule instanceof DeliveryCourierSchedule) {
            $schedule = new DeliveryCourierSchedule();
        } elseif (!$this->isScheduleOwnedBy($schedule, $currentPeople)) {
            throw new AccessDeniedHttpException('Este horario pertence a outro motoboy.');
        }

        $schedule->setCourier($currentPeople);
        $schedule->setLabel($this->normalizeText($payload['label'] ?? $schedule->getLabel() ?? ''));
        $schedule->setWeekday($this->normalizeWeekday($payload['weekday'] ?? $schedule->getWeekday() ?? null));
        $schedule->setStartTime($payload['startTime'] ?? $payload['start_time'] ?? $schedule->getStartTime());
        $schedule->setEndTime($payload['endTime'] ?? $payload['end_time'] ?? $schedule->getEndTime());
        $schedule->setActive($payload['active'] ?? $schedule->isActive());
        $schedule->setAlterDate(new \DateTime('now'));
        if (!$schedule->getCreationDate() instanceof \DateTimeInterface) {
            $schedule->setCreationDate(new \DateTime('now'));
        }

        if (!$schedule->getStartTime() instanceof \DateTimeInterface || !$schedule->getEndTime() instanceof \DateTimeInterface) {
            throw new BadRequestHttpException('Informe hora inicial e final validas.');
        }

        if ($this->compareTimeValues($schedule->getStartTime(), $schedule->getEndTime()) >= 0) {
            throw new BadRequestHttpException('A hora final precisa ser maior que a hora inicial.');
        }

        if ($schedule->getLabel() === '') {
            $schedule->setLabel($this->buildScheduleLabel($schedule));
        }

        $this->manager->persist($schedule);
        $this->manager->flush();

        $this->logPresenceEvent(
            $isNew ? 'insert' : 'update',
            DeliveryCourierSchedule::class,
            $schedule->getId(),
            $isNew ? 'Horario criado.' : 'Horario atualizado.',
            [
                'courierId' => $currentPeople->getId(),
                'weekday' => $schedule->getWeekday(),
                'weekdayLabel' => $schedule->getWeekdayLabel(),
                'label' => $schedule->getLabel(),
                'window' => $schedule->getWindowLabel(),
                'active' => $schedule->isActive(),
            ]
        );

        $this->refreshPresenceStatesForSchedule($schedule);

        return $schedule;
    }

    public function savePresenceFromPayload(array $payload): DeliveryCourierCompanyPresence
    {
        $currentPeople = $this->requireCurrentPeople();
        $this->assertCourierRole($currentPeople);

        $company = $this->resolveCompanyReference($payload['companyId'] ?? $payload['company'] ?? null);
        if (!$company instanceof People) {
            throw new NotFoundHttpException('Empresa nao encontrada.');
        }

        $this->assertCompanyAccess($company);

        $presence = $this->resolvePresenceReference($payload['id'] ?? $payload['presenceId'] ?? null);
        if (!$presence instanceof DeliveryCourierCompanyPresence) {
            $presence = $this->findPresenceByCourierAndCompany($currentPeople, $company) ?? new DeliveryCourierCompanyPresence();
        } elseif (!$this->isPresenceOwnedBy($presence, $currentPeople)) {
            throw new AccessDeniedHttpException('Esta presenca pertence a outro motoboy.');
        }

        $isNew = !$presence->getId();

        $presence->setCourier($currentPeople);
        $presence->setCompany($company);
        $presence->setAvailabilityMode($payload['availabilityMode'] ?? $payload['availability_mode'] ?? $presence->getAvailabilityMode());

        if (array_key_exists('manualReason', $payload) || array_key_exists('manual_reason', $payload)) {
            $presence->setManualReason($payload['manualReason'] ?? $payload['manual_reason']);
        } elseif ($presence->getAvailabilityMode() === DeliveryCourierCompanyPresence::AVAILABILITY_MODE_AUTOMATIC) {
            $presence->setManualReason(null);
        }

        $scheduleIds = null;
        if (array_key_exists('scheduleIds', $payload) || array_key_exists('schedule_ids', $payload)) {
            $scheduleIds = $this->normalizeIdList($payload['scheduleIds'] ?? $payload['schedule_ids'] ?? []);
        }

        if ($scheduleIds !== null) {
            $this->syncPresenceSchedules($presence, $scheduleIds, $currentPeople);
        }

        if (array_key_exists('isOnline', $payload) || array_key_exists('is_online', $payload)) {
            $presence->setIsOnline($payload['isOnline'] ?? $payload['is_online']);
        }

        $presence->setAlterDate(new \DateTime('now'));
        if (!$presence->getCreationDate() instanceof \DateTimeInterface) {
            $presence->setCreationDate(new \DateTime('now'));
        }

        if ($presence->getAvailabilityMode() === DeliveryCourierCompanyPresence::AVAILABILITY_MODE_AUTOMATIC) {
            $presence->setIsOnline($this->resolvePresenceEffectiveOnline($presence));
            $presence->setManualReason(null);
        } elseif ($presence->getManualReason() === null) {
            throw new BadRequestHttpException('Informe o motivo da conexao ou desconexao.');
        }

        $this->persistPresence($presence);

        $this->manager->flush();

        $this->logPresenceEvent(
            $isNew ? 'insert' : 'update',
            DeliveryCourierCompanyPresence::class,
            $presence->getId(),
            $isNew ? 'Presenca criada.' : 'Presenca atualizada.',
            $this->buildPresenceEventContext($presence, [
                'scheduleIds' => $scheduleIds,
            ])
        );

        return $presence;
    }

    public function togglePresenceState(
        DeliveryCourierCompanyPresence $presence,
        bool $online,
        ?string $reason = null,
        ?string $mode = null
    ): DeliveryCourierCompanyPresence {
        $currentPeople = $this->requireCurrentPeople();
        $this->assertCourierRole($currentPeople);
        $this->assertPresenceOwnership($presence, $currentPeople);

        $previousOnline = $presence->isOnline();
        $previousMode = $presence->getAvailabilityMode();
        $normalizedReason = $reason !== null ? trim($reason) : null;

        $presence->setAvailabilityMode($mode ?? DeliveryCourierCompanyPresence::AVAILABILITY_MODE_MANUAL);
        if ($presence->getAvailabilityMode() === DeliveryCourierCompanyPresence::AVAILABILITY_MODE_AUTOMATIC) {
            $presence->setIsOnline($this->resolvePresenceEffectiveOnline($presence));
            $presence->setManualReason(null);
        } else {
            if ($normalizedReason === null || $normalizedReason === '') {
                throw new BadRequestHttpException('Informe o motivo da conexao ou desconexao.');
            }

            $presence->setIsOnline($online);
            $presence->setManualReason($normalizedReason ?? ($online ? 'Conexao manual.' : 'Desconexao manual.'));
        }

        $now = new \DateTime('now');
        if ($previousOnline !== $presence->isOnline()) {
            if ($presence->isOnline()) {
                $presence->setLastOnlineAt($now);
            } else {
                $presence->setLastOfflineAt($now);
            }
        }

        $presence->setAlterDate($now);
        $this->persistPresence($presence);
        $this->manager->flush();

        $this->logPresenceEvent(
            'update',
            DeliveryCourierCompanyPresence::class,
            $presence->getId(),
            $presence->isOnline() ? 'Conexao do motoboy registrada.' : 'Desconexao do motoboy registrada.',
            $this->buildPresenceEventContext($presence, [
                'previousOnline' => $previousOnline,
                'previousMode' => $previousMode,
                'reason' => $presence->getManualReason(),
            ])
        );

        return $presence;
    }

    public function getAccessibleSchedule(mixed $reference): ?DeliveryCourierSchedule
    {
        $id = $this->normalizeReferenceId($reference);
        if ($id === null) {
            return null;
        }

        $queryBuilder = $this->manager->createQueryBuilder()
            ->select('deliveryCourierSchedule')
            ->from(DeliveryCourierSchedule::class, 'deliveryCourierSchedule')
            ->andWhere('deliveryCourierSchedule.id = :delivery_schedule_id')
            ->setParameter('delivery_schedule_id', $id);

        $this->securityFilter($queryBuilder, DeliveryCourierSchedule::class, 'item', 'deliveryCourierSchedule');

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    public function getAccessiblePresence(mixed $reference): ?DeliveryCourierCompanyPresence
    {
        $id = $this->normalizeReferenceId($reference);
        if ($id === null) {
            return null;
        }

        $queryBuilder = $this->manager->createQueryBuilder()
            ->select('deliveryCourierPresence')
            ->from(DeliveryCourierCompanyPresence::class, 'deliveryCourierPresence')
            ->leftJoin('deliveryCourierPresence.schedules', 'deliveryCourierPresenceSchedule')
            ->leftJoin('deliveryCourierPresenceSchedule.schedule', 'deliveryCourierPresenceScheduleDefinition')
            ->andWhere('deliveryCourierPresence.id = :delivery_presence_id')
            ->setParameter('delivery_presence_id', $id);

        $this->securityFilter($queryBuilder, DeliveryCourierCompanyPresence::class, 'item', 'deliveryCourierPresence');

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    public function findPresenceByCourierAndCompany(People $courier, People $company): ?DeliveryCourierCompanyPresence
    {
        $queryBuilder = $this->manager->createQueryBuilder()
            ->select('deliveryCourierPresence')
            ->from(DeliveryCourierCompanyPresence::class, 'deliveryCourierPresence')
            ->andWhere('deliveryCourierPresence.courier = :courier')
            ->andWhere('deliveryCourierPresence.company = :company')
            ->setParameter('courier', $courier->getId())
            ->setParameter('company', $company->getId());

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    public function toScheduleSnapshot(DeliveryCourierSchedule $schedule): array
    {
        return [
            'id' => $schedule->getId(),
            '@id' => $schedule->getId() ? '/delivery_courier_schedules/' . $schedule->getId() : null,
            'courier' => $this->toPeopleSnapshot($schedule->getCourier()),
            'label' => $schedule->getLabel(),
            'weekday' => $schedule->getWeekday(),
            'weekdayLabel' => $schedule->getWeekdayLabel(),
            'startTime' => $this->formatTime($schedule->getStartTime()),
            'endTime' => $this->formatTime($schedule->getEndTime()),
            'windowLabel' => $schedule->getWindowLabel(),
            'active' => $schedule->isActive(),
            'usageCount' => $schedule->getUsageCount(),
            'creationDate' => $this->formatDateTime($schedule->getCreationDate()),
            'alterDate' => $this->formatDateTime($schedule->getAlterDate()),
        ];
    }

    public function toPresenceSnapshot(DeliveryCourierCompanyPresence $presence): array
    {
        $schedules = [];
        foreach ($presence->getSchedules() as $presenceSchedule) {
            if (!$presenceSchedule instanceof DeliveryCourierCompanyPresenceSchedule) {
                continue;
            }

            $schedules[] = $this->toPresenceScheduleSnapshot($presenceSchedule);
        }

        return [
            'id' => $presence->getId(),
            '@id' => $presence->getId() ? '/delivery_courier_company_presences/' . $presence->getId() : null,
            'courier' => $this->toPeopleSnapshot($presence->getCourier()),
            'company' => $this->toPeopleSnapshot($presence->getCompany()),
            'availabilityMode' => $presence->getAvailabilityMode(),
            'currentModeLabel' => $presence->getCurrentModeLabel(),
            'isOnline' => $presence->isOnline(),
            'effectiveOnline' => $presence->getEffectiveOnline(),
            'availabilityStateLabel' => $presence->getAvailabilityStateLabel(),
            'manualReason' => $presence->getManualReason(),
            'lastOnlineAt' => $this->formatDateTime($presence->getLastOnlineAt()),
            'lastOfflineAt' => $this->formatDateTime($presence->getLastOfflineAt()),
            'creationDate' => $this->formatDateTime($presence->getCreationDate()),
            'alterDate' => $this->formatDateTime($presence->getAlterDate()),
            'schedulesCount' => $presence->getSchedulesCount(),
            'schedulesSummary' => $presence->getSchedulesSummary(),
            'schedules' => $schedules,
        ];
    }

    public function toPresenceScheduleSnapshot(DeliveryCourierCompanyPresenceSchedule $presenceSchedule): array
    {
        return [
            'id' => $presenceSchedule->getId(),
            '@id' => $presenceSchedule->getId() ? '/delivery_courier_company_presence_schedules/' . $presenceSchedule->getId() : null,
            'schedule' => $presenceSchedule->getSchedule() instanceof DeliveryCourierSchedule
                ? $this->toScheduleSnapshot($presenceSchedule->getSchedule())
                : null,
            'scheduleLabel' => $presenceSchedule->getScheduleLabel(),
            'active' => $presenceSchedule->isActive(),
            'creationDate' => $this->formatDateTime($presenceSchedule->getCreationDate()),
            'alterDate' => $this->formatDateTime($presenceSchedule->getAlterDate()),
        ];
    }

    public function syncPresenceStatesForCourier(People $courier): int
    {
        $count = 0;
        $queryBuilder = $this->manager->createQueryBuilder()
            ->select('presence')
            ->from(DeliveryCourierCompanyPresence::class, 'presence')
            ->leftJoin('presence.schedules', 'presenceSchedule')
            ->leftJoin('presenceSchedule.schedule', 'schedule')
            ->andWhere('presence.courier = :courier')
            ->setParameter('courier', $courier->getId());

        /** @var array<int, DeliveryCourierCompanyPresence> $presences */
        $presences = $queryBuilder->getQuery()->getResult();
        foreach ($presences as $presence) {
            if (!$presence instanceof DeliveryCourierCompanyPresence) {
                continue;
            }

            if ($this->refreshDerivedState($presence, false)) {
                $count++;
            }
        }

        if ($count > 0) {
            $this->manager->flush();
        }

        return $count;
    }

    public function syncPresenceStatesForCompany(People $company): int
    {
        $count = 0;
        $queryBuilder = $this->manager->createQueryBuilder()
            ->select('presence')
            ->from(DeliveryCourierCompanyPresence::class, 'presence')
            ->leftJoin('presence.schedules', 'presenceSchedule')
            ->leftJoin('presenceSchedule.schedule', 'schedule')
            ->andWhere('presence.company = :company')
            ->setParameter('company', $company->getId());

        /** @var array<int, DeliveryCourierCompanyPresence> $presences */
        $presences = $queryBuilder->getQuery()->getResult();
        foreach ($presences as $presence) {
            if (!$presence instanceof DeliveryCourierCompanyPresence) {
                continue;
            }

            if ($this->refreshDerivedState($presence, false)) {
                $count++;
            }
        }

        if ($count > 0) {
            $this->manager->flush();
        }

        return $count;
    }

    public function resolveDispatchCandidatesForCompany(People $company): array
    {
        $queryBuilder = $this->manager->createQueryBuilder()
            ->select('presence')
            ->from(DeliveryCourierCompanyPresence::class, 'presence')
            ->leftJoin('presence.schedules', 'presenceSchedule')
            ->leftJoin('presenceSchedule.schedule', 'schedule')
            ->andWhere('presence.company = :company')
            ->setParameter('company', $company->getId());

        /** @var array<int, DeliveryCourierCompanyPresence> $presences */
        $presences = $queryBuilder->getQuery()->getResult();

        $candidates = [];
        foreach ($presences as $presence) {
            if (!$presence instanceof DeliveryCourierCompanyPresence) {
                continue;
            }

            $effectiveOnline = $presence->getEffectiveOnline();
            if (!$effectiveOnline) {
                continue;
            }

            $candidates[] = $this->toPresenceSnapshot($presence);
        }

        return $candidates;
    }

    private function syncPresenceSchedules(
        DeliveryCourierCompanyPresence $presence,
        array $scheduleIds,
        People $currentCourier
    ): void {
        $scheduleIds = array_values(array_unique(array_map('intval', $scheduleIds)));
        $existingByScheduleId = [];

        foreach ($presence->getSchedules() as $presenceSchedule) {
            if (!$presenceSchedule instanceof DeliveryCourierCompanyPresenceSchedule) {
                continue;
            }

            $schedule = $presenceSchedule->getSchedule();
            if ($schedule instanceof DeliveryCourierSchedule && $schedule->getId()) {
                $existingByScheduleId[(int) $schedule->getId()] = $presenceSchedule;
            }
        }

        foreach ($existingByScheduleId as $scheduleId => $presenceSchedule) {
            if (!in_array($scheduleId, $scheduleIds, true)) {
                $presence->removeSchedule($presenceSchedule);
            }
        }

        foreach ($scheduleIds as $scheduleId) {
            if (isset($existingByScheduleId[$scheduleId])) {
                continue;
            }

            $schedule = $this->getAccessibleSchedule($scheduleId);
            if (!$schedule instanceof DeliveryCourierSchedule) {
                throw new NotFoundHttpException('Horario nao encontrado.');
            }

            if (!$this->isScheduleOwnedBy($schedule, $currentCourier)) {
                throw new AccessDeniedHttpException('Horario fora do seu contexto.');
            }

            $presenceSchedule = new DeliveryCourierCompanyPresenceSchedule();
            $presenceSchedule->setSchedule($schedule);
            $presenceSchedule->setPresence($presence);
            $presenceSchedule->setActive(true);
            $presenceSchedule->setCreationDate(new \DateTime('now'));
            $presenceSchedule->setAlterDate(new \DateTime('now'));

            $presence->addSchedule($presenceSchedule);
            $this->manager->persist($presenceSchedule);
        }

        $presence->setAlterDate(new \DateTime('now'));
    }

    private function refreshPresenceStatesForSchedule(DeliveryCourierSchedule $schedule): void
    {
        foreach ($schedule->getPresenceLinks() as $presenceLink) {
            if (!$presenceLink instanceof DeliveryCourierCompanyPresenceSchedule) {
                continue;
            }

            $presence = $presenceLink->getPresence();
            if (!$presence instanceof DeliveryCourierCompanyPresence) {
                continue;
            }

            if ($this->refreshDerivedState($presence, true, 'schedule_updated')) {
                $this->manager->persist($presence);
            }
        }
    }

    private function refreshDerivedState(
        DeliveryCourierCompanyPresence $presence,
        bool $logChange = true,
        string $reason = 'automatic'
    ): bool {
        $currentResolved = $presence->getEffectiveOnline();
        $previousResolved = $presence->isOnline();

        if ($presence->getAvailabilityMode() !== DeliveryCourierCompanyPresence::AVAILABILITY_MODE_AUTOMATIC) {
            return false;
        }

        if ($currentResolved === $previousResolved) {
            return false;
        }

        $now = new \DateTime('now');
        $presence->setIsOnline($currentResolved);
        $presence->setAlterDate($now);
        if ($currentResolved) {
            $presence->setLastOnlineAt($now);
        } else {
            $presence->setLastOfflineAt($now);
        }

        if ($logChange) {
            $this->logPresenceEvent(
                'notice',
                DeliveryCourierCompanyPresence::class,
                $presence->getId(),
                $currentResolved ? 'Presenca automatica ativada.' : 'Presenca automatica desativada.',
                $this->buildPresenceEventContext($presence, [
                    'reason' => $reason,
                    'previousOnline' => $previousResolved,
                ])
            );
        }

        $this->manager->persist($presence);

        return true;
    }

    private function persistPresence(DeliveryCourierCompanyPresence $presence): void
    {
        $this->manager->persist($presence);
        foreach ($presence->getSchedules() as $presenceSchedule) {
            if ($presenceSchedule instanceof DeliveryCourierCompanyPresenceSchedule) {
                $this->manager->persist($presenceSchedule);
            }
        }
    }

    private function resolvePresenceEffectiveOnline(DeliveryCourierCompanyPresence $presence): bool
    {
        return $presence->getEffectiveOnline();
    }

    private function buildScheduleLabel(DeliveryCourierSchedule $schedule): string
    {
        return trim(sprintf('%s %s', $schedule->getWeekdayLabel(), $schedule->getWindowLabel()));
    }

    private function buildPresenceEventContext(
        DeliveryCourierCompanyPresence $presence,
        array $extraContext = []
    ): array {
        return [
            'channel' => 'delivery-presence',
            'message' => $presence->getAvailabilityStateLabel(),
            'context' => array_merge([
                'courierId' => $presence->getCourier()?->getId(),
                'courierLabel' => $this->toPeopleSnapshot($presence->getCourier()),
                'companyId' => $presence->getCompany()?->getId(),
                'companyLabel' => $this->toPeopleSnapshot($presence->getCompany()),
                'availabilityMode' => $presence->getAvailabilityMode(),
                'availabilityStateLabel' => $presence->getAvailabilityStateLabel(),
                'effectiveOnline' => $presence->getEffectiveOnline(),
                'isOnline' => $presence->isOnline(),
                'manualReason' => $presence->getManualReason(),
                'schedulesCount' => $presence->getSchedulesCount(),
                'schedulesSummary' => $presence->getSchedulesSummary(),
            ], $extraContext),
        ];
    }

    private function logPresenceEvent(
        string $action,
        string $class,
        ?int $row,
        string $message,
        array $payload = []
    ): void {
        $this->systemLogWriter->write(
            'entity',
            $action,
            $class,
            $row,
            array_merge([
                'channel' => 'delivery-presence',
                'message' => $message,
                'context' => [],
            ], $payload)
        );
    }

    private function requireCurrentPeople(): People
    {
        $people = $this->peopleService->getMyPeople();
        if (!$people instanceof People) {
            throw new AccessDeniedHttpException('Motoboy nao autenticado.');
        }

        return $people;
    }

    private function assertCourierRole(People $people): void
    {
        if (!in_array('courier', $this->peopleRoleService->getAllRoles($people), true)) {
            throw new AccessDeniedHttpException('Somente o motoboy pode operar esta tela.');
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

    private function assertPresenceOwnership(DeliveryCourierCompanyPresence $presence, People $currentPeople): void
    {
        $courier = $presence->getCourier();
        if (!$courier instanceof People || (int) $courier->getId() !== (int) $currentPeople->getId()) {
            throw new AccessDeniedHttpException('Esta presenca pertence a outro motoboy.');
        }
    }

    private function isPresenceOwnedBy(DeliveryCourierCompanyPresence $presence, People $courier): bool
    {
        return $presence->getCourier() instanceof People && (int) $presence->getCourier()->getId() === (int) $courier->getId();
    }

    private function isScheduleOwnedBy(DeliveryCourierSchedule $schedule, People $courier): bool
    {
        return $schedule->getCourier() instanceof People && (int) $schedule->getCourier()->getId() === (int) $courier->getId();
    }

    private function resolveScheduleReference(mixed $reference): ?DeliveryCourierSchedule
    {
        $id = $this->normalizeReferenceId($reference);
        if ($id === null) {
            return null;
        }

        return $this->manager->getRepository(DeliveryCourierSchedule::class)->find($id);
    }

    private function resolvePresenceReference(mixed $reference): ?DeliveryCourierCompanyPresence
    {
        $id = $this->normalizeReferenceId($reference);
        if ($id === null) {
            return null;
        }

        return $this->manager->getRepository(DeliveryCourierCompanyPresence::class)->find($id);
    }

    private function resolveCompanyReference(mixed $reference): ?People
    {
        $id = $this->normalizeReferenceId($reference);
        if ($id === null) {
            return null;
        }

        return $this->manager->getRepository(People::class)->find($id);
    }

    private function normalizeReferenceId(mixed $reference): ?int
    {
        if (is_object($reference)) {
            if (method_exists($reference, 'getId')) {
                $reference = $reference->getId();
            } elseif (property_exists($reference, 'id')) {
                $reference = $reference->id;
            } elseif (property_exists($reference, 'value')) {
                $reference = $reference->value;
            } elseif (method_exists($reference, '__toString')) {
                $reference = (string) $reference;
            } else {
                $reference = null;
            }
        }

        if (is_array($reference)) {
            $reference = $reference['@id'] ?? $reference['id'] ?? $reference['value'] ?? null;
        }

        $normalized = preg_replace('/\D+/', '', (string) $reference);
        if ($normalized === '') {
            return null;
        }

        return (int) $normalized;
    }

    private function normalizeIdList(mixed $ids): array
    {
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $normalized = [];
        foreach ($ids as $id) {
            $normalizedId = $this->normalizeReferenceId($id);
            if ($normalizedId === null) {
                continue;
            }

            $normalized[$normalizedId] = $normalizedId;
        }

        return array_values($normalized);
    }

    private function normalizeBoolean(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
    }

    private function normalizeWeekday(mixed $weekday): int
    {
        $normalized = strtolower(trim((string) $weekday));
        $map = [
            '0' => 7,
            '7' => 7,
            'sunday' => 7,
            'domingo' => 7,
            '1' => 1,
            'monday' => 1,
            'segunda' => 1,
            '2' => 2,
            'tuesday' => 2,
            'terca' => 2,
            '3' => 3,
            'wednesday' => 3,
            'quarta' => 3,
            '4' => 4,
            'thursday' => 4,
            'quinta' => 4,
            '5' => 5,
            'friday' => 5,
            'sexta' => 5,
            '6' => 6,
            'saturday' => 6,
            'sabado' => 6,
        ];

        if ($normalized !== '' && array_key_exists($normalized, $map)) {
            return $map[$normalized];
        }

        if (is_numeric($weekday)) {
            $value = (int) $weekday;
            if ($value === 0) {
                return 7;
            }

            return min(7, max(1, $value));
        }

        throw new BadRequestHttpException('Informe um dia da semana valido.');
    }

    private function normalizeText(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function compareTimeValues(\DateTimeInterface $left, \DateTimeInterface $right): int
    {
        return strcmp($left->format('H:i:s'), $right->format('H:i:s'));
    }

    private function formatTime(?\DateTimeInterface $time): ?string
    {
        return $time instanceof \DateTimeInterface ? $time->format('H:i') : null;
    }

    private function formatDateTime(?\DateTimeInterface $dateTime): ?string
    {
        return $dateTime instanceof \DateTimeInterface ? $dateTime->format(\DateTimeInterface::ATOM) : null;
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
}
