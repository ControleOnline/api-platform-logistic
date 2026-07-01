<?php

/*
 * Contract imported from MODOS_OPERACAO.md
 * - DELIVERY courier-rate versions are immutable and read through scoped queries.
 * - The repository exists so read helpers and future lookups stay close to the aggregate.
 */

namespace ControleOnline\Repository;

use ControleOnline\Entity\DeliveryTaxGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DeliveryTaxGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method DeliveryTaxGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method DeliveryTaxGroup[]    findAll()
 * @method DeliveryTaxGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DeliveryTaxGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeliveryTaxGroup::class);
    }
}
