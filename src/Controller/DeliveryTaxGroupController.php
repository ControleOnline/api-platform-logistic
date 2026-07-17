<?php

/*
 * Contract imported from MODOS_OPERACAO.md
 * - POST creates a new immutable delivery-rate version.
 * - Courier association and manager activation both reuse the same service predicates.
 * - Controllers only orchestrate IO; the service owns the business rules.
 */

namespace ControleOnline\Controller;

use ControleOnline\Entity\DeliveryTaxGroup;
use ControleOnline\Entity\People;
use ControleOnline\Service\DeliveryTaxGroupService;
use ControleOnline\Service\HydratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\Security as SecurityAttribute;

#[SecurityAttribute("is_granted('ROLE_HUMAN')")]
class DeliveryTaxGroupController
{
    public function __construct(
        private readonly DeliveryTaxGroupService $deliveryTaxGroupService,
        private readonly HydratorService $hydratorService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/delivery_tax_groups', name: 'delivery_tax_groups_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $payload = $this->readPayload($request);
            $group = $this->deliveryTaxGroupService->saveFromPayload($payload);

            return new JsonResponse(
                $this->hydratorService->result([$this->deliveryTaxGroupService->getSnapshot($group)]),
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

    #[Route('/delivery_tax_groups/{id}/companies/associate', name: 'delivery_tax_groups_associate_companies', methods: ['POST'])]
    public function associateCompanies(Request $request, string $id): JsonResponse
    {
        try {
            $payload = $this->readPayload($request);
            $group = $this->deliveryTaxGroupService->getAccessibleGroup($id);
            if (!$group instanceof DeliveryTaxGroup) {
                throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Tabela de entrega nao encontrada.');
            }

            $updatedGroup = $this->deliveryTaxGroupService->associateCompanies(
                $group,
                $payload['companyIds'] ?? $payload['companies'] ?? []
            );

            return new JsonResponse(
                $this->hydratorService->result([$this->deliveryTaxGroupService->getSnapshot($updatedGroup)]),
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

    #[Route('/delivery_tax_groups/{id}/companies/{companyId}', name: 'delivery_tax_groups_toggle_company', methods: ['PATCH', 'POST'])]
    public function toggleCompany(Request $request, string $id, string $companyId): JsonResponse
    {
        try {
            $payload = $this->readPayload($request);
            $group = $this->deliveryTaxGroupService->getAccessibleGroup($id);
            if (!$group instanceof DeliveryTaxGroup) {
                throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Tabela de entrega nao encontrada.');
            }

            $company = $this->entityManager->getRepository(People::class)->find(
                preg_replace('/\D+/', '', $companyId)
            );
            if (!$company instanceof People) {
                throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Empresa nao encontrada.');
            }

            $link = $this->deliveryTaxGroupService->toggleCompanyActivation(
                $group,
                $company,
                (bool) ($payload['enabled'] ?? $payload['active'] ?? false)
            );

            return new JsonResponse(
                $this->hydratorService->result([[
                    'group' => $this->deliveryTaxGroupService->getSnapshot($group),
                    'link' => $this->deliveryTaxGroupService->toCompanySnapshot($link),
                ]]),
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
