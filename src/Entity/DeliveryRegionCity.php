<?php

namespace ControleOnline\Entity;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
/**
 * DeliveryRegionCity
 *
 * @ORM\EntityListeners ({App\Listener\LogListener::class})
 * @ORM\Table (name="delivery_region_city", uniqueConstraints={@ORM\UniqueConstraint (name="delivery_region_id", columns={"delivery_region_id","city_id"})}, indexes={@ORM\Index (name="city_id", columns={"city_id"})})
 * @ORM\Entity (repositoryClass="ControleOnline\Repository\DeliveryRegionCityRepository")
 */
#[ApiResource(operations: [new Get(security: 'is_granted(\'ROLE_CLIENT\')'), new GetCollection(security: 'is_granted(\'ROLE_CLIENT\')')], formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']], normalizationContext: ['groups' => ['delivery_region_city_read']], denormalizationContext: ['groups' => ['delivery_region_city_write']])]
class DeliveryRegionCity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;
    /**
     * @var \ControleOnline\Entity\DeliveryRegion
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\DeliveryRegion", inversedBy="regionCity")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="delivery_region_id", referencedColumnName="id")
     * })
     */
    private $region;
    /**
     * @var \ControleOnline\Entity\City
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\City")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="city_id", referencedColumnName="id")
     * })
     * @Groups({"delivery_region_read"})
     */
    private $city;
    /**
     * Constructor
     */
    public function __construct()
    {
    }
    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }
    /**
     * Add city
     *
     * @param \ControleOnline\Entity\City $city
     * @return DeliveryRegionCity
     */
    public function setCity(\ControleOnline\Entity\City $city)
    {
        $this->city = $city;
        return $this;
    }
    /**
     * Get city
     *
     * @return \ControleOnline\Entity\DeliveryRegion
     */
    public function getCity()
    {
        return $this->city;
    }
    /**
     * Add region
     *
     * @param \ControleOnline\Entity\DeliveryRegion $region
     * @return DeliveryRegionCity
     */
    public function setRegion(\ControleOnline\Entity\DeliveryRegion $region)
    {
        $this->region = $region;
        return $this;
    }
    /**
     * Get region
     *
     * @return \ControleOnline\Entity\DeliveryRegion
     */
    public function getRegion()
    {
        return $this->region;
    }
}
