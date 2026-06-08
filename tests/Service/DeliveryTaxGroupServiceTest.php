<?php

/*
 * Contract imported from MODOS_OPERACAO.md
 * - DELIVERY rate reads must be scoped by company and courier through DeliveryTaxGroupService::securityFilter().
 * - The same QueryBuilder can be filtered more than once by ApiPlatform, so the domain filter must be idempotent.
 * - Company joins and predicates must not be duplicated when the filter is reapplied on the same query.
 */

namespace ControleOnline\Logistic\Tests\Service;

use ControleOnline\Entity\DeliveryTaxGroup;
use ControleOnline\Entity\People;
use ControleOnline\Service\DeliveryTaxGroupService;
use ControleOnline\Service\PeopleRoleService;
use ControleOnline\Service\PeopleService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AllowMockObjectsWithoutExpectations]
class DeliveryTaxGroupServiceTest extends TestCase
{
    public function testSecurityFilterIsIdempotentOnSameQueryBuilder(): void
    {
        $courier = $this->createMock(People::class);
        $courier->method('getId')->willReturn(7);

        $company = $this->createMock(People::class);
        $company->method('getId')->willReturn(3);

        $peopleService = $this->createMock(PeopleService::class);
        $peopleService->expects(self::once())
            ->method('getMyPeople')
            ->willReturn($courier);
        $peopleService->expects(self::once())
            ->method('getMyCompanies')
            ->willReturn([$company]);

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/delivery_tax_groups', 'GET', [
            'company' => '/people/3',
        ]));

        $service = new DeliveryTaxGroupService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(TokenStorageInterface::class),
            $peopleService,
            $this->createStub(PeopleRoleService::class),
            $requestStack
        );

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getRootAliases',
                'getDQLPart',
                'distinct',
                'leftJoin',
                'andWhere',
                'setParameter',
                'expr',
            ])
            ->getMock();

        $joins = [];
        $whereClauses = [];
        $parameters = [];

        $queryBuilder->method('getRootAliases')->willReturn(['deliveryTaxGroup']);
        $queryBuilder->expects(self::exactly(2))
            ->method('getDQLPart')
            ->willReturnCallback(function (string $part) use (&$joins): array {
                return $part === 'join' ? $joins : [];
            });
        $queryBuilder->expects(self::once())->method('distinct')->willReturnSelf();
        $queryBuilder->expects(self::once())
            ->method('leftJoin')
            ->with('deliveryTaxGroup.companies', 'deliveryTaxGroupCompanyFilter')
            ->willReturnCallback(function (string $join, string $alias) use (&$joins, $queryBuilder) {
                $joins['deliveryTaxGroup'][$alias] = [
                    'alias' => $alias,
                    'join' => $join,
                ];

                return $queryBuilder;
            });
        $queryBuilder->expects(self::exactly(2))
            ->method('andWhere')
            ->willReturnCallback(function (mixed $expression) use (&$whereClauses, $queryBuilder) {
                $whereClauses[] = (string) $expression;

                return $queryBuilder;
            });
        $queryBuilder->expects(self::exactly(3))
            ->method('setParameter')
            ->willReturnCallback(function (string $name, mixed $value) use (&$parameters, $queryBuilder) {
                $parameters[$name] = $value;

                return $queryBuilder;
            });
        $queryBuilder->expects(self::once())
            ->method('expr')
            ->willReturn(new Expr());

        $service->securityFilter($queryBuilder, DeliveryTaxGroup::class, 'api_platform', 'deliveryTaxGroup');
        $service->securityFilter($queryBuilder, DeliveryTaxGroup::class, 'api_platform', 'deliveryTaxGroup');

        self::assertSame([
            'deliveryTaxGroup.courier = :delivery_current_people OR deliveryTaxGroupCompanyFilter.company IN (:delivery_company_ids)',
            'deliveryTaxGroupCompanyFilter.company = :delivery_company_filter',
        ], $whereClauses);
        self::assertSame(7, $parameters['delivery_current_people']);
        self::assertSame([3], $parameters['delivery_company_ids']);
        self::assertSame(3, $parameters['delivery_company_filter']);
        self::assertCount(1, $joins['deliveryTaxGroup']);
    }
}
