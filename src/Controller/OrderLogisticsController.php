<?php

namespace ControleOnline\Controller;

use ControleOnline\Service\HydratorService;
use ControleOnline\Service\OrderLogisticsService;
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
        private readonly OrderLogisticsService $logisticsService,
        private readonly HydratorService $hydratorService,
    ) {
    }

    #[Route('/marketplace/logistics/orders/{orderId}', name: 'marketplace_logistics_order_show', methods: ['GET'])]
    public function show(Request $request, string $orderId): JsonResponse
    {
        try {
            $payload = $this->logisticsService->buildPayload($orderId);

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

    #[Route('/marketplace/logistics/orders/{orderId}/request', name: 'marketplace_logistics_order_request', methods: ['POST'])]
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
