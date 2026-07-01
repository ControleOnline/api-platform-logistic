<?php

/*
 * Contract imported from MODOS_OPERACAO.md
 * - Every DELIVERY rate read must be filtered through DeliveryTaxGroupService::securityFilter().
 * - The ApiPlatform extension delegates collection and item scoping to the domain service.
 */

namespace ControleOnline\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ControleOnline\Entity\DeliveryTaxGroup;
use ControleOnline\Service\DeliveryTaxGroupService;
use Doctrine\ORM\QueryBuilder;

class DeliveryTaxGroupSecurityExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private readonly DeliveryTaxGroupService $deliveryTaxGroupService,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->applySecurityFilter($queryBuilder, $resourceClass);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->applySecurityFilter($queryBuilder, $resourceClass);
    }

    private function applySecurityFilter(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if ($resourceClass !== DeliveryTaxGroup::class) {
            return;
        }

        $this->deliveryTaxGroupService->securityFilter($queryBuilder, $resourceClass, 'api_platform', $queryBuilder->getRootAliases()[0] ?? null);
    }
}
