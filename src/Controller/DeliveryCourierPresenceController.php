<?php

/*
 * Contract imported from MODOS_OPERACAO.md
 * - Controllers only orchestrate IO for courier presence and schedule management.
 * - The service owns validations, company homologation checks, and log writing.
 * - The HTTP surface is intentionally small: save schedules, save presence, and toggle state.
 */

namespace ControleOnline\Controller;

use ControleOnline\Entity\DeliveryCourierCompanyPresence;
use ControleOnline\Service\DeliveryCourierPresenceService;
use ControleOnline\Service\HydratorService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\Security as SecurityAttribute;

#[SecurityAttribute("is_granted('ROLE_HUMAN')")]
class DeliveryCourierPresenceController
{
    public function __construct(
        private readonly DeliveryCourierPresenceService $presenceService,
        private readonly HydratorService $hydratorService,
    ) {
    }

    #[Route('/delivery_courier_schedules', name: 'delivery_courier_schedules_create', methods: ['POST'])]
    public function createSchedule(Request $request): JsonResponse
    {
        return $this->saveSchedule($request, null);
    }

    #[Route('/delivery_courier_schedules/{id}', name: 'delivery_courier_schedules_update', methods: ['PUT', 'PATCH'])]
    public function updateSchedule(Request $request, string $id): JsonResponse
    {
        return $this->saveSchedule($request, $id);
    }

    #[Route('/delivery_courier_company_presences', name: 'delivery_courier_company_presences_create', methods: ['POST'])]
    public function createPresence(Request $request): JsonResponse
    {
        return $this->savePresence($request, null);
    }

    #[Route('/delivery_courier_company_presences/{id}', name: 'delivery_courier_company_presences_update', methods: ['PUT', 'PATCH'])]
    public function updatePresence(Request $request, string $id): JsonResponse
    {
        return $this->savePresence($request, $id);
    }

    #[Route('/delivery_courier_company_presences/{id}/state', name: 'delivery_courier_company_presences_toggle', methods: ['PATCH', 'POST'])]
    public function togglePresence(Request $request, string $id): JsonResponse
    {
        try {
            $payload = $this->readPayload($request);
            $presence = $this->presenceService->getAccessiblePresence($id);
            if (!$presence instanceof DeliveryCourierCompanyPresence) {
                throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Presenca nao encontrada.');
            }

            $updated = $this->presenceService->togglePresenceState(
                $presence,
                (bool) ($payload['online'] ?? $payload['isOnline'] ?? false),
                $payload['reason'] ?? $payload['manualReason'] ?? null,
                $payload['mode'] ?? $payload['availabilityMode'] ?? null
            );

            return new JsonResponse(
                $this->hydratorService->result([$this->presenceService->toPresenceSnapshot($updated)]),
                Response::HTTP_OK
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

    private function saveSchedule(Request $request, ?string $id): JsonResponse
    {
        try {
            $payload = $this->readPayload($request);
            if ($id !== null) {
                $payload['id'] = $id;
            }

            $schedule = $this->presenceService->saveScheduleFromPayload($payload);

            return new JsonResponse(
                $this->hydratorService->result([$this->presenceService->toScheduleSnapshot($schedule)]),
                $id !== null ? Response::HTTP_OK : Response::HTTP_CREATED
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

    private function savePresence(Request $request, ?string $id): JsonResponse
    {
        try {
            $payload = $this->readPayload($request);
            if ($id !== null) {
                $payload['id'] = $id;
            }

            $presence = $this->presenceService->savePresenceFromPayload($payload);

            return new JsonResponse(
                $this->hydratorService->result([$this->presenceService->toPresenceSnapshot($presence)]),
                $id !== null ? Response::HTTP_OK : Response::HTTP_CREATED
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
