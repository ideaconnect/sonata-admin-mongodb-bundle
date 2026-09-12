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

use MongoDB\BSON\ObjectId;
use PHPUnit\Framework\Attributes\DataProvider;
use IDCT\Adminata\Filter\Model\FilterData;
use IDCT\Adminata\Form\Type\Operator\EqualOperatorType;
use IDCT\Adminata\DoctrineMongoDB\Datagrid\ProxyQuery;
use IDCT\Adminata\DoctrineMongoDB\Filter\IdFilter;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class IdFilterTest extends FilterWithQueryBuilderTestCase
{
    public function testEmpty(): void
    {
        $filter = new IdFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
        ]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::never())
            ->method('field');

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray([]));

        static::assertFalse($filter->isActive());
    }

    public function testItDoesNotApplyWithWrongObjectId(): void
    {
        $filter = new IdFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
        ]);

        $queryBuilder = $this->getQueryBuilder();

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray(['value' => 'wrong_object_id', 'type' => null]));
        static::assertFalse($filter->isActive());
    }

    #[DataProvider('provideDefaultTypeIsEqualsCases')]
    public function testDefaultTypeIsEquals(?int $type): void
    {
        $filter = new IdFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
        ]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('equals')
            ->with(new ObjectId('507f1f77bcf86cd799439011'));

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray(['value' => '507f1f77bcf86cd799439011', 'type' => $type]));

        static::assertTrue($filter->isActive());
    }

    /**
     * @phpstan-return iterable<array{int|null}>
     */
    public static function provideDefaultTypeIsEqualsCases(): iterable
    {
        yield 'default type' => [null];
        yield 'equals type' => [EqualOperatorType::TYPE_EQUAL];
    }

    public function testNotEquals(): void
    {
        $filter = new IdFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
        ]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('notEqual')
            ->with(new ObjectId('507f1f77bcf86cd799439011'));

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray([
            'value' => '507f1f77bcf86cd799439011',
            'type' => EqualOperatorType::TYPE_NOT_EQUAL,
        ]));
        static::assertTrue($filter->isActive());
    }

    public function testGetDefaultOptions(): void
    {
        $filter = new IdFilter();

        static::assertSame(
            [
                'field_type' => TextType::class,
                'operator_type' => EqualOperatorType::class,
            ],
            $filter->getDefaultOptions(),
        );
    }

    public function testGetFormOptionsExposesFieldAndOperatorMetadata(): void
    {
        $filter = new IdFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
        ]);

        $options = $filter->getFormOptions();

        static::assertSame(TextType::class, $options['field_type']);
        static::assertSame(EqualOperatorType::class, $options['operator_type']);
    }

    public function testGetFormOptionsHasExactShape(): void
    {
        $filter = new IdFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
        ]);

        static::assertSame(
            [
                'field_type' => TextType::class,
                'field_options' => [],
                'operator_type' => EqualOperatorType::class,
                'label' => null,
            ],
            $filter->getFormOptions(),
        );
    }

    public function testItIgnoresValueThatIsOnlyWhitespace(): void
    {
        $filter = new IdFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
        ]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->expects(static::never())->method('field');

        $filter->apply(new ProxyQuery($queryBuilder), FilterData::fromArray(['value' => '   ']));

        static::assertFalse($filter->isActive());
    }

    public function testItTrimsSurroundingWhitespaceOnValidObjectId(): void
    {
        // Dropping the trim() would leave the padded string in place, the
        // ObjectId constructor would throw, and the filter would silently
        // become inactive — exactly the regression we want to catch.
        $filter = new IdFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
        ]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('equals')
            ->with(new ObjectId('507f1f77bcf86cd799439011'));

        $filter->apply(
            new ProxyQuery($queryBuilder),
            FilterData::fromArray(['value' => '  507f1f77bcf86cd799439011  ']),
        );

        static::assertTrue($filter->isActive());
    }
}
