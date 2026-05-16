<?php

namespace ControleOnline\MessageHandler;

use ControleOnline\Entity\Order;
use ControleOnline\Message\GenerateLogisticsQuoteMessage;
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
            $result = match ($providerKey) {
                'ifood' => $this->iFoodService->quoteDelivery($quoteOrder),
                'uber' => $this->uberService->quoteDelivery($quoteOrder),
                'food99' => $this->food99Service->quoteDelivery($quoteOrder),
                default => [
                    'errno' => 400,
                    'errmsg' => 'Provider de cotacao invalido.',
                ],
            };
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
}
