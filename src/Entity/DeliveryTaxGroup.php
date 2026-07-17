<?php

/*
 * Contract imported from MODOS_OPERACAO.md
 * - DELIVERY courier-rate tables are immutable versions.
 * - The courier owns the version header through the current person/courier link.
 * - Managers only read versions or toggle company activation on copied versions.
 * - This entity is the version header and aggregates km bands plus company links.
 */

namespace ControleOnline\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ControleOnline\Filter\CustomOrFilter;
use ControleOnline\Repository\DeliveryTaxGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Table(name: 'delivery_tax_group')]
#[ORM\Index(name: 'delivery_tax_group_courier_idx', columns: ['carrier_id'])]
#[ORM\Index(name: 'delivery_tax_group_code_idx', columns: ['code'])]
#[ORM\Index(name: 'delivery_tax_group_version_idx', columns: ['version_number'])]
#[ORM\Entity(repositoryClass: DeliveryTaxGroupRepository::class)]
#[ApiResource(
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['delivery_tax_group:read']],
    denormalizationContext: ['groups' => ['delivery_tax_group:write']],
    security: "is_granted('ROLE_HUMAN')",
    operations: [
        new Get(security: "is_granted('ROLE_HUMAN')"),
        new GetCollection(security: "is_granted('ROLE_HUMAN')"),
    ]
)]
#[ApiFilter(CustomOrFilter::class, properties: [
    'code',
    'groupName',
    'vehicleType',
    'courier.name',
    'courier.alias',
])]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
    'code',
    'groupName',
    'vehicleType',
    'versionNumber',
    'creationDate',
    'alterDate',
])]
#[ApiFilter(DateFilter::class, properties: ['creationDate', 'alterDate'])]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'code' => 'exact',
    'groupName' => 'partial',
    'vehicleType' => 'exact',
    'versionNumber' => 'exact',
    'courier' => 'exact',
    'courier.id' => 'exact',
    'previousGroup' => 'exact',
])]
class DeliveryTaxGroup
{
    public const VEHICLE_TYPE_MOTO = 'moto';
    public const VEHICLE_TYPE_BIKE = 'bike';
    public const VEHICLE_TYPES = [
        self::VEHICLE_TYPE_MOTO,
        self::VEHICLE_TYPE_BIKE,
    ];

    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group_company:read', 'delivery_tax:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ORM\JoinColumn(name: 'carrier_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax_group_company:read', 'delivery_tax:read'])]
    private ?People $courier = null;

    #[ORM\Column(name: 'code', type: 'string', length: 50, nullable: true)]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax_group_company:read', 'delivery_tax:read'])]
    private ?string $code = null;

    #[ORM\Column(name: 'group_name', type: 'string', length: 255, nullable: false)]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax_group_company:read', 'delivery_tax:read'])]
    private string $groupName = '';

    #[ORM\Column(name: 'vehicle_type', type: 'string', length: 20, nullable: true)]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax_group_company:read', 'delivery_tax:read'])]
    private ?string $vehicleType = null;

    #[ORM\Column(name: 'version_number', type: 'integer', nullable: false, options: ['default' => 1])]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax_group_company:read', 'delivery_tax:read'])]
    private int $versionNumber = 1;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'previous_group_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write'])]
    private ?self $previousGroup = null;

    #[ORM\Column(name: 'creation_date', type: 'datetime', nullable: false, columnDefinition: 'DATETIME DEFAULT CURRENT_TIMESTAMP')]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group_company:read', 'delivery_tax:read'])]
    private ?\DateTimeInterface $creationDate = null;

    #[ORM\Column(name: 'alter_date', type: 'datetime', nullable: false, columnDefinition: 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP')]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group_company:read', 'delivery_tax:read'])]
    private ?\DateTimeInterface $alterDate = null;

    #[ORM\OneToMany(
        mappedBy: 'deliveryTaxGroup',
        targetEntity: DeliveryTax::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    #[ORM\OrderBy(['taxOrder' => 'ASC', 'id' => 'ASC'])]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write'])]
    private Collection $taxes;

    #[ORM\OneToMany(
        mappedBy: 'deliveryTaxGroup',
        targetEntity: DeliveryTaxGroupCompany::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    #[ORM\OrderBy(['id' => 'ASC'])]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write'])]
    private Collection $companies;

    public function __construct()
    {
        $this->taxes = new ArrayCollection();
        $this->companies = new ArrayCollection();
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code !== null ? trim($code) : null;

        return $this;
    }

    public function getGroupName(): string
    {
        return $this->groupName;
    }

    public function setGroupName(string $groupName): self
    {
        $this->groupName = trim($groupName);

        return $this;
    }

    public function getVehicleType(): ?string
    {
        return $this->vehicleType;
    }

    public function setVehicleType(?string $vehicleType): self
    {
        $normalizedVehicleType = strtolower(trim((string) $vehicleType));
        $this->vehicleType = in_array($normalizedVehicleType, self::VEHICLE_TYPES, true)
            ? $normalizedVehicleType
            : null;

        return $this;
    }

    public function getVersionNumber(): int
    {
        return $this->versionNumber;
    }

    public function setVersionNumber(int $versionNumber): self
    {
        $this->versionNumber = max(1, $versionNumber);

        return $this;
    }

    public function getPreviousGroup(): ?self
    {
        return $this->previousGroup;
    }

    public function setPreviousGroup(?self $previousGroup): self
    {
        $this->previousGroup = $previousGroup;

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

    public function getTaxes(): Collection
    {
        return $this->taxes;
    }

    public function addTax(DeliveryTax $tax): self
    {
        if (!$this->taxes->contains($tax)) {
            $this->taxes->add($tax);
            $tax->setDeliveryTaxGroup($this);
        }

        return $this;
    }

    public function removeTax(DeliveryTax $tax): self
    {
        if ($this->taxes->removeElement($tax) && $tax->getDeliveryTaxGroup() === $this) {
            $tax->setDeliveryTaxGroup(null);
        }

        return $this;
    }

    public function getCompanies(): Collection
    {
        return $this->companies;
    }

    public function addCompany(DeliveryTaxGroupCompany $companyLink): self
    {
        if (!$this->companies->contains($companyLink)) {
            $this->companies->add($companyLink);
            $companyLink->setDeliveryTaxGroup($this);
        }

        return $this;
    }

    public function removeCompany(DeliveryTaxGroupCompany $companyLink): self
    {
        if ($this->companies->removeElement($companyLink) && $companyLink->getDeliveryTaxGroup() === $this) {
            $companyLink->setDeliveryTaxGroup(null);
        }

        return $this;
    }

    public function getTaxesCount(): int
    {
        return $this->taxes->count();
    }

    public function getCompaniesCount(): int
    {
        return $this->companies->count();
    }

    public function getActiveCompaniesCount(): int
    {
        $count = 0;

        foreach ($this->companies as $companyLink) {
            if ($companyLink instanceof DeliveryTaxGroupCompany && $companyLink->isEnabled()) {
                $count++;
            }
        }

        return $count;
    }
}
