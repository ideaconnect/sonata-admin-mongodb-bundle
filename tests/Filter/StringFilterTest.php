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

use Doctrine\ODM\MongoDB\Query\Builder;
use IDCT\Adminata\DoctrineMongoDB\Datagrid\ProxyQuery;
use IDCT\Adminata\DoctrineMongoDB\Filter\StringFilter;
use IDCT\Adminata\Filter\FilterInterface;
use IDCT\Adminata\Filter\Model\FilterData;
use IDCT\Adminata\Form\Type\Operator\ContainsOperatorType;
use IDCT\Adminata\Form\Type\Operator\StringOperatorType;
use MongoDB\BSON\Regex;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class StringFilterTest extends FilterWithQueryBuilderTestCase
{
    public function testSearchEnabled(): void
    {
        $filter = new StringFilter();
        $filter->initialize('field_name', []);
        static::assertTrue($filter->isSearchEnabled());

        $filter = new StringFilter();
        $filter->initialize('field_name', ['global_search' => false]);
        static::assertFalse($filter->isSearchEnabled());
    }

    public function testEmpty(): void
    {
        $filter = new StringFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
            'field_options' => ['class' => 'FooBar'],
        ]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::never())
            ->method('field');

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray([]));

        static::assertFalse($filter->isActive());
    }

    public function testDefaultTypeIsContains(): void
    {
        $filter = new StringFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
            'format' => '%s',
        ]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('equals')
            ->with(new Regex('asd', 'i'));

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray(['value' => 'asd', 'type' => null]));
        static::assertTrue($filter->isActive());
    }

    /**
     * @param non-empty-string $method
     */
    #[DataProvider('provideContainsCases')]
    public function testContains(string $method, int $type, mixed $value): void
    {
        $filter = new StringFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
            'format' => '%s',
        ]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method($method)
            ->with($value);

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray(['value' => 'asd', 'type' => $type]));
        static::assertTrue($filter->isActive());
    }

    /**
     * @phpstan-return iterable<array{non-empty-string, int, mixed}>
     */
    public static function provideContainsCases(): iterable
    {
        yield ['equals', ContainsOperatorType::TYPE_CONTAINS, new Regex('asd', 'i')];
        yield ['equals', ContainsOperatorType::TYPE_EQUAL, 'asd'];
        yield ['not', ContainsOperatorType::TYPE_NOT_CONTAINS, new Regex('asd', 'i')];
    }

    public function testNotContains(): void
    {
        $filter = new StringFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
            'format' => '%s',
        ]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('not')
            ->with(new Regex('asd', 'i'));

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray(['value' => 'asd', 'type' => ContainsOperatorType::TYPE_NOT_CONTAINS]));
        static::assertTrue($filter->isActive());
    }

    public function testEquals(): void
    {
        $filter = new StringFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
            'format' => '%s',
        ]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('equals')
            ->with('asd');

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray(['value' => 'asd', 'type' => ContainsOperatorType::TYPE_EQUAL]));
        static::assertTrue($filter->isActive());
    }

    public function testEqualsWithValidParentAssociationMappings(): void
    {
        $filter = new StringFilter();
        $filter->initialize('field_name', [
            'format' => '%s',
            'field_name' => 'field_name',
            'parent_association_mappings' => [
                [
                    'fieldName' => 'association_mapping',
                ],
                [
                    'fieldName' => 'sub_association_mapping',
                ],
                [
                    'fieldName' => 'sub_sub_association_mapping',
                ],
            ],
        ]);

        $queryBuilder = $this->createMock(Builder::class);
        $queryBuilder
            ->method('field')
            ->with('field_name')
            ->willReturnSelf();

        $queryBuilder
            ->expects(static::once())
            ->method('equals')
            ->with('asd');

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray(['type' => ContainsOperatorType::TYPE_EQUAL, 'value' => 'asd']));
        static::assertTrue($filter->isActive());
    }

    public function testOr(): void
    {
        $filter = new StringFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
            'format' => '%s',
        ]);
        $filter->setCondition(FilterInterface::CONDITION_OR);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->expects(static::once())->method('addOr');
        $builder = new ProxyQuery($queryBuilder);
        $filter->apply($builder, FilterData::fromArray(['value' => 'asd', 'type' => ContainsOperatorType::TYPE_CONTAINS]));
        static::assertTrue($filter->isActive());

        $filter->setCondition(FilterInterface::CONDITION_AND);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->expects(static::never())->method('addOr');
        $builder = new ProxyQuery($queryBuilder);
        $filter->apply($builder, FilterData::fromArray(['value' => 'asd', 'type' => ContainsOperatorType::TYPE_CONTAINS]));
        static::assertTrue($filter->isActive());
    }

    public function testDefaultValues(): void
    {
        $filter = new StringFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
            'format' => '%s',
        ]);

        static::assertSame(TextType::class, $filter->getFieldType());
    }

    public function testGetFormOptionsExposesFieldAndOperatorMetadata(): void
    {
        $filter = new StringFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
        ]);

        $options = $filter->getFormOptions();

        static::assertSame(TextType::class, $options['field_type']);
        static::assertSame(ContainsOperatorType::class, $options['operator_type']);
    }

    public function testGetDefaultOptions(): void
    {
        static::assertSame(
            [
                'field_type' => TextType::class,
                'global_search' => true,
                'case_sensitive' => false,
            ],
            new StringFilter()->getDefaultOptions(),
        );
    }

    public function testGetFormOptionsHasExactShape(): void
    {
        $filter = new StringFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
        ]);

        static::assertSame(
            [
                'field_type' => TextType::class,
                'field_options' => [],
                'label' => null,
                'operator_type' => ContainsOperatorType::class,
            ],
            $filter->getFormOptions(),
        );
    }

    public function testFilterIsInactiveForWhitespaceValue(): void
    {
        $filter = new StringFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
            'format' => '%s',
        ]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->expects(static::never())->method('field');

        $filter->apply(new ProxyQuery($queryBuilder), FilterData::fromArray(['value' => '   ']));

        static::assertFalse($filter->isActive());
    }

    /**
     * @phpstan-return iterable<string, array{0: string, 1: string}>
     */
    public static function provideRegexMetacharacterCases(): iterable
    {
        yield 'dot is escaped' => ['foo.bar', 'foo\\.bar'];
        yield 'plus is escaped' => ['a+b', 'a\\+b'];
        yield 'wildcard set is escaped' => ['[a-z]+', '\\[a\\-z\\]\\+'];
        yield 'redos-shaped input is literalized' => ['(a+)+b', '\\(a\\+\\)\\+b'];
    }

    #[DataProvider('provideRegexMetacharacterCases')]
    public function testContainsEscapesRegexMetacharacters(string $input, string $expectedPattern): void
    {
        $filter = new StringFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
            'format' => '%s',
        ]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('equals')
            ->with(new Regex($expectedPattern, 'i'));

        $filter->apply(
            new ProxyQuery($queryBuilder),
            FilterData::fromArray(['value' => $input, 'type' => ContainsOperatorType::TYPE_CONTAINS]),
        );

        static::assertTrue($filter->isActive());
    }

    #[DataProvider('provideRegexMetacharacterCases')]
    public function testNotContainsEscapesRegexMetacharacters(string $input, string $expectedPattern): void
    {
        $filter = new StringFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
            'format' => '%s',
        ]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('not')
            ->with(new Regex($expectedPattern, 'i'));

        $filter->apply(
            new ProxyQuery($queryBuilder),
            FilterData::fromArray(['value' => $input, 'type' => ContainsOperatorType::TYPE_NOT_CONTAINS]),
        );

        static::assertTrue($filter->isActive());
    }

    public function testStartsWithUsesAnchoredRegex(): void
    {
        $filter = new StringFilter();
        $filter->initialize('field_name', ['field_name' => self::DEFAULT_FIELD_NAME]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('equals')
            ->with(new Regex('^foo\\.', 'i'));

        $filter->apply(
            new ProxyQuery($queryBuilder),
            FilterData::fromArray(['value' => 'foo.', 'type' => StringOperatorType::TYPE_STARTS_WITH]),
        );

        static::assertTrue($filter->isActive());
    }

    public function testEndsWithUsesAnchoredRegex(): void
    {
        $filter = new StringFilter();
        $filter->initialize('field_name', ['field_name' => self::DEFAULT_FIELD_NAME]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('equals')
            ->with(new Regex('\\.bar$', 'i'));

        $filter->apply(
            new ProxyQuery($queryBuilder),
            FilterData::fromArray(['value' => '.bar', 'type' => StringOperatorType::TYPE_ENDS_WITH]),
        );

        static::assertTrue($filter->isActive());
    }

    public function testNotEqualUsesPlainNotEqual(): void
    {
        $filter = new StringFilter();
        $filter->initialize('field_name', ['field_name' => self::DEFAULT_FIELD_NAME]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('notEqual')
            ->with('asd');

        $filter->apply(
            new ProxyQuery($queryBuilder),
            FilterData::fromArray(['value' => 'asd', 'type' => StringOperatorType::TYPE_NOT_EQUAL]),
        );

        static::assertTrue($filter->isActive());
    }

    public function testCaseSensitiveOptionRemovesIFlag(): void
    {
        $filter = new StringFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
            'case_sensitive' => true,
        ]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method('equals')
            ->with(new Regex('asd', ''));

        $filter->apply(
            new ProxyQuery($queryBuilder),
            FilterData::fromArray(['value' => 'asd', 'type' => ContainsOperatorType::TYPE_CONTAINS]),
        );

        static::assertTrue($filter->isActive());
    }

    /**
     * Locks the case_sensitive flag's effect across every regex-using operator
     * (T1). CONTAINS is already covered above; this extends to the three
     * anchored / negated variants so they don't silently regress.
     *
     * @phpstan-return iterable<string, array{int, string, non-empty-string}>
     */
    public static function provideCaseSensitiveOptionRemovesIFlagAcrossOperatorsCases(): iterable
    {
        // [operator, expected Regex pattern, expected Mongo builder method].
        yield 'NOT_CONTAINS' => [ContainsOperatorType::TYPE_NOT_CONTAINS, 'asd', 'not'];
        yield 'STARTS_WITH' => [StringOperatorType::TYPE_STARTS_WITH, '^asd', 'equals'];
        yield 'ENDS_WITH' => [StringOperatorType::TYPE_ENDS_WITH, 'asd$', 'equals'];
    }

    /**
     * @param non-empty-string $builderMethod
     */
    #[DataProvider('provideCaseSensitiveOptionRemovesIFlagAcrossOperatorsCases')]
    public function testCaseSensitiveOptionRemovesIFlagAcrossOperators(
        int $type,
        string $expectedPattern,
        string $builderMethod,
    ): void {
        $filter = new StringFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
            'case_sensitive' => true,
        ]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->expects(static::once())
            ->method($builderMethod)
            ->with(new Regex($expectedPattern, ''));

        $filter->apply(
            new ProxyQuery($queryBuilder),
            FilterData::fromArray(['value' => 'asd', 'type' => $type]),
        );

        static::assertTrue($filter->isActive());
    }

    /**
     * Regression for R1: an unknown operator int used to leave the builder
     * untouched while still flipping isActive() to true. The filter now
     * short-circuits without ever calling field().
     */
    public function testFilterIsInactiveForUnknownOperatorType(): void
    {
        $filter = new StringFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
        ]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->expects(static::never())->method('field');

        $filter->apply(
            new ProxyQuery($queryBuilder),
            FilterData::fromArray(['value' => 'asd', 'type' => 9_999_999]),
        );

        static::assertFalse($filter->isActive());
    }

    /**
     * Regression for R2: a non-scalar submitted value used to TypeError on the
     * `(string)` cast. It now skips the filter silently.
     *
     * @phpstan-return iterable<string, array{mixed}>
     */
    public static function provideFilterIsInactiveForNonScalarValueCases(): iterable
    {
        yield 'array' => [['nested']];
        yield 'object' => [new \stdClass()];
    }

    #[DataProvider('provideFilterIsInactiveForNonScalarValueCases')]
    public function testFilterIsInactiveForNonScalarValue(mixed $value): void
    {
        $filter = new StringFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
        ]);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->expects(static::never())->method('field');

        $filter->apply(
            new ProxyQuery($queryBuilder),
            FilterData::fromArray(['value' => $value]),
        );

        static::assertFalse($filter->isActive());
    }
}
