<?php

/*
 * Contract imported from MODOS_OPERACAO.md
 * - Weekly courier schedules are reusable building blocks for company presence.
 * - Each schedule stores a single weekday/time window and can be linked to many companies.
 * - The record belongs to the courier and is kept simple so the presence layer can reuse it.
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

#[ORM\Table(name: 'delivery_courier_schedule')]
#[ORM\Index(name: 'delivery_courier_schedule_courier_idx', columns: ['courier_id'])]
#[ORM\Index(name: 'delivery_courier_schedule_weekday_idx', columns: ['weekday'])]
#[ORM\Entity]
#[ApiResource(
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['delivery_courier_schedule:read']],
    security: "is_granted('ROLE_HUMAN')",
    operations: [
        new Get(security: "is_granted('ROLE_HUMAN')"),
        new GetCollection(security: "is_granted('ROLE_HUMAN')"),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'courier' => 'exact',
    'weekday' => 'exact',
    'active' => 'exact',
    'label' => 'partial',
])]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
    'weekday',
    'startTime',
    'endTime',
    'label',
    'active',
    'creationDate',
    'alterDate',
])]
class DeliveryCourierSchedule
{
    public const WEEKDAY_LABELS = [
        1 => 'Segunda',
        2 => 'Terca',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
        6 => 'Sabado',
        7 => 'Domingo',
    ];

    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['delivery_courier_schedule:read', 'delivery_presence:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ORM\JoinColumn(name: 'courier_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['delivery_courier_schedule:read', 'delivery_courier_schedule:write', 'delivery_presence:read'])]
    private ?People $courier = null;

    #[ORM\Column(name: 'label', type: 'string', length: 255, nullable: false)]
    #[Groups(['delivery_courier_schedule:read', 'delivery_courier_schedule:write', 'delivery_presence:read'])]
    private string $label = '';

    #[ORM\Column(name: 'weekday', type: 'smallint', nullable: false)]
    #[Groups(['delivery_courier_schedule:read', 'delivery_courier_schedule:write', 'delivery_presence:read'])]
    private int $weekday = 1;

    #[ORM\Column(name: 'start_time', type: 'time', nullable: false)]
    #[Groups(['delivery_courier_schedule:read', 'delivery_courier_schedule:write', 'delivery_presence:read'])]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(name: 'end_time', type: 'time', nullable: false)]
    #[Groups(['delivery_courier_schedule:read', 'delivery_courier_schedule:write', 'delivery_presence:read'])]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(name: 'active', type: 'boolean', nullable: false, options: ['default' => true])]
    #[Groups(['delivery_courier_schedule:read', 'delivery_courier_schedule:write', 'delivery_presence:read'])]
    private bool $active = true;

    #[ORM\Column(name: 'creation_date', type: 'datetime', nullable: false, columnDefinition: 'DATETIME DEFAULT CURRENT_TIMESTAMP')]
    #[Groups(['delivery_courier_schedule:read', 'delivery_presence:read'])]
    private ?\DateTimeInterface $creationDate = null;

    #[ORM\Column(name: 'alter_date', type: 'datetime', nullable: false, columnDefinition: 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP')]
    #[Groups(['delivery_courier_schedule:read', 'delivery_presence:read'])]
    private ?\DateTimeInterface $alterDate = null;

    #[ORM\OneToMany(
        mappedBy: 'schedule',
        targetEntity: DeliveryCourierCompanyPresenceSchedule::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $presenceLinks;

    public function __construct()
    {
        $this->presenceLinks = new ArrayCollection();
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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = trim($label);

        return $this;
    }

    public function getWeekday(): int
    {
        return $this->weekday;
    }

    public function setWeekday(int $weekday): self
    {
        $this->weekday = min(7, max(1, $weekday));

        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(mixed $startTime): self
    {
        $this->startTime = $this->normalizeTimeValue($startTime);

        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(mixed $endTime): self
    {
        $this->endTime = $this->normalizeTimeValue($endTime);

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getActive(): bool
    {
        return $this->isActive();
    }

    public function setActive(bool|int|string $active): self
    {
        $this->active = in_array($active, [true, 1, '1', 'true'], true);

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

    public function getPresenceLinks(): Collection
    {
        return $this->presenceLinks;
    }

    public function addPresenceLink(DeliveryCourierCompanyPresenceSchedule $presenceLink): self
    {
        if (!$this->presenceLinks->contains($presenceLink)) {
            $this->presenceLinks->add($presenceLink);
            $presenceLink->setSchedule($this);
        }

        return $this;
    }

    public function removePresenceLink(DeliveryCourierCompanyPresenceSchedule $presenceLink): self
    {
        if ($this->presenceLinks->removeElement($presenceLink) && $presenceLink->getSchedule() === $this) {
            $presenceLink->setSchedule(null);
        }

        return $this;
    }

    #[Groups(['delivery_courier_schedule:read', 'delivery_presence:read'])]
    public function getWeekdayLabel(): string
    {
        return self::WEEKDAY_LABELS[$this->weekday] ?? sprintf('Dia %d', $this->weekday);
    }

    #[Groups(['delivery_courier_schedule:read', 'delivery_presence:read'])]
    public function getWindowLabel(): string
    {
        $start = $this->startTime instanceof \DateTimeInterface ? $this->startTime->format('H:i') : '--:--';
        $end = $this->endTime instanceof \DateTimeInterface ? $this->endTime->format('H:i') : '--:--';

        return sprintf('%s - %s', $start, $end);
    }

    #[Groups(['delivery_courier_schedule:read', 'delivery_presence:read'])]
    public function getUsageCount(): int
    {
        $uniquePresences = [];

        foreach ($this->presenceLinks as $presenceLink) {
            if (!$presenceLink instanceof DeliveryCourierCompanyPresenceSchedule) {
                continue;
            }

            $presence = $presenceLink->getPresence();
            if ($presence instanceof DeliveryCourierCompanyPresence && $presence->getId()) {
                $uniquePresences[(int) $presence->getId()] = true;
            }
        }

        return count($uniquePresences);
    }

    private function normalizeTimeValue(mixed $value): ?\DateTimeInterface
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        foreach (['H:i:s', 'H:i'] as $format) {
            $dateTime = \DateTimeImmutable::createFromFormat($format, $normalized);
            if ($dateTime instanceof \DateTimeImmutable) {
                return $dateTime;
            }
        }

        return null;
    }
}
