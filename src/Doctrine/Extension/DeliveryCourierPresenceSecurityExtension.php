<?php

/*
 * Contract imported from MODOS_OPERACAO.md
 * - Every courier presence and schedule read must be filtered through the domain service.
 * - The ApiPlatform extension delegates collection and item scoping to the presence service.
 */

namespace ControleOnline\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ControleOnline\Entity\DeliveryCourierCompanyPresence;
use ControleOnline\Entity\DeliveryCourierSchedule;
use ControleOnline\Service\DeliveryCourierPresenceService;
use Doctrine\ORM\QueryBuilder;

class DeliveryCourierPresenceSecurityExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private readonly DeliveryCourierPresenceService $presenceService,
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
        if (
            $resourceClass !== DeliveryCourierSchedule::class
            && $resourceClass !== DeliveryCourierCompanyPresence::class
        ) {
            return;
        }

        $this->presenceService->securityFilter(
            $queryBuilder,
            $resourceClass,
            'api_platform',
            $queryBuilder->getRootAliases()[0] ?? null
        );
    }
}
