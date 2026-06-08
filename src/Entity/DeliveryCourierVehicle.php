<?php

/*
 * Contract imported from MODOS_OPERACAO.md
 * - The vehicle setup is a dedicated courier table, separate from delivery-rate versions.
 * - The courier vehicle must store a rich identity snapshot: type, brand, model, year, plate, and optional color.
 * - A standalone authenticated user can register the first vehicle before any company courier link exists.
 * - Companies never edit courier vehicles directly; the courier owns this record.
 */

namespace ControleOnline\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Table(name: 'delivery_courier_vehicle')]
#[ORM\UniqueConstraint(name: 'delivery_courier_vehicle_unique', columns: ['courier_id'])]
#[ORM\Index(name: 'delivery_courier_vehicle_courier_idx', columns: ['courier_id'])]
#[ORM\Entity]
#[ApiResource(
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['delivery_courier_vehicle:read']],
    denormalizationContext: ['groups' => ['delivery_courier_vehicle:write']],
    security: "is_granted('ROLE_HUMAN')",
    operations: [
        new Get(security: "is_granted('ROLE_HUMAN')"),
        new GetCollection(security: "is_granted('ROLE_HUMAN')"),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'courier' => 'exact',
    'vehicleType' => 'exact',
    'brand' => 'ipartial',
    'model' => 'ipartial',
    'plate' => 'ipartial',
    'color' => 'ipartial',
    'year' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
    'vehicleType',
    'brand',
    'model',
    'plate',
    'year',
    'creationDate',
    'alterDate',
])]
class DeliveryCourierVehicle
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
    #[Groups(['delivery_courier_vehicle:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ORM\JoinColumn(name: 'courier_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['delivery_courier_vehicle:read', 'delivery_courier_vehicle:write'])]
    private ?People $courier = null;

    #[ORM\Column(name: 'vehicle_type', type: 'string', length: 20, nullable: false)]
    #[Groups(['delivery_courier_vehicle:read', 'delivery_courier_vehicle:write'])]
    private string $vehicleType = self::VEHICLE_TYPE_MOTO;

    #[ORM\Column(name: 'brand', type: 'string', length: 80, nullable: true)]
    #[Groups(['delivery_courier_vehicle:read', 'delivery_courier_vehicle:write'])]
    private ?string $brand = null;

    #[ORM\Column(name: 'model', type: 'string', length: 120, nullable: true)]
    #[Groups(['delivery_courier_vehicle:read', 'delivery_courier_vehicle:write'])]
    private ?string $model = null;

    #[ORM\Column(name: 'plate', type: 'string', length: 20, nullable: true)]
    #[Groups(['delivery_courier_vehicle:read', 'delivery_courier_vehicle:write'])]
    private ?string $plate = null;

    #[ORM\Column(name: 'year', type: 'integer', nullable: true)]
    #[Groups(['delivery_courier_vehicle:read', 'delivery_courier_vehicle:write'])]
    private ?int $year = null;

    #[ORM\Column(name: 'color', type: 'string', length: 60, nullable: true)]
    #[Groups(['delivery_courier_vehicle:read', 'delivery_courier_vehicle:write'])]
    private ?string $color = null;

    #[ORM\Column(name: 'creation_date', type: 'datetime', nullable: false, columnDefinition: 'DATETIME DEFAULT CURRENT_TIMESTAMP')]
    #[Groups(['delivery_courier_vehicle:read'])]
    private ?\DateTimeInterface $creationDate = null;

    #[ORM\Column(name: 'alter_date', type: 'datetime', nullable: false, columnDefinition: 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP')]
    #[Groups(['delivery_courier_vehicle:read'])]
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

    public function getCourier(): ?People
    {
        return $this->courier;
    }

    public function setCourier(?People $courier): self
    {
        $this->courier = $courier;

        return $this;
    }

    public function getVehicleType(): string
    {
        return $this->vehicleType;
    }

    public function setVehicleType(?string $vehicleType): self
    {
        $normalizedVehicleType = strtolower(trim((string) $vehicleType));
        $this->vehicleType = in_array($normalizedVehicleType, self::VEHICLE_TYPES, true)
            ? $normalizedVehicleType
            : self::VEHICLE_TYPE_MOTO;

        return $this;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): self
    {
        $normalizedBrand = trim((string) $brand);
        $this->brand = $normalizedBrand !== '' ? $normalizedBrand : null;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): self
    {
        $normalizedModel = trim((string) $model);
        $this->model = $normalizedModel !== '' ? $normalizedModel : null;

        return $this;
    }

    public function getPlate(): ?string
    {
        return $this->plate;
    }

    public function setPlate(?string $plate): self
    {
        $normalizedPlate = strtoupper(preg_replace('/\s+/', '', trim((string) $plate)));
        $this->plate = $normalizedPlate !== '' ? $normalizedPlate : null;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(mixed $year): self
    {
        $normalizedYear = preg_replace('/\D+/', '', (string) $year);
        $this->year = $normalizedYear !== '' ? (int) $normalizedYear : null;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): self
    {
        $normalizedColor = trim((string) $color);
        $this->color = $normalizedColor !== '' ? $normalizedColor : null;

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
