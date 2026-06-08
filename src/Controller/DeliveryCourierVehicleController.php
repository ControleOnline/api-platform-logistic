<?php

/*
 * Contract imported from MODOS_OPERACAO.md
 * - Vehicle registration is a courier-owned operation and does not mutate delivery-rate versions.
 * - The controller only orchestrates IO; the service owns the vehicle rules and ownership checks.
 */

namespace ControleOnline\Controller;

use ControleOnline\Service\DeliveryCourierVehicleService;
use ControleOnline\Service\HydratorService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\Security as SecurityAttribute;

#[SecurityAttribute("is_granted('ROLE_HUMAN')")]
class DeliveryCourierVehicleController
{
    public function __construct(
        private readonly DeliveryCourierVehicleService $vehicleService,
        private readonly HydratorService $hydratorService,
    ) {
    }

    #[Route('/delivery_courier_vehicles', name: 'delivery_courier_vehicles_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $payload = $this->readPayload($request);
            $vehicle = $this->vehicleService->saveFromPayload($payload);

            return new JsonResponse(
                $this->hydratorService->result([$this->vehicleService->getSnapshot($vehicle)]),
                Response::HTTP_CREATED
            );
        } catch (HttpExceptionInterface $exception) {
            return new JsonResponse(
                $this->hydratorService->error(new \Exception($exception->getMessage())),
                $exception->getStatusCode()
            );
        } catch (\Throwable $exception) {
            return new JsonResponse(
                $this->hydratorService->error(new \Exception($exception->getMessage())),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    private function readPayload(Request $request): array
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            $payload = $request->request->all();
        }

        return is_array($payload) ? $payload : [];
    }
}
