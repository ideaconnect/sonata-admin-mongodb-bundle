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

use Sonata\AdminBundle\Filter\Model\FilterData;
use Sonata\DoctrineMongoDBAdminBundle\Datagrid\ProxyQuery;
use Sonata\DoctrineMongoDBAdminBundle\Filter\DateFilter;
use Symfony\Component\Form\Extension\Core\Type\DateType;

final class DateFilterTest extends FilterWithQueryBuilderTestCase
{
    public function testEmpty(): void
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

    public function testGetType(): void
    {
        static::assertSame(DateType::class, $this->createFilter()->getFieldType());
    }

    public function testFilterRecordsWholeDay(): void
    {
        $filter = $this->createFilter();

        $date = new \DateTime('2016-08-31 23:59:59.0-03:00');
        $datePlusOneDay = new \DateTime('2016-09-01 23:59:59.0-03:00');

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('gte')
            ->with($date);

        $queryBuilder
            ->expects(static::once())
            ->method('lt')
            ->with($datePlusOneDay);

        // Locks the post-whole-day `return;`: dropping it would chain a third
        // applyType(equals) call below using $value.
        $queryBuilder->expects(static::never())->method('equals');

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray(['value' => $date]));

        static::assertTrue($filter->isActive());
    }

    public function testFilterWholeDayDoesNotMutateInputDateTime(): void
    {
        // CloneRemoval on the `$endValue = clone $value` line would let the
        // +1 day step mutate the caller's DateTime, since the gte/lt calls
        // happen after the mutation. Pin the input timestamp to lock the
        // clone barrier.
        $filter = $this->createFilter();

        $date = new \DateTime('2016-08-31 12:00:00');
        $originalTimestamp = $date->getTimestamp();

        $filter->apply(new ProxyQuery($this->getQueryBuilder()), FilterData::fromArray(['value' => $date]));

        static::assertSame(
            $originalTimestamp,
            $date->getTimestamp(),
            'Whole-day filter must not mutate the caller\'s DateTime.',
        );
    }

    public function testFilterRecordsWholeDayWithImmutableDate(): void
    {
        $filter = $this->createFilter();

        $date = new \DateTimeImmutable('2016-08-31 23:59:59.0-03:00');
        $datePlusOneDay = $date->add(new \DateInterval('P1D'));

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('gte')
            ->with($date);

        $queryBuilder
            ->expects(static::once())
            ->method('lt')
            ->with($datePlusOneDay);

        $filter->apply(new ProxyQuery($queryBuilder), FilterData::fromArray(['value' => $date]));

        static::assertTrue($filter->isActive());
    }

    public function testFilterIsInactiveWhenValueIsNotADate(): void
    {
        $filter = $this->createFilter();

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->expects(static::never())->method('field');

        $filter->apply(new ProxyQuery($queryBuilder), FilterData::fromArray(['value' => 'not-a-date']));

        static::assertFalse($filter->isActive());
    }

    public function testFilterThrowsForUnknownOperatorType(): void
    {
        $filter = $this->createFilter();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not valid');
        // The supported-types list must contain the int *keys* of the operator
        // map (TYPE_GREATER_EQUAL = 1, TYPE_EQUAL = 3, …) — not the Mongo
        // operator strings ('equals', 'gte', …) the keys map to.
        $this->expectExceptionMessageMatches('/"3", "1", "2", "4", "5"/');

        $filter->apply(
            new ProxyQuery($this->getQueryBuilder()),
            FilterData::fromArray([
                'type' => 9_999_999,
                'value' => new \DateTime('2020-01-01'),
            ]),
        );
    }

    private function createFilter(): DateFilter
    {
        $filter = new DateFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
        ]);

        return $filter;
    }
}
