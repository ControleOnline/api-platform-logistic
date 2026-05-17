<?php

namespace ControleOnline\Service;

use ControleOnline\Service\Client\WebsocketClient;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionNamedType;
use ReflectionParameter;
use Symfony\Component\Messenger\MessageBusInterface;

class QuoteLogisticsServiceFactory
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly PeopleRoleService $peopleRoleService,
        private readonly StatusService $statusService,
        private readonly MessageBusInterface $bus,
        private readonly Food99Service $food99Service,
        private readonly iFoodService $iFoodService,
        private readonly UberService $uberService,
        private readonly WebsocketClient $websocketClient,
        private readonly ?IntegrationService $integrationService = null,
    ) {
    }

    public function create(): QuoteLogisticsService
    {
        $constructor = new \ReflectionMethod(QuoteLogisticsService::class, '__construct');
        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $arguments[$parameter->getName()] = match ($parameter->getName()) {
                'manager' => $this->manager,
                'peopleRoleService' => $this->peopleRoleService,
                'statusService' => $this->statusService,
                'integrationService' => $this->integrationService ?? throw new \LogicException('QuoteLogisticsService requer IntegrationService.'),
                'bus' => $this->bus,
                'food99Service' => $this->food99Service,
                'iFoodService' => $this->iFoodService,
                'uberService' => $this->uberService,
                'websocketClient' => $this->websocketClient,
                default => $this->resolveByType($parameter),
            };
        }

        return new QuoteLogisticsService(...$arguments);
    }

    private function resolveByType(ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();
        $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

        return match ($typeName) {
            EntityManagerInterface::class => $this->manager,
            PeopleRoleService::class => $this->peopleRoleService,
            StatusService::class => $this->statusService,
            MessageBusInterface::class => $this->bus,
            IntegrationService::class => $this->integrationService ?? throw new \LogicException('QuoteLogisticsService requer IntegrationService.'),
            Food99Service::class => $this->food99Service,
            iFoodService::class => $this->iFoodService,
            UberService::class => $this->uberService,
            WebsocketClient::class => $this->websocketClient,
            default => throw new \LogicException(sprintf('Dependencia nao mapeada para QuoteLogisticsService: %s', $parameter->getName())),
        };
    }
}
