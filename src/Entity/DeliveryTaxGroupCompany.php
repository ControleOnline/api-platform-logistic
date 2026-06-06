<?php

/*
 * Contract imported from MODOS_OPERACAO.md
 * - Company activation is a history table, not a mutable version header.
 * - Couriers associate one table version with one or more companies.
 * - Managers toggle activation per company and keep prior version history intact.
 */

namespace ControleOnline\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Table(name: 'delivery_tax_group_company')]
#[ORM\UniqueConstraint(name: 'delivery_tax_group_company_unique', columns: ['delivery_tax_group_id', 'people_id'])]
class DeliveryTaxGroupCompany
{
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DeliveryTaxGroup::class, inversedBy: 'companies')]
    #[ORM\JoinColumn(name: 'delivery_tax_group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write'])]
    private ?DeliveryTaxGroup $deliveryTaxGroup = null;

    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ORM\JoinColumn(name: 'people_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write'])]
    private ?People $company = null;

    #[ORM\Column(name: 'enabled', type: 'boolean', nullable: false, options: ['default' => false])]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write'])]
    private bool $enabled = false;

    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ORM\JoinColumn(name: 'activated_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write'])]
    private ?People $activatedBy = null;

    #[ORM\Column(name: 'activated_at', type: 'datetime', nullable: true)]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write'])]
    private ?\DateTimeInterface $activatedAt = null;

    #[ORM\Column(name: 'deactivated_at', type: 'datetime', nullable: true)]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write'])]
    private ?\DateTimeInterface $deactivatedAt = null;

    #[ORM\Column(name: 'creation_date', type: 'datetime', nullable: false, columnDefinition: 'DATETIME DEFAULT CURRENT_TIMESTAMP')]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write'])]
    private ?\DateTimeInterface $creationDate = null;

    #[ORM\Column(name: 'alter_date', type: 'datetime', nullable: false, columnDefinition: 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP')]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write'])]
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

    public function getDeliveryTaxGroup(): ?DeliveryTaxGroup
    {
        return $this->deliveryTaxGroup;
    }

    public function setDeliveryTaxGroup(?DeliveryTaxGroup $deliveryTaxGroup): self
    {
        $this->deliveryTaxGroup = $deliveryTaxGroup;

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

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getEnabled(): bool
    {
        return $this->isEnabled();
    }

    public function setEnabled(bool|int|string $enabled): self
    {
        $this->enabled = in_array($enabled, [true, 1, '1', 'true'], true);

        return $this;
    }

    public function getActivatedBy(): ?People
    {
        return $this->activatedBy;
    }

    public function setActivatedBy(?People $activatedBy): self
    {
        $this->activatedBy = $activatedBy;

        return $this;
    }

    public function getActivatedAt(): ?\DateTimeInterface
    {
        return $this->activatedAt;
    }

    public function setActivatedAt(?\DateTimeInterface $activatedAt): self
    {
        $this->activatedAt = $activatedAt;

        return $this;
    }

    public function getDeactivatedAt(): ?\DateTimeInterface
    {
        return $this->deactivatedAt;
    }

    public function setDeactivatedAt(?\DateTimeInterface $deactivatedAt): self
    {
        $this->deactivatedAt = $deactivatedAt;

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
}
