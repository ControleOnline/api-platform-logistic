<?php

/*
 * Contract imported from MODOS_OPERACAO.md
 * - Vehicle reads must stay scoped to the authenticated courier.
 * - ApiPlatform delegates collection and item scoping to the vehicle service.
 */

namespace ControleOnline\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ControleOnline\Entity\DeliveryCourierVehicle;
use ControleOnline\Service\DeliveryCourierVehicleService;
use Doctrine\ORM\QueryBuilder;

class DeliveryCourierVehicleSecurityExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private readonly DeliveryCourierVehicleService $vehicleService,
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
        if ($resourceClass !== DeliveryCourierVehicle::class) {
            return;
        }

        $this->vehicleService->securityFilter(
            $queryBuilder,
            $resourceClass,
            'api_platform',
            $queryBuilder->getRootAliases()[0] ?? null
        );
    }
}
