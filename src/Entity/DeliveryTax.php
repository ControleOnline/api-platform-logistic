<?php

/*
 * Contract imported from MODOS_OPERACAO.md
 * - Km bands belong to a delivery rate version and are immutable once published.
 * - The first slice uses explicit km ranges and monetary values per band.
 * - Legacy delivery columns stay materialized for compatibility, but the new UI reads the km fields.
 */

namespace ControleOnline\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Table(name: 'delivery_tax')]
#[ORM\Index(name: 'delivery_tax_group_id_idx', columns: ['delivery_tax_group_id'])]
class DeliveryTax
{
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DeliveryTaxGroup::class, inversedBy: 'taxes')]
    #[ORM\JoinColumn(name: 'delivery_tax_group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax:read'])]
    private ?DeliveryTaxGroup $deliveryTaxGroup = null;

    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ORM\JoinColumn(name: 'people_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax:read'])]
    private ?People $people = null;

    #[ORM\Column(name: 'tax_name', type: 'string', length: 255, nullable: false)]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax:read'])]
    private string $taxName = '';

    #[ORM\Column(name: 'tax_description', type: 'string', length: 255, nullable: true)]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax:read'])]
    private ?string $taxDescription = null;

    #[ORM\Column(name: 'km_from', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax:read'])]
    private ?string $kmFrom = null;

    #[ORM\Column(name: 'km_to', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax:read'])]
    private ?string $kmTo = null;

    #[ORM\Column(name: 'price_per_km', type: 'decimal', precision: 15, scale: 2, nullable: true)]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax:read'])]
    private ?string $pricePerKm = null;

    #[ORM\Column(name: 'minimum_trip_value', type: 'decimal', precision: 15, scale: 2, nullable: true)]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax:read'])]
    private ?string $minimumTripValue = null;

    #[ORM\Column(name: 'minimum_daily_value', type: 'decimal', precision: 15, scale: 2, nullable: true)]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax:read'])]
    private ?string $minimumDailyValue = null;

    #[ORM\Column(name: 'tax_type', type: 'string', length: 20, nullable: false, options: ['default' => 'km'])]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax:read'])]
    private string $taxType = 'km';

    #[ORM\Column(name: 'tax_subtype', type: 'string', length: 20, nullable: true)]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax:read'])]
    private ?string $taxSubtype = 'band';

    #[ORM\Column(name: 'tax_order', type: 'integer', nullable: false, options: ['default' => 0])]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax:read'])]
    private int $taxOrder = 0;

    #[ORM\Column(name: 'price', type: 'decimal', precision: 15, scale: 2, nullable: true)]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax:read'])]
    private ?string $price = null;

    #[ORM\Column(name: 'minimum_price', type: 'decimal', precision: 15, scale: 2, nullable: true)]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax:read'])]
    private ?string $minimumPrice = null;

    #[ORM\Column(name: 'optional', type: 'boolean', nullable: false, options: ['default' => false])]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax:read'])]
    private bool $optional = false;

    #[ORM\Column(name: 'deadline', type: 'integer', nullable: false, options: ['default' => 0])]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax:read'])]
    private int $deadline = 0;

    #[ORM\Column(name: 'final_weight', type: 'decimal', precision: 10, scale: 3, nullable: true)]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax:read'])]
    private ?string $finalWeight = null;

    #[ORM\Column(name: 'creation_date', type: 'datetime', nullable: false, columnDefinition: 'DATETIME DEFAULT CURRENT_TIMESTAMP')]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax:read'])]
    private ?\DateTimeInterface $creationDate = null;

    #[ORM\Column(name: 'alter_date', type: 'datetime', nullable: false, columnDefinition: 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP')]
    #[Groups(['delivery_tax_group:read', 'delivery_tax_group:write', 'delivery_tax:read'])]
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

    public function getPeople(): ?People
    {
        return $this->people;
    }

    public function setPeople(?People $people): self
    {
        $this->people = $people;

        return $this;
    }

    public function getTaxName(): string
    {
        return $this->taxName;
    }

    public function setTaxName(string $taxName): self
    {
        $this->taxName = trim($taxName);

        return $this;
    }

    public function getTaxDescription(): ?string
    {
        return $this->taxDescription;
    }

    public function setTaxDescription(?string $taxDescription): self
    {
        $this->taxDescription = $taxDescription !== null ? trim($taxDescription) : null;

        return $this;
    }

    public function getKmFrom(): ?string
    {
        return $this->kmFrom;
    }

    public function setKmFrom(mixed $kmFrom): self
    {
        $this->kmFrom = $this->normalizeDecimal($kmFrom);

        return $this;
    }

    public function getKmTo(): ?string
    {
        return $this->kmTo;
    }

    public function setKmTo(mixed $kmTo): self
    {
        $this->kmTo = $this->normalizeDecimal($kmTo);

        return $this;
    }

    public function getPricePerKm(): ?string
    {
        return $this->pricePerKm;
    }

    public function setPricePerKm(mixed $pricePerKm): self
    {
        $this->pricePerKm = $this->normalizeDecimal($pricePerKm);
        $this->price = $this->pricePerKm;

        return $this;
    }

    public function getMinimumTripValue(): ?string
    {
        return $this->minimumTripValue;
    }

    public function setMinimumTripValue(mixed $minimumTripValue): self
    {
        $this->minimumTripValue = $this->normalizeDecimal($minimumTripValue);
        $this->minimumPrice = $this->minimumTripValue;

        return $this;
    }

    public function getMinimumDailyValue(): ?string
    {
        return $this->minimumDailyValue;
    }

    public function setMinimumDailyValue(mixed $minimumDailyValue): self
    {
        $this->minimumDailyValue = $this->normalizeDecimal($minimumDailyValue);

        return $this;
    }

    public function getTaxType(): string
    {
        return $this->taxType;
    }

    public function setTaxType(string $taxType): self
    {
        $this->taxType = trim($taxType) !== '' ? trim($taxType) : 'fixed';

        return $this;
    }

    public function getTaxSubtype(): ?string
    {
        return $this->taxSubtype;
    }

    public function setTaxSubtype(?string $taxSubtype): self
    {
        $this->taxSubtype = $taxSubtype !== null ? trim($taxSubtype) : null;

        return $this;
    }

    public function getTaxOrder(): int
    {
        return $this->taxOrder;
    }

    public function setTaxOrder(int $taxOrder): self
    {
        $this->taxOrder = max(0, $taxOrder);

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(mixed $price): self
    {
        $this->price = $this->normalizeDecimal($price);

        return $this;
    }

    public function getMinimumPrice(): ?string
    {
        return $this->minimumPrice;
    }

    public function setMinimumPrice(mixed $minimumPrice): self
    {
        $this->minimumPrice = $this->normalizeDecimal($minimumPrice);

        return $this;
    }

    public function isOptional(): bool
    {
        return $this->optional;
    }

    public function setOptional(bool $optional): self
    {
        $this->optional = $optional;

        return $this;
    }

    public function getDeadline(): int
    {
        return $this->deadline;
    }

    public function setDeadline(int $deadline): self
    {
        $this->deadline = max(0, $deadline);

        return $this;
    }

    public function getFinalWeight(): ?string
    {
        return $this->finalWeight;
    }

    public function setFinalWeight(mixed $finalWeight): self
    {
        $this->finalWeight = $this->normalizeDecimal($finalWeight, 3);

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

    private function normalizeDecimal(mixed $value, int $scale = 2): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return number_format((float) $value, $scale, '.', '');
        }

        $rawValue = trim((string) $value);
        if ($rawValue === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9,.\-]/', '', $rawValue) ?? '';
        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        return number_format((float) $normalized, $scale, '.', '');
    }
}
