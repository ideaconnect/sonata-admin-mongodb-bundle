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
use Sonata\AdminBundle\Form\Type\BooleanType;
use Sonata\DoctrineMongoDBAdminBundle\Datagrid\ProxyQuery;
use Sonata\DoctrineMongoDBAdminBundle\Filter\EmptyFilter;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

final class EmptyFilterTest extends FilterWithQueryBuilderTestCase
{
    public function testGetFormOptionsExposesHiddenOperator(): void
    {
        $filter = new EmptyFilter();
        $filter->initialize('field_name', ['field_name' => self::DEFAULT_FIELD_NAME]);

        $options = $filter->getFormOptions();

        static::assertSame(BooleanType::class, $options['field_type']);
        static::assertSame(HiddenType::class, $options['operator_type']);
    }

    public function testGetDefaultOptions(): void
    {
        static::assertSame(
            [
                'field_type' => BooleanType::class,
                'operator_type' => HiddenType::class,
                'operator_options' => [],
            ],
            new EmptyFilter()->getDefaultOptions(),
        );
    }

    public function testGetFormOptionsHasExactShape(): void
    {
        $filter = new EmptyFilter();
        $filter->initialize('field_name', ['field_name' => self::DEFAULT_FIELD_NAME]);

        static::assertSame(
            [
                'field_type' => BooleanType::class,
                'field_options' => [],
                'operator_type' => HiddenType::class,
                'operator_options' => [],
                'label' => null,
            ],
            $filter->getFormOptions(),
        );
    }

    public function testFilterIsInactiveWithoutValue(): void
    {
        $filter = new EmptyFilter();
        $filter->initialize('field_name', ['field_name' => self::DEFAULT_FIELD_NAME]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->expects(static::never())->method('field');

        $filter->apply(new ProxyQuery($queryBuilder), FilterData::fromArray([]));

        static::assertFalse($filter->isActive());
    }

    public function testFilterIsInactiveForUnsupportedValue(): void
    {
        $filter = new EmptyFilter();
        $filter->initialize('field_name', ['field_name' => self::DEFAULT_FIELD_NAME]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->expects(static::never())->method('field');

        // Anything outside the BooleanType TYPE_YES/TYPE_NO ints must be
        // ignored — the filter only understands the choice form's output.
        $filter->apply(new ProxyQuery($queryBuilder), FilterData::fromArray(['value' => 'maybe']));

        static::assertFalse($filter->isActive());
    }

    public function testFilterYesMatchesNullOrMissing(): void
    {
        $filter = new EmptyFilter();
        $filter->initialize('field_name', ['field_name' => self::DEFAULT_FIELD_NAME]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('equals')
            ->with(null);

        $filter->apply(
            new ProxyQuery($queryBuilder),
            FilterData::fromArray(['value' => BooleanType::TYPE_YES]),
        );

        static::assertTrue($filter->isActive());
    }

    public function testFilterNoMatchesNonNull(): void
    {
        $filter = new EmptyFilter();
        $filter->initialize('field_name', ['field_name' => self::DEFAULT_FIELD_NAME]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('notEqual')
            ->with(null);

        $filter->apply(
            new ProxyQuery($queryBuilder),
            FilterData::fromArray(['value' => BooleanType::TYPE_NO]),
        );

        static::assertTrue($filter->isActive());
    }
}
