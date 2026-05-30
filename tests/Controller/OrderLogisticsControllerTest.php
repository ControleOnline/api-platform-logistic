<?php

namespace ControleOnline\Logistic\Tests\Controller;

use ControleOnline\Controller\OrderLogisticsController;
use ControleOnline\Service\HydratorService;
use ControleOnline\Service\QuoteLogisticsService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class OrderLogisticsControllerTest extends TestCase
{
    public function testQuoteReturnsHydratorErrorEnvelopeForHttpExceptions(): void
    {
        $quoteService = $this->createMock(QuoteLogisticsService::class);
        $quoteService
            ->expects(self::once())
            ->method('quote')
            ->with('71066')
            ->willThrowException(new BadRequestHttpException('Pedido sem endereco de entrega valido.'));

        $hydratorService = $this->createMock(HydratorService::class);
        $hydratorService
            ->expects(self::once())
            ->method('error')
            ->with(self::callback(function (\Exception $exception): bool {
                return $exception->getMessage() === 'Pedido sem endereco de entrega valido.';
            }))
            ->willReturn([
                '@context' => '/contexts/Error',
                '@type' => 'Error',
                'hydra:title' => 'An error occurred',
                'hydra:description' => 'Pedido sem endereco de entrega valido.',
            ]);

        $controller = new OrderLogisticsController($quoteService, $hydratorService);
        $response = $controller->quote(Request::create('/marketplace/logistics/orders/71066/quote', 'POST'), '71066');

        self::assertSame(400, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Error', $payload['@type']);
        self::assertSame('Pedido sem endereco de entrega valido.', $payload['hydra:description']);
    }
}
