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

namespace IDCT\Adminata\DoctrineMongoDB\Tests\Filter;

use PHPUnit\Framework\Attributes\DataProvider;
use IDCT\Adminata\Filter\Model\FilterData;
use IDCT\Adminata\Form\Type\DateTimeRangeType;
use IDCT\Adminata\Form\Type\Operator\DateRangeOperatorType;
use IDCT\Adminata\DoctrineMongoDB\Datagrid\ProxyQuery;
use IDCT\Adminata\DoctrineMongoDB\Filter\DateTimeRangeFilter;

final class DateTimeRangeFilterTest extends FilterWithQueryBuilderTestCase
{
    /**
     * @phpstan-param array{start?: mixed, end?: mixed} $value
     */
    #[DataProvider('provideEmptyCases')]
    public function testEmpty(array $value): void
    {
        $filter = $this->createFilter();

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::never())
            ->method('field');

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray(['value' => $value]));

        static::assertFalse($filter->isActive());
    }

    /**
     * @phpstan-return iterable<array<array{start?: mixed, end?: mixed}>>
     */
    public static function provideEmptyCases(): iterable
    {
        yield [[]];
        yield [['end' => new \DateTime()]];
        yield [['start' => new \DateTime()]];
    }

    public function testGetType(): void
    {
        static::assertSame(DateTimeRangeType::class, $this->createFilter()->getFieldType());
    }

    #[DataProvider('provideFilterBetweenCases')]
    public function testFilterBetween(?int $type): void
    {
        $filter = $this->createFilter();

        $startDate = new \DateTimeImmutable();
        $endDate = new \DateTimeImmutable('+1 day');

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('gte')
            ->with($startDate);

        $queryBuilder
            ->expects(static::once())
            ->method('lte')
            ->with($endDate);

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray([
            'type' => $type,
            'value' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
        ]));

        static::assertTrue($filter->isActive());
    }

    /**
     * @phpstan-return iterable<array{int|null}>
     */
    public static function provideFilterBetweenCases(): iterable
    {
        yield 'default' => [null];
        yield 'between' => [DateRangeOperatorType::TYPE_BETWEEN];
    }

    public function testFilterNotBetween(): void
    {
        $filter = $this->createFilter();

        $startDate = new \DateTimeImmutable();
        $endDate = new \DateTimeImmutable('+1 day');

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('lt')
            ->with($startDate);

        $queryBuilder
            ->expects(static::once())
            ->method('gt')
            ->with($endDate);

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray([
            'type' => DateRangeOperatorType::TYPE_NOT_BETWEEN,
            'value' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
        ]));

        static::assertTrue($filter->isActive());
    }

    private function createFilter(): DateTimeRangeFilter
    {
        $filter = new DateTimeRangeFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
        ]);

        return $filter;
    }
}
