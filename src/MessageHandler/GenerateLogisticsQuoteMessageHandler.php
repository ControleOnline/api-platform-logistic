<?php

namespace ControleOnline\MessageHandler;

use ControleOnline\Entity\Order;
use ControleOnline\Message\GenerateLogisticsQuoteMessage;
use ControleOnline\Service\Marketplace\MarketplaceLogisticsQuoteProviderInterface;
use ControleOnline\Service\Marketplace\MarketplaceProviderRegistry;
use ControleOnline\Service\Food99Service;
use ControleOnline\Service\QuoteLogisticsService;
use ControleOnline\Service\UberService;
use ControleOnline\Service\iFoodService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GenerateLogisticsQuoteMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly QuoteLogisticsService $quoteLogisticsService,
        private readonly iFoodService $iFoodService,
        private readonly UberService $uberService,
        private readonly Food99Service $food99Service,
        private readonly ?MarketplaceProviderRegistry $marketplaceProviderRegistry = null,
    ) {
    }

    public function __invoke(GenerateLogisticsQuoteMessage $message): void
    {
        $quoteOrder = $this->entityManager->getRepository(Order::class)->find($message->quoteOrderId);
        if (!$quoteOrder instanceof Order) {
            return;
        }

        $providerKey = $this->normalizeProviderKey($quoteOrder->getApp());
        try {
            $provider = $this->resolveMarketplaceQuoteProvider($providerKey);
            $result = $provider instanceof MarketplaceLogisticsQuoteProviderInterface
                ? $provider->quoteDelivery($quoteOrder)
                : [
                    'errno' => 400,
                    'errmsg' => 'Provider de cotacao invalido.',
                ];
        } catch (\Throwable $exception) {
            $result = [
                'errno' => 500,
                'errmsg' => $exception->getMessage(),
            ];
        }

        $this->quoteLogisticsService->broadcastOrderChange($quoteOrder, 'order.updated', [
            'quoteError' => $result['errmsg'] ?? null,
            'quoteErrorCode' => $result['errno'] ?? null,
        ]);
    }

    private function normalizeProviderKey(?string $value): string
    {
        $normalized = strtolower(trim((string) ($value ?? '')));

        return $normalized === '99food' ? 'food99' : $normalized;
    }

    private function resolveMarketplaceQuoteProvider(string $providerKey): ?MarketplaceLogisticsQuoteProviderInterface
    {
        $normalizedProviderKey = $this->normalizeProviderKey($providerKey);

        if ($this->marketplaceProviderRegistry instanceof MarketplaceProviderRegistry) {
            $provider = $this->marketplaceProviderRegistry->resolveLogisticsQuoteProvider($normalizedProviderKey);
            if ($provider instanceof MarketplaceLogisticsQuoteProviderInterface) {
                return $provider;
            }
        }

        return match ($normalizedProviderKey) {
            'ifood' => $this->iFoodService,
            'uber' => $this->uberService,
            'food99' => $this->food99Service,
            default => null,
        };
    }
}
