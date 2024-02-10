<?php

namespace ControleOnline\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Delete;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use ControleOnline\Entity\Status;
use ControleOnline\Entity\SalesOrder;
use ControleOnline\Entity\People;
use DateTime;

/**
 * @ORM\EntityListeners ({App\Listener\LogListener::class})
 * @ORM\Table (name="order_logistic", indexes={@ORM\Index (name="provider_id", columns={"provider_id"}), @ORM\Index(name="order_id", columns={"order_id"}), @ORM\Index(name="status_id", columns={"status_id"})})
 * @ORM\Entity (repositoryClass="ControleOnline\Repository\OrderLogisticRepository")
 */
#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Put(security: 'is_granted(\'ROLE_CLIENT\')', denormalizationContext: ['groups' => ['logistic_write']]),
        new GetCollection(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Delete(name: 'order_logistics_delete', security: 'is_granted(\'ROLE_CLIENT\')', denormalizationContext: ['groups' => ['logistic_write']]),
        new Post(security: 'is_granted(\'ROLE_CLIENT\')', uriTemplate: '/order_logistics', denormalizationContext: ['groups' => ['logistic_write']])
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    security: 'is_granted(\'ROLE_CLIENT\')',
    normalizationContext: ['groups' => ['logistic_read']],
    denormalizationContext: ['groups' => ['logistic_write']],
    filters: [
        'date_filter' => [
            'class' => DateFilter::class,
            'properties' => [
                'estimatedShippingDate',
                'shippingDate',
                'estimatedArrivalDate',
                'arrivalDate',
            ],
        ],
    ],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'order.id'                  => 'exact',
        'order.contract.id'         => 'exact',
        'order.client.name'         => 'partial',
        'order.productType'         => 'partial',
        'order.otherInformations'   => 'partial',
        'originType'                => 'exact',
        'originProvider'            => 'exact',
        'originAddress'             => 'partial',
        'originCity'                => 'exact',
        'destinationType'           => 'exact',
        'destinationProvider'       => 'exact',
        'destinationAddress'        => 'partial',
        'destinationCity'           => 'exact',
        'status'                    => 'exact',
    ]
)]

