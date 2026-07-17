<?php

/*
 * Contract imported from MODOS_OPERACAO.md
 * - Presence schedules are the reusable links between a courier/company row and a schedule definition.
 * - The link keeps the schedule reuse explicit so the company row stays unique and auditable.
 */

namespace ControleOnline\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Table(name: 'delivery_courier_company_presence_schedule')]
#[ORM\UniqueConstraint(name: 'delivery_courier_company_presence_schedule_unique', columns: ['presence_id', 'schedule_id'])]
#[ORM\Entity]
class DeliveryCourierCompanyPresenceSchedule
{
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['delivery_presence:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DeliveryCourierCompanyPresence::class, inversedBy: 'schedules')]
    #[ORM\JoinColumn(name: 'presence_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?DeliveryCourierCompanyPresence $presence = null;

    #[ORM\ManyToOne(targetEntity: DeliveryCourierSchedule::class, inversedBy: 'presenceLinks')]
    #[ORM\JoinColumn(name: 'schedule_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['delivery_presence:read'])]
    private ?DeliveryCourierSchedule $schedule = null;

    #[ORM\Column(name: 'active', type: 'boolean', nullable: false, options: ['default' => true])]
    #[Groups(['delivery_presence:read'])]
    private bool $active = true;

    #[ORM\Column(name: 'creation_date', type: 'datetime', nullable: false, columnDefinition: 'DATETIME DEFAULT CURRENT_TIMESTAMP')]
    #[Groups(['delivery_presence:read'])]
    private ?\DateTimeInterface $creationDate = null;

    #[ORM\Column(name: 'alter_date', type: 'datetime', nullable: false, columnDefinition: 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP')]
    #[Groups(['delivery_presence:read'])]
    private ?\DateTimeInterface $alterDate = null;

    public function __construct()
    {
        $this->creationDate = new \DateTime('now');
        $this->alterDate = new \DateTime('now');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPresence(): ?DeliveryCourierCompanyPresence
    {
        return $this->presence;
    }

    public function setPresence(?DeliveryCourierCompanyPresence $presence): self
    {
        $this->presence = $presence;

        return $this;
    }

    public function getSchedule(): ?DeliveryCourierSchedule
    {
        return $this->schedule;
    }

    public function setSchedule(?DeliveryCourierSchedule $schedule): self
    {
        $this->schedule = $schedule;

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

    #[Groups(['delivery_presence:read'])]
    public function getScheduleLabel(): string
    {
        $schedule = $this->schedule;
        if (!$schedule instanceof DeliveryCourierSchedule) {
            return '-';
        }

        return trim(sprintf('%s %s', $schedule->getWeekdayLabel(), $schedule->getWindowLabel()));
    }
}
