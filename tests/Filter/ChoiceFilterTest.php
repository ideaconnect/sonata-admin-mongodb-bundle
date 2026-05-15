<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\DoctrineMongoDBAdminBundle\Tests\Filter;

use PHPUnit\Framework\Attributes\DataProvider;
use Sonata\AdminBundle\Filter\Model\FilterData;
use Sonata\AdminBundle\Form\Type\Operator\ContainsOperatorType;
use Sonata\AdminBundle\Form\Type\Operator\EqualOperatorType;
use Sonata\DoctrineMongoDBAdminBundle\Datagrid\ProxyQuery;
use Sonata\DoctrineMongoDBAdminBundle\Filter\ChoiceFilter;

final class ChoiceFilterTest extends FilterWithQueryBuilderTestCase
{
    public function testFilterEmpty(): void
    {
        $filter = $this->createFilter();

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::never())
            ->method('field');

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray([]));

        static::assertFalse($filter->isActive());
    }

    public function testFilterArray(): void
    {
        $filter = $this->createFilter();

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('in')
            ->with(['1', '2']);

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray(['type' => ContainsOperatorType::TYPE_CONTAINS, 'value' => ['1', '2']]));

        static::assertTrue($filter->isActive());
    }

    public function testFilterScalar(): void
    {
        $filter = $this->createFilter();

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('equals')
            ->with('1');

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray(['type' => ContainsOperatorType::TYPE_CONTAINS, 'value' => '1']));

        static::assertTrue($filter->isActive());
    }

    public function testFilterZero(): void
    {
        $filter = $this->createFilter();

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('equals')
            ->with('0');

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray(['type' => ContainsOperatorType::TYPE_CONTAINS, 'value' => 0]));

        static::assertTrue($filter->isActive());
    }

    public function testFilterArrayNotEqualUsesNotIn(): void
    {
        $filter = $this->createFilter();

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('notIn')
            ->with(['1', '2']);

        $filter->apply(
            new ProxyQuery($queryBuilder),
            FilterData::fromArray(['type' => EqualOperatorType::TYPE_NOT_EQUAL, 'value' => ['1', '2']]),
        );

        static::assertTrue($filter->isActive());
    }

    public function testFilterScalarNotEqualUsesNotEqual(): void
    {
        $filter = $this->createFilter();

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('notEqual')
            ->with('foo');

        $filter->apply(
            new ProxyQuery($queryBuilder),
            FilterData::fromArray(['type' => EqualOperatorType::TYPE_NOT_EQUAL, 'value' => 'foo']),
        );

        static::assertTrue($filter->isActive());
    }

    public function testFilterIsInactiveForEmptyArray(): void
    {
        $filter = $this->createFilter();

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->expects(static::never())->method('field');

        $filter->apply(new ProxyQuery($queryBuilder), FilterData::fromArray(['value' => []]));

        static::assertFalse($filter->isActive());
    }

    /**
     * @phpstan-return iterable<array{mixed}>
     */
    public static function provideFilterIsInactiveForFalsyScalarCases(): iterable
    {
        yield 'empty string' => [''];
        yield 'false' => [false];
    }

    #[DataProvider('provideFilterIsInactiveForFalsyScalarCases')]
    public function testFilterIsInactiveForFalsyScalar(mixed $value): void
    {
        $filter = $this->createFilter();

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->expects(static::never())->method('field');

        $filter->apply(new ProxyQuery($queryBuilder), FilterData::fromArray(['value' => $value]));

        static::assertFalse($filter->isActive());
    }

    public function testGetFormOptionsExposesFieldAndOperatorMetadata(): void
    {
        $filter = $this->createFilter();

        $options = $filter->getFormOptions();

        static::assertSame(EqualOperatorType::class, $options['operator_type']);
        static::assertSame(['class' => 'FooBar'], $options['field_options']);
    }

    public function testGetDefaultOptions(): void
    {
        static::assertSame(
            [
                'operator_type' => EqualOperatorType::class,
                'operator_options' => [],
            ],
            new ChoiceFilter()->getDefaultOptions(),
        );
    }

    private function createFilter(): ChoiceFilter
    {
        $filter = new ChoiceFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
            'field_options' => ['class' => 'FooBar'],
        ]);

        return $filter;
    }
}