class OrderLogistic
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @Groups({"logistic_read"})
     */
    private $id;
    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="estimated_shipping_date", type="date", nullable=true)
     * @Groups({"logistic_read","logistic_write"})
     */
    private $estimatedShippingDate = NULL;
    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="shipping_date", type="date", nullable=true)
     * @Groups({"logistic_read","logistic_write"})
     */
    private $shippingDate = NULL;
    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="estimated_arrival_date", type="date", nullable=true)
     * @Groups({"logistic_read","logistic_write"})
     */
    private $estimatedArrivalDate = NULL;
    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="arrival_date", type="date", nullable=true)
     * @Groups({"logistic_read","logistic_write"})
     */
    private $arrivalDate = NULL;
    /**
     * @var \ControleOnline\Entity\Category
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\Category")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="origin_type", referencedColumnName="id")
     * })
     * @Groups({"logistic_read","logistic_write"})
     */
    private $originType;

    /**
     * @var \ControleOnline\Entity\City
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\City")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="origin_city_id", referencedColumnName="id")
     * })
     * @Groups({"logistic_read","logistic_write"})
     */
    private $originCity = NULL;

    /**
     * @var string|null
     *
     * @ORM\Column(name="origin_address", type="string", length=150, nullable=true)
     * @Groups({"logistic_read","logistic_write"})
     */
    private $originAddress = NULL;

    /**
     * @var float
     *
     * @ORM\Column(name="price", type="float", nullable=false)
     * @Groups({"logistic_read","logistic_write"})
     */
    private $price = 0;
    /**
     * @var float
     *
     * @ORM\Column(name="amount_paid", type="float", nullable=false)
     * @Groups({"logistic_read","logistic_write"})
     */
    private $amountPaid = 0;
    /**
     * @var float
     *
     * @ORM\Column(name="balance", type="float", nullable=false)
     * @Groups({"logistic_read","logistic_write"})
     */
    private $balance = 0;
    /**
     * @var \SalesOrder
     *
     * @ORM\ManyToOne(targetEntity="SalesOrder")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="order_id", referencedColumnName="id")
     * })
     * @Groups({"logistic_read","logistic_write"})
     */
    private $order;

    /**
     * @var \People
     *
     * @ORM\ManyToOne(targetEntity="People")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="origin_provider_id", referencedColumnName="id")
     * })
     * @Groups({"logistic_read","logistic_write"})
     */
    private $originProvider;
    /**
     * @var \ControleOnline\Entity\Status
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\Status")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="status_id", referencedColumnName="id")
     * })
     * @Groups({"logistic_read","logistic_write"})
     */
    private $status;
    /**
     * @var \ControleOnline\Entity\Category
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\Category")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="destination_type", referencedColumnName="id")
     * })
     * @Groups({"logistic_read","logistic_write"})
     */
    private $destinationType;

    /**
     * @var \ControleOnline\Entity\City
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\City")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="destination_city_id", referencedColumnName="id")
     * })
     * @Groups({"logistic_read","logistic_write"})
     */

    private $destinationCity = NULL;
    /**
     * @var string|null
     *
     * @ORM\Column(name="destination_address", type="string", length=150, nullable=true)
     * @Groups({"logistic_read","logistic_write"})
     */
    private $destinationAddress = NULL;

    /**
     * @var \People
     *
     * @ORM\ManyToOne(targetEntity="People")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="destination_provider_id", referencedColumnName="id")
     * })
     * @Groups({"logistic_read","logistic_write"})
     */
    private $destinationProvider;
    /**
     * @var \People
     *
     * @ORM\ManyToOne(targetEntity="People")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="created_by", referencedColumnName="id")
     * })
     * @Groups({"logistic_read","logistic_write"})
     */
    private $created_by;
    /**
     * @var \DateTimeInterface
     * @ORM\Column(name="last_modified", type="datetime",  nullable=false, columnDefinition="DATETIME")
     * @Groups({"logistic_read","logistic_write"})
     */
    private $lastModified;
    /**
     * @var \OrderLogisticSurveys
     *
     * @ORM\OneToOne(targetEntity="OrderLogisticSurveys", mappedBy="order_logistic_id")
     * @Groups({"logistic_read"})
     */
    private $orderLogisticSurvey;
    /**
     * Get the value of id
     *
     * @return  int
     */
    public function getId()
    {
        return $this->id;
    }
    /**
     * Get the value of estimatedShippingDate
     */
    public function getEstimatedShippingDate()
    {
        return $this->estimatedShippingDate;
    }

    /**
     * Set the value of estimatedShippingDate
     */
    public function setEstimatedShippingDate($estimatedShippingDate)
    {
        $this->estimatedShippingDate = $estimatedShippingDate;

        return $this;
    }
    /**
     * Get the value of shippingDate
     *
     * @return  \DateTime|null
     */
    public function getShippingDate()
    {
        return $this->shippingDate;
    }
    /**
     * Set the value of shippingDate
     *
     * @param  \DateTime|null  $shippingDate
     *
     * @return  self
     */
    public function setShippingDate($shippingDate)
    {
        $this->shippingDate = $shippingDate;
        return $this;
    }
    /**
     * Get the value of estimatedArrivalDate
     */
    public function getEstimatedArrivalDate()
    {
        return $this->estimatedArrivalDate;
    }

    /**
     * Set the value of estimatedArrivalDate
     */
    public function setEstimatedArrivalDate($estimatedArrivalDate)
    {
        $this->estimatedArrivalDate = $estimatedArrivalDate;

        return $this;
    }
    /**
     * Get the value of arrivalDate
     *
     * @return  \DateTime|null
     */
    public function getArrivalDate()
    {
        return $this->arrivalDate;
    }
    /**
     * Set the value of arrivalDate
     *
     * @param  \DateTime|null  $arrivalDate
     *
     * @return  self
     */
    public function setArrivalDate($arrivalDate)
    {
        $this->arrivalDate = $arrivalDate;
        return $this;
    }
    /**
     * Get the value of originType
     *
     * @return  string|null
     */
    public function getOriginType()
    {
        return $this->originType;
    }
    /**
     * Set the value of originType
     *
     * @param  string|null  $originType
     *
     * @return  self
     */
    public function setOriginType($originType)
    {
        $this->originType = $originType;
        return $this;
    }

    /**
     * Get the value of originCity
     *
     * @return  string|null
     */
    public function getOriginCity()
    {
        return $this->originCity;
    }
    /**
     * Set the value of originCity
     *
     * @param  string|null  $originCity
     *
     * @return  self
     */
    public function setOriginCity($originCity)
    {
        $this->originCity = $originCity;
        return $this;
    }
    /**
     * Get the value of originAddress
     *
     * @return  string|null
     */
    public function getOriginAddress()
    {
        return $this->originAddress;
    }
    /**
     * Set the value of originAddress
     *
     * @param  string|null  $originAddress
     *
     * @return  self
     */
    public function setOriginAddress($originAddress)
    {
        $this->originAddress = $originAddress;
        return $this;
    }
    /**
     * Get the value of price
     *
     * @return  float
     */
    public function getPrice()
    {
        return $this->price;
    }
    /**
     * Set the value of price
     *
     * @param  float  $price
     *
     * @return  self
     */
    public function setPrice($price)
    {
        $this->price = $price ?: 0;
        return $this;
    }
    /**
     * Get order
     *
     * @return \ControleOnline\Entity\SalesOrder
     */
    public function getOrder()
    {
        return $this->order;
    }
    /**
     * Set order
     *
     * @param \ControleOnline\Entity\SalesOrder $order
     */
    public function setOrder(\ControleOnline\Entity\SalesOrder $order)
    {
        $this->order = $order;
        return $this;
    }
    /**
     * Get the value of status
     *
     * @return  \ControleOnline\Entity\Status
     */
    public function getStatus()
    {
        return $this->status;
    }
    /**
     * Set the value of status
     *
     * @param  \ControleOnline\Entity\Status  $status
     *
     * @return  self
     */
    public function setStatus(Status $status)
    {
        $this->status = $status;
        return $this;
    }
    /**
     * Get the value of destinationType
     *
     * @return  string|null
     */
    public function getDestinationType()
    {
        return $this->destinationType;
    }
    /**
     * Set the value of destinationType
     *
     * @param  string|null  $destinationType
     *
     * @return  self
     */
    public function setDestinationType($destinationType)
    {
        $this->destinationType = $destinationType;
        return $this;
    }
    /**
     * Get the value of destinationCity
     *
     * @return  string|null
     */
    public function getDestinationCity()
    {
        return $this->destinationCity;
    }
    /**
     * Set the value of destinationCity
     *
     * @param  string|null  $destinationCity
     *
     * @return  self
     */
    public function setDestinationCity($destinationCity)
    {
        $this->destinationCity = $destinationCity;
        return $this;
    }
    /**
     * Get the value of destinationAddress
     *
     * @return  string|null
     */
    public function getDestinationAddress()
    {
        return $this->destinationAddress;
    }
    /**
     * Set the value of destinationAddress
     *
     * @param  string|null  $destinationAddress
     *
     * @return  self
     */
    public function setDestinationAddress($destinationAddress)
    {
        $this->destinationAddress = $destinationAddress;
        return $this;
    }
    /**
     * Get the value of destinationProvider
     *
     * @return  \People
     */
    public function getDestinationProvider()
    {
        return $this->destinationProvider;
    }
    /**
     * Set the value of destinationProvider
     *
     * @param  \People  $destinationProvider
     *
     * @return  self
     */
    public function setDestinationProvider(?\ControleOnline\Entity\People $destinationProvider)
    {
        $this->destinationProvider = $destinationProvider;
        return $this;
    }
    /**
     * Get the value of lastModified
     *
     * @return  \DateTimeInterface
     */
    public function getLastModified()
    {
        return $this->lastModified;
    }
    /**
     * Set the value of lastModified
     *
     * @param  \DateTimeInterface  $lastModified
     *
     * @return  self
     */
    public function setLastModified(\DateTimeInterface $lastModified)
    {
        $this->lastModified = $lastModified;
        return $this;
    }
    /**
     * Get the value of amountPaid
     *
     * @return  int
     */
    public function getAmountPaid()
    {
        return $this->amountPaid;
    }
    /**
     * Set the value of amountPaid
     *
     * @param  int  $amountPaid
     *
     * @return  self
     */
    public function setAmountPaid($amountPaid)
    {
        $this->amountPaid = $amountPaid;
        return $this;
    }
    /**
     * Get the value of balance
     */
    public function getBalance()
    {
        return $this->balance;
    }

    /**
     * Set the value of balance
     */
    public function setBalance($balance)
    {
        $this->balance = $balance;

        return $this;
    }


    /**
     * Get the value of orderLogisticSurvey
     */
    public function getOrderLogisticSurvey()
    {
        return $this->orderLogisticSurvey;
    }

    /**
     * Set the value of orderLogisticSurvey
     */
    public function setOrderLogisticSurvey(OrderLogisticSurveys $orderLogisticSurvey)
    {
        $this->orderLogisticSurvey = $orderLogisticSurvey;

        return $this;
    }


    /**
     * Get the value of created_by
     */
    public function getCreatedBy()
    {
        return $this->created_by;
    }

    /**
     * Set the value of created_by
     */
    public function setCreatedBy($created_by): self
    {
        $this->created_by = $created_by;

        return $this;
    }

    /**
     * Get the value of originProvider
     */
    public function getOriginProvider()
    {
        return $this->originProvider;
    }

    /**
     * Set the value of originProvider
     */
    public function setOriginProvider($originProvider): self
    {
        $this->originProvider = $originProvider;

        return $this;
    }
}
