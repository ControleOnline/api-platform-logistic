<?php

namespace ControleOnline\Controller;

use ControleOnline\Service\HydratorService;
use ControleOnline\Service\QuoteLogisticsService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\Security as SecurityAttribute;

#[SecurityAttribute("is_granted('ROLE_HUMAN')")]
class OrderLogisticsController
{
    public function __construct(
        private readonly QuoteLogisticsService $logisticsService,
        private readonly HydratorService $hydratorService,
    ) {
    }

    #[Route('/marketplace/logistics/orders/{orderId}', name: 'marketplace_logistics_order_show', methods: ['GET'])]
    #[Route('/orders/{orderId}/logistic', name: 'orders_logistic_order_show', methods: ['GET'])]
    public function show(Request $request, string $orderId): JsonResponse
    {
        try {
            $payload = $this->logisticsService->show($orderId);

            return new JsonResponse(
                $this->hydratorService->result([$payload]),
                Response::HTTP_OK,
            );
        } catch (HttpExceptionInterface $exception) {
            return new JsonResponse(
                ['error' => $exception->getMessage()],
                $exception->getStatusCode(),
            );
        } catch (\Throwable $exception) {
            return new JsonResponse(
                $this->hydratorService->error(new \Exception($exception->getMessage())),
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    #[Route('/marketplace/logistics/orders/{orderId}/quote', name: 'marketplace_logistics_order_quote', methods: ['POST'])]
    #[Route('/orders/{orderId}/logistic/quote', name: 'orders_logistic_order_quote', methods: ['POST'])]
    public function quote(Request $request, string $orderId): JsonResponse
    {
        try {
            $result = $this->logisticsService->quote($orderId);

            return new JsonResponse(
                $this->hydratorService->result([$result]),
                Response::HTTP_OK,
            );
        } catch (HttpExceptionInterface $exception) {
            return new JsonResponse(
                ['error' => $exception->getMessage()],
                $exception->getStatusCode(),
            );
        } catch (\Throwable $exception) {
            return new JsonResponse(
                $this->hydratorService->error(new \Exception($exception->getMessage())),
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    #[Route('/marketplace/logistics/orders/{orderId}/quotes/{quoteOrderId}/select', name: 'marketplace_logistics_order_quote_select', methods: ['POST'])]
    #[Route('/orders/{orderId}/logistic/quotes/{quoteOrderId}/select', name: 'orders_logistic_order_quote_select', methods: ['POST'])]
    public function selectQuote(Request $request, string $orderId, string $quoteOrderId): JsonResponse
    {
        try {
            $result = $this->logisticsService->selectQuote($orderId, $quoteOrderId);

            return new JsonResponse(
                $this->hydratorService->result([$result]),
                Response::HTTP_OK,
            );
        } catch (HttpExceptionInterface $exception) {
            return new JsonResponse(
                ['error' => $exception->getMessage()],
                $exception->getStatusCode(),
            );
        } catch (\Throwable $exception) {
            return new JsonResponse(
                $this->hydratorService->error(new \Exception($exception->getMessage())),
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    #[Route('/marketplace/logistics/orders/{orderId}/request', name: 'marketplace_logistics_order_request', methods: ['POST'])]
    #[Route('/orders/{orderId}/logistic/request', name: 'orders_logistic_order_request', methods: ['POST'])]
    public function request(Request $request, string $orderId): JsonResponse
    {
        try {
            $payload = [];
            try {
                $payload = $request->toArray();
            } catch (\Throwable) {
                $payload = $request->request->all();
            }
            $result = $this->logisticsService->requestDelivery($orderId, $payload);

            return new JsonResponse(
                $this->hydratorService->result([$result]),
                Response::HTTP_OK,
            );
        } catch (HttpExceptionInterface $exception) {
            return new JsonResponse(
                ['error' => $exception->getMessage()],
                $exception->getStatusCode(),
            );
        } catch (\Throwable $exception) {
            return new JsonResponse(
                $this->hydratorService->error(new \Exception($exception->getMessage())),
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }
}
