<?php
namespace ControleOnline\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use ControleOnline\Entity\Category;
use ControleOnline\Entity\City;
use ControleOnline\Entity\Order;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Status;
use ControleOnline\Repository\OrderLogisticRepository;
use ControleOnline\Listener\LogListener;
use DateTime;
use DateTimeInterface;

#[ORM\Table(name: 'order_logistic')]
#[ORM\Index(name: 'provider_id', columns: ['provider_id'])]
#[ORM\Index(name: 'order_id', columns: ['order_id'])]
#[ORM\Index(name: 'status_id', columns: ['status_id'])]
#[ORM\EntityListeners([LogListener::class])]
#[ORM\Entity(repositoryClass: OrderLogisticRepository::class)]
#[ApiResource(
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['logistic:read']],
    denormalizationContext: ['groups' => ['logistic:write']],
    security: "is_granted('ROLE_CLIENT')",
    operations: [
        new GetCollection(security: "is_granted('ROLE_CLIENT')"),
        new Get(security: "is_granted('ROLE_CLIENT')"),
        new Post(
            uriTemplate: '/order_logistics',
            security: "is_granted('ROLE_CLIENT')",
            denormalizationContext: ['groups' => ['logistic:write']]
        ),
        new Put(
            security: "is_granted('ROLE_CLIENT')",
            denormalizationContext: ['groups' => ['logistic:write']]
        ),
        new Delete(
            name: 'order_logistics_delete',
            security: "is_granted('ROLE_CLIENT')",
            denormalizationContext: ['groups' => ['logistic:write']]
        )
    ]
)]
#[ApiFilter(DateFilter::class, properties: [
    'estimatedShippingDate',
    'shippingDate',
    'estimatedArrivalDate',
    'arrivalDate'
])]
#[ApiFilter(SearchFilter::class, properties: [
    'order' => 'exact',
    'order.id' => 'exact',
    'order.contract.id' => 'exact',
    'order.client.name' => 'partial',
    'order.productType' => 'partial',
    'order.otherInformations' => 'partial',
    'originType' => 'exact',
    'originProvider' => 'exact',
    'originAddress' => 'partial',
    'originCity' => 'exact',
    'destinationType' => 'exact',
    'destinationProvider' => 'exact',
    'destinationAddress' => 'partial',
    'destinationCity' => 'exact',
    'status' => 'exact'
])]
class OrderLogistic
{
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['logistic:read'])]
    private $id;

    #[ORM\Column(name: 'estimated_shipping_date', type: 'date', nullable: true)]
    #[Groups(['logistic:read', 'logistic:write'])]
    private $estimatedShippingDate = null;

    #[ORM\Column(name: 'shipping_date', type: 'date', nullable: true)]
    #[Groups(['logistic:read', 'logistic:write'])]
    private $shippingDate = null;

    #[ORM\Column(name: 'estimated_arrival_date', type: 'date', nullable: true)]
    #[Groups(['logistic:read', 'logistic:write'])]
    private $estimatedArrivalDate = null;

    #[ORM\Column(name: 'arrival_date', type: 'date', nullable: true)]
    #[Groups(['logistic:read', 'logistic:write'])]
    private $arrivalDate = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'origin_type', referencedColumnName: 'id')]
    #[Groups(['logistic:read', 'logistic:write'])]
    private $originType;

    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn(name: 'origin_city_id', referencedColumnName: 'id')]
    #[Groups(['logistic:read', 'logistic:write'])]
    private $originCity = null;

    #[ORM\Column(name: 'origin_address', type: 'string', length: 150, nullable: true)]
    #[Groups(['logistic:read', 'logistic:write'])]
    private $originAddress = null;

    #[ORM\Column(name: 'price', type: 'float', nullable: false)]
    #[Groups(['logistic:read', 'logistic:write'])]
    private $price = 0;

    #[ORM\Column(name: 'amount_paid', type: 'float', nullable: false)]
    #[Groups(['logistic:read', 'logistic:write'])]
    private $amountPaid = 0;

    #[ORM\Column(name: 'balance', type: 'float', nullable: false)]
    #[Groups(['logistic:read', 'logistic:write'])]
    private $balance = 0;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id')]
    #[Groups(['logistic:read', 'logistic:write'])]
    private $order;

    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ORM\JoinColumn(name: 'origin_provider_id', referencedColumnName: 'id')]
    #[Groups(['logistic:read', 'logistic:write'])]
    private $originProvider;

    #[ORM\ManyToOne(targetEntity: Status::class)]
    #[ORM\JoinColumn(name: 'status_id', referencedColumnName: 'id')]
    #[Groups(['logistic:read', 'logistic:write'])]
    private $status;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'destination_type', referencedColumnName: 'id')]
    #[Groups(['logistic:read', 'logistic:write'])]
    private $destinationType;

    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn(name: 'destination_city_id', referencedColumnName: 'id')]
    #[Groups(['logistic:read', 'logistic:write'])]
    private $destinationCity = null;

    #[ORM\Column(name: 'destination_address', type: 'string', length: 150, nullable: true)]
    #[Groups(['logistic:read', 'logistic:write'])]
    private $destinationAddress = null;

    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ORM\JoinColumn(name: 'destination_provider_id', referencedColumnName: 'id')]
    #[Groups(['logistic:read', 'logistic:write'])]
    private $destinationProvider;

    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id')]
    #[Groups(['logistic:read', 'logistic:write'])]
    private $created_by;

    #[ORM\Column(name: 'last_modified', type: 'datetime', nullable: false, columnDefinition: 'DATETIME')]
    #[Groups(['logistic:read', 'logistic:write'])]
    private $lastModified;

    public function getId()
    {
        return $this->id;
    }

    public function getEstimatedShippingDate()
    {
        return $this->estimatedShippingDate;
    }

    public function setEstimatedShippingDate($estimatedShippingDate)
    {
        $this->estimatedShippingDate = $estimatedShippingDate;
        return $this;
    }

    public function getShippingDate()
    {
        return $this->shippingDate;
    }

    public function setShippingDate($shippingDate)
    {
        $this->shippingDate = $shippingDate;
        return $this;
    }

    public function getEstimatedArrivalDate()
    {
        return $this->estimatedArrivalDate;
    }

    public function setEstimatedArrivalDate($estimatedArrivalDate)
    {
        $this->estimatedArrivalDate = $estimatedArrivalDate;
        return $this;
    }

    public function getArrivalDate()
    {
        return $this->arrivalDate;
    }

    public function setArrivalDate($arrivalDate)
    {
        $this->arrivalDate = $arrivalDate;
        return $this;
    }

    public function getOriginType()
    {
        return $this->originType;
    }

    public function setOriginType($originType)
    {
        $this->originType = $originType;
        return $this;
    }

    public function getOriginCity()
    {
        return $this->originCity;
    }

    public function setOriginCity($originCity)
    {
        $this->originCity = $originCity;
        return $this;
    }

    public function getOriginAddress()
    {
        return $this->originAddress;
    }

    public function setOriginAddress($originAddress)
    {
        $this->originAddress = $originAddress;
        return $this;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function setPrice($price)
    {
        $this->price = $price ?: 0;
        return $this;
    }

    public function getAmountPaid()
    {
        return $this->amountPaid;
    }

    public function setAmountPaid($amountPaid)
    {
        $this->amountPaid = $amountPaid;
        return $this;
    }

    public function getBalance()
    {
        return $this->balance;
    }

    public function setBalance($balance)
    {
        $this->balance = $balance;
        return $this;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function setOrder(Order $order)
    {
        $this->order = $order;
        return $this;
    }

    public function getOriginProvider()
    {
        return $this->originProvider;
    }

    public function setOriginProvider($originProvider): self
    {
        $this->originProvider = $originProvider;
        return $this;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus(Status $status)
    {
        $this->status = $status;
        return $this;
    }

    public function getDestinationType()
    {
        return $this->destinationType;
    }

    public function setDestinationType($destinationType)
    {
        $this->destinationType = $destinationType;
        return $this;
    }

    public function getDestinationCity()
    {
        return $this->destinationCity;
    }

    public function setDestinationCity($destinationCity)
    {
        $this->destinationCity = $destinationCity;
        return $this;
    }

    public function getDestinationAddress()
    {
        return $this->destinationAddress;
    }

    public function setDestinationAddress($destinationAddress)
    {
        $this->destinationAddress = $destinationAddress;
        return $this;
    }

    public function getDestinationProvider()
    {
        return $this->destinationProvider;
    }

    public function setDestinationProvider(?People $destinationProvider)
    {
        $this->destinationProvider = $destinationProvider;
        return $this;
    }

    public function getCreatedBy()
    {
        return $this->created_by;
    }

    public function setCreatedBy($created_by): self
    {
        $this->created_by = $created_by;
        return $this;
    }

    public function getLastModified()
    {
        return $this->lastModified;
    }

    public function setLastModified(DateTimeInterface $lastModified)
    {
        $this->lastModified = $lastModified;
        return $this;
    }
}