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
use Sonata\DoctrineMongoDBAdminBundle\Datagrid\ProxyQuery;
use Sonata\DoctrineMongoDBAdminBundle\Filter\BooleanFilter;
use Sonata\Form\Type\BooleanType;

final class BooleanFilterTest extends FilterWithQueryBuilderTestCase
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

    #[DataProvider('provideFilterScalarCases')]
    public function testFilterScalar(bool $equalsReturnValue, int $value): void
    {
        $filter = $this->createFilter();

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('equals')
            ->with($equalsReturnValue);

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray(['type' => null, 'value' => $value]));

        static::assertTrue($filter->isActive());
    }

    /**
     * @phpstan-return iterable<array{bool, int}>
     */
    public static function provideFilterScalarCases(): iterable
    {
        yield [false, BooleanType::TYPE_NO];
        yield [true, BooleanType::TYPE_YES];
    }

    public function testFilterArray(): void
    {
        $filter = $this->createFilter();

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('in')
            ->with([false]);

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray(['type' => null, 'value' => [BooleanType::TYPE_NO]]));

        static::assertTrue($filter->isActive());
    }

    public function testDefaultValues(): void
    {
        $filter = $this->createFilter();

        static::assertSame(BooleanType::class, $filter->getFieldType());
    }

    public function testGetFormOptionsExposesFieldAndOperatorMetadata(): void
    {
        $filter = $this->createFilter();

        $options = $filter->getFormOptions();

        static::assertSame(BooleanType::class, $options['field_type']);
        static::assertSame(['class' => 'FooBar'], $options['field_options']);
        static::assertSame([], $options['operator_options']);
    }

    public function testFilterIsInactiveForUnknownScalarValue(): void
    {
        $filter = $this->createFilter();

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->expects(static::never())->method('field');

        $filter->apply(
            new ProxyQuery($queryBuilder),
            FilterData::fromArray(['type' => null, 'value' => 999]),
        );

        static::assertFalse($filter->isActive());
    }

    public function testFilterIsInactiveWhenArrayContainsNoValidValues(): void
    {
        $filter = $this->createFilter();

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->expects(static::never())->method('field');

        $filter->apply(
            new ProxyQuery($queryBuilder),
            FilterData::fromArray(['type' => null, 'value' => [999, 'bogus']]),
        );

        static::assertFalse($filter->isActive());
    }

    public function testFilterArrayDropsUnknownEntriesAndKeepsValidOnes(): void
    {
        $filter = $this->createFilter();

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('in')
            ->with([true]);

        $filter->apply(
            new ProxyQuery($queryBuilder),
            FilterData::fromArray(['type' => null, 'value' => [BooleanType::TYPE_YES, 999]]),
        );

        static::assertTrue($filter->isActive());
    }

    private function createFilter(): BooleanFilter
    {
        $filter = new BooleanFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
            'field_options' => ['class' => 'FooBar'],
        ]);

        return $filter;
    }
}
