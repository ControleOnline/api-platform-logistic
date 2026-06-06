<?php

/*
 * Contract imported from MODOS_OPERACAO.md
 * - The courier-company presence row is the operational source of truth for online/offline state.
 * - Automatic mode resolves against reusable schedules; manual mode stores the override reason.
 * - The row stays unique per courier and company and keeps historical timestamps.
 */

namespace ControleOnline\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Table(name: 'delivery_courier_company_presence')]
#[ORM\UniqueConstraint(name: 'delivery_courier_company_presence_unique', columns: ['courier_id', 'company_id'])]
#[ORM\Index(name: 'delivery_courier_company_presence_courier_idx', columns: ['courier_id'])]
#[ORM\Index(name: 'delivery_courier_company_presence_company_idx', columns: ['company_id'])]
#[ORM\Entity]
#[ApiResource(
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['delivery_presence:read']],
    security: "is_granted('ROLE_HUMAN')",
    operations: [
        new Get(security: "is_granted('ROLE_HUMAN')"),
        new GetCollection(security: "is_granted('ROLE_HUMAN')"),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'courier' => 'exact',
    'company' => 'exact',
    'availabilityMode' => 'exact',
    'isOnline' => 'exact',
    'manualReason' => 'partial',
    'company.name' => 'partial',
    'company.alias' => 'partial',
    'courier.name' => 'partial',
    'courier.alias' => 'partial',
])]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
    'availabilityMode',
    'isOnline',
    'lastOnlineAt',
    'lastOfflineAt',
    'creationDate',
    'alterDate',
])]
class DeliveryCourierCompanyPresence
{
    public const AVAILABILITY_MODE_AUTOMATIC = 'automatic';
    public const AVAILABILITY_MODE_MANUAL = 'manual';
    public const AVAILABILITY_MODES = [
        self::AVAILABILITY_MODE_AUTOMATIC,
        self::AVAILABILITY_MODE_MANUAL,
    ];

    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['delivery_presence:read', 'delivery_presence_schedule:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ORM\JoinColumn(name: 'courier_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['delivery_presence:read', 'delivery_presence:write', 'delivery_presence_schedule:read'])]
    private ?People $courier = null;

    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['delivery_presence:read', 'delivery_presence:write', 'delivery_presence_schedule:read'])]
    private ?People $company = null;

    #[ORM\Column(name: 'availability_mode', type: 'string', length: 20, nullable: false, options: ['default' => 'automatic'])]
    #[Groups(['delivery_presence:read', 'delivery_presence:write', 'delivery_presence_schedule:read'])]
    private string $availabilityMode = self::AVAILABILITY_MODE_AUTOMATIC;

    #[ORM\Column(name: 'is_online', type: 'boolean', nullable: false, options: ['default' => false])]
    #[Groups(['delivery_presence:read', 'delivery_presence:write', 'delivery_presence_schedule:read'])]
    private bool $isOnline = false;

    #[ORM\Column(name: 'manual_reason', type: 'text', nullable: true)]
    #[Groups(['delivery_presence:read', 'delivery_presence:write', 'delivery_presence_schedule:read'])]
    private ?string $manualReason = null;

    #[ORM\Column(name: 'last_online_at', type: 'datetime', nullable: true)]
    #[Groups(['delivery_presence:read', 'delivery_presence:write', 'delivery_presence_schedule:read'])]
    private ?\DateTimeInterface $lastOnlineAt = null;

    #[ORM\Column(name: 'last_offline_at', type: 'datetime', nullable: true)]
    #[Groups(['delivery_presence:read', 'delivery_presence:write', 'delivery_presence_schedule:read'])]
    private ?\DateTimeInterface $lastOfflineAt = null;

    #[ORM\Column(name: 'creation_date', type: 'datetime', nullable: false, columnDefinition: 'DATETIME DEFAULT CURRENT_TIMESTAMP')]
    #[Groups(['delivery_presence:read', 'delivery_presence_schedule:read'])]
    private ?\DateTimeInterface $creationDate = null;

    #[ORM\Column(name: 'alter_date', type: 'datetime', nullable: false, columnDefinition: 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP')]
    #[Groups(['delivery_presence:read', 'delivery_presence_schedule:read'])]
    private ?\DateTimeInterface $alterDate = null;

    #[ORM\OneToMany(
        mappedBy: 'presence',
        targetEntity: DeliveryCourierCompanyPresenceSchedule::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    #[ORM\OrderBy(['id' => 'ASC'])]
    #[Groups(['delivery_presence:read'])]
    private Collection $schedules;

    public function __construct()
    {
        $this->schedules = new ArrayCollection();
        $this->creationDate = new \DateTime('now');
        $this->alterDate = new \DateTime('now');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCourier(): ?People
    {
        return $this->courier;
    }

    public function setCourier(?People $courier): self
    {
        $this->courier = $courier;

        return $this;
    }

    public function getCompany(): ?People
    {
        return $this->company;
    }

    public function setCompany(?People $company): self
    {
        $this->company = $company;

        return $this;
    }

    public function getAvailabilityMode(): string
    {
        return $this->availabilityMode;
    }

    public function setAvailabilityMode(?string $availabilityMode): self
    {
        $normalized = strtolower(trim((string) $availabilityMode));
        $this->availabilityMode = in_array($normalized, self::AVAILABILITY_MODES, true)
            ? $normalized
            : self::AVAILABILITY_MODE_AUTOMATIC;

        return $this;
    }

    public function isOnline(): bool
    {
        return $this->isOnline;
    }

    public function getIsOnline(): bool
    {
        return $this->isOnline();
    }

    public function setIsOnline(bool|int|string $isOnline): self
    {
        $this->isOnline = in_array($isOnline, [true, 1, '1', 'true'], true);

        return $this;
    }

    public function getManualReason(): ?string
    {
        return $this->manualReason;
    }

    public function setManualReason(?string $manualReason): self
    {
        $manualReason = $manualReason !== null ? trim($manualReason) : null;
        $this->manualReason = $manualReason !== '' ? $manualReason : null;

        return $this;
    }

    public function getLastOnlineAt(): ?\DateTimeInterface
    {
        return $this->lastOnlineAt;
    }

    public function setLastOnlineAt(?\DateTimeInterface $lastOnlineAt): self
    {
        $this->lastOnlineAt = $lastOnlineAt;

        return $this;
    }

    public function getLastOfflineAt(): ?\DateTimeInterface
    {
        return $this->lastOfflineAt;
    }

    public function setLastOfflineAt(?\DateTimeInterface $lastOfflineAt): self
    {
        $this->lastOfflineAt = $lastOfflineAt;

        return $this;
    }

    public function getCreationDate(): ?\DateTimeInterface
    {
        return $this->creationDate;
    }

    public function setCreationDate(?\DateTimeInterface $creationDate): self
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getAlterDate(): ?\DateTimeInterface
    {
        return $this->alterDate;
    }

    public function setAlterDate(?\DateTimeInterface $alterDate): self
    {
        $this->alterDate = $alterDate;

        return $this;
    }

    public function getSchedules(): Collection
    {
        return $this->schedules;
    }

    public function addSchedule(DeliveryCourierCompanyPresenceSchedule $presenceSchedule): self
    {
        if (!$this->schedules->contains($presenceSchedule)) {
            $this->schedules->add($presenceSchedule);
            $presenceSchedule->setPresence($this);
        }

        return $this;
    }

    public function removeSchedule(DeliveryCourierCompanyPresenceSchedule $presenceSchedule): self
    {
        if ($this->schedules->removeElement($presenceSchedule) && $presenceSchedule->getPresence() === $this) {
            $presenceSchedule->setPresence(null);
        }

        return $this;
    }

    #[Groups(['delivery_presence:read'])]
    public function getSchedulesCount(): int
    {
        return $this->schedules->count();
    }

    #[Groups(['delivery_presence:read'])]
    public function getSchedulesSummary(): string
    {
        $labels = [];
        foreach ($this->schedules as $presenceSchedule) {
            if (!$presenceSchedule instanceof DeliveryCourierCompanyPresenceSchedule) {
                continue;
            }

            $schedule = $presenceSchedule->getSchedule();
            if (!$schedule instanceof DeliveryCourierSchedule || !$presenceSchedule->isActive()) {
                continue;
            }

            $labels[] = trim(sprintf(
                '%s %s',
                $schedule->getWeekdayLabel(),
                $schedule->getWindowLabel()
            ));
        }

        return implode(' | ', array_values(array_filter($labels, static fn ($value) => trim((string) $value) !== '')));
    }

    #[Groups(['delivery_presence:read'])]
    public function getEffectiveOnline(): bool
    {
        if ($this->availabilityMode === self::AVAILABILITY_MODE_MANUAL) {
            return $this->isOnline;
        }

        $now = new \DateTimeImmutable('now');
        $weekday = (int) $now->format('N');
        $currentTime = $now->format('H:i:s');

        foreach ($this->schedules as $presenceSchedule) {
            if (!$presenceSchedule instanceof DeliveryCourierCompanyPresenceSchedule || !$presenceSchedule->isActive()) {
                continue;
            }

            $schedule = $presenceSchedule->getSchedule();
            if (!$schedule instanceof DeliveryCourierSchedule || !$schedule->isActive()) {
                continue;
            }

            if ((int) $schedule->getWeekday() !== $weekday) {
                continue;
            }

            $start = $schedule->getStartTime() instanceof \DateTimeInterface ? $schedule->getStartTime()->format('H:i:s') : null;
            $end = $schedule->getEndTime() instanceof \DateTimeInterface ? $schedule->getEndTime()->format('H:i:s') : null;
            if (!$start || !$end) {
                continue;
            }

            if ($currentTime >= $start && $currentTime <= $end) {
                return true;
            }
        }

        return false;
    }

    #[Groups(['delivery_presence:read'])]
    public function getAvailabilityStateLabel(): string
    {
        $effectiveOnline = $this->getEffectiveOnline();

        if ($this->availabilityMode === self::AVAILABILITY_MODE_MANUAL) {
            return $effectiveOnline ? 'Online manual' : 'Offline manual';
        }

        return $effectiveOnline ? 'Online automatico' : 'Offline automatico';
    }

    #[Groups(['delivery_presence:read'])]
    public function getCurrentModeLabel(): string
    {
        return $this->availabilityMode === self::AVAILABILITY_MODE_MANUAL ? 'Manual' : 'Automatico';
    }
}
