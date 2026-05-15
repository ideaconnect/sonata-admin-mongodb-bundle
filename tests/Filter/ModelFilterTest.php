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

use Doctrine\Bundle\MongoDBBundle\Form\Type\DocumentType;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\Query\Builder;
use MongoDB\BSON\ObjectId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sonata\AdminBundle\Filter\Model\FilterData;
use Sonata\AdminBundle\Form\Type\Operator\EqualOperatorType;
use Sonata\DoctrineMongoDBAdminBundle\Datagrid\ProxyQuery;
use Sonata\DoctrineMongoDBAdminBundle\Filter\ModelFilter;

/**
 * @psalm-suppress ClassMustBeFinal
 */
class DocumentStub
{
    private ObjectId $id;

    public function __construct()
    {
        $this->id = new ObjectId();
    }

    public function getId(): string
    {
        return (string) $this->id;
    }
}

final class ModelFilterTest extends TestCase
{
    public function testFilterEmpty(): void
    {
        $filter = new ModelFilter();
        $filter->initialize('field_name', [
            'field_name' => 'field',
            'field_options' => ['class' => 'FooBar'],
        ]);

        $queryBuilder = $this->createMock(Builder::class);
        $queryBuilder
            ->expects(static::never())
            ->method('field');

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray([]));

        static::assertFalse($filter->isActive());
    }

    public function testFilterArray(): void
    {
        $filter = new ModelFilter();
        $filter->initialize('field_name', [
            'field_name' => 'field',
            'field_options' => [
                'class' => 'FooBar',
            ],
            'field_mapping' => [
                'type' => 'collection',
            ],
        ]);

        $queryBuilder = $this->createMock(Builder::class);
        $queryBuilder
            ->expects(static::once())
            ->method('field')
            ->with('field._id')
            ->willReturnSelf();

        $oneDocument = new DocumentStub();
        $otherDocument = new DocumentStub();

        $queryBuilder
            ->expects(static::once())
            ->method('in')
            ->with([new ObjectId($oneDocument->getId()), new ObjectId($otherDocument->getId())]);

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray([
            'type' => EqualOperatorType::TYPE_EQUAL,
            'value' => [$oneDocument, $otherDocument],
        ]));

        static::assertTrue($filter->isActive());
    }

    /**
     * Regression for B2: handleScalar used to call ->getId() on the value
     * unconditionally, which fatals when the submitted value is not an object
     * (e.g. null, a primitive, or a malformed payload).
     */
    public function testFilterScalarIgnoresNonObjectValue(): void
    {
        $filter = new ModelFilter();
        $filter->initialize('field_name', [
            'field_name' => 'field',
            'field_options' => ['class' => 'FooBar'],
            'field_mapping' => ['type' => 'string'],
        ]);

        $queryBuilder = $this->createMock(Builder::class);
        $queryBuilder->expects(static::never())->method('field');

        $builder = new ProxyQuery($queryBuilder);

        // null, scalar and an object without getId() must all be ignored.
        foreach ([null, 'some-string', 42, new \stdClass()] as $value) {
            $filter->apply($builder, FilterData::fromArray([
                'type' => EqualOperatorType::TYPE_EQUAL,
                'value' => $value,
            ]));
            static::assertFalse($filter->isActive());
        }
    }

    public function testFilterScalar(): void
    {
        $filter = new ModelFilter();
        $filter->initialize('field_name', [
            'field_name' => 'field',
            'field_options' => [
                'class' => 'FooBar',
            ],
            'field_mapping' => [
                'type' => 'string',
            ],
        ]);

        $queryBuilder = $this->createMock(Builder::class);
        $queryBuilder
            ->expects(static::once())
            ->method('field')
            ->with('field._id')
            ->willReturnSelf();

        $document1 = new DocumentStub();

        $queryBuilder
            ->expects(static::once())
            ->method('equals')
            ->with(new ObjectId($document1->getId()));

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray([
            'type' => EqualOperatorType::TYPE_EQUAL,
            'value' => $document1,
        ]));

        static::assertTrue($filter->isActive());
    }

    /**
     * @psalm-suppress InvalidArgument
     */
    public function testAssociationWithInvalidMapping(): void
    {
        $filter = new ModelFilter();
        $filter->initialize('field_name', ['mapping_type' => 'foo', 'field_mapping' => []]);

        $builder = new ProxyQuery(static::createStub(Builder::class));

        $this->expectException(\RuntimeException::class);

        $filter->apply($builder, FilterData::fromArray(['foo']));
    }

    /**
     * @psalm-suppress InvalidArgument
     */
    public function testAssociationWithValidMappingAndEmptyFieldName(): void
    {
        $filter = new ModelFilter();
        $filter->initialize('field_name', ['mapping_type' => ClassMetadata::ONE, 'field_mapping' => []]);

        $builder = new ProxyQuery(static::createStub(Builder::class));

        $this->expectException(\RuntimeException::class);

        $filter->apply($builder, FilterData::fromArray(['foo']));
    }

    public function testAssociationWithValidMapping(): void
    {
        $filter = new ModelFilter();
        $filter->initialize('field_name', [
            'mapping_type' => ClassMetadata::ONE,
            'field_name' => 'field_name',
            'association_mapping' => [
                'fieldName' => 'association_mapping',
            ],
            'field_mapping' => [
                'fieldName' => 'association_mapping',
            ],
        ]);

        $queryBuilder = $this->createMock(Builder::class);
        $queryBuilder
            ->expects(static::once())
            ->method('field')
            ->with('field_name._id')
            ->willReturnSelf();

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray([
            'type' => EqualOperatorType::TYPE_EQUAL,
            'value' => new DocumentStub(),
        ]));

        static::assertTrue($filter->isActive());
    }

    public function testAssociationWithValidParentAssociationMappings(): void
    {
        $filter = new ModelFilter();
        $filter->initialize('field_name', [
            'mapping_type' => ClassMetadata::ONE,
            'field_name' => 'field_name',
            'parent_association_mappings' => [
                [
                    'fieldName' => 'association_mapping',
                ],
                [
                    'fieldName' => 'sub_association_mapping',
                ],
            ],
            'association_mapping' => [
                'fieldName' => 'sub_sub_association_mapping',
            ],
            'field_mapping' => [
                'fieldName' => 'sub_sub_association_mapping',
            ],
        ]);

        $queryBuilder = $this->createMock(Builder::class);
        $queryBuilder
            ->expects(static::once())
            ->method('field')
            ->with('field_name._id')
            ->willReturnSelf();

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray([
            'type' => EqualOperatorType::TYPE_EQUAL,
            'value' => new DocumentStub(),
        ]));

        static::assertTrue($filter->isActive());
    }

    #[DataProvider('provideDifferentIdentifiersBasedOnMappingCases')]
    public function testDifferentIdentifiersBasedOnMapping(string $storeAs, string $fieldIdentifier): void
    {
        $filter = new ModelFilter();
        $filter->initialize('field_name', [
            'mapping_type' => ClassMetadata::ONE,
            'field_name' => 'field_name',
            'field_mapping' => [
                'storeAs' => $storeAs,
            ],
        ]);

        $queryBuilder = $this->createMock(Builder::class);
        $queryBuilder
            ->expects(static::once())
            ->method('field')
            ->with('field_name'.$fieldIdentifier)
            ->willReturnSelf();

        $builder = new ProxyQuery($queryBuilder);

        $filter->apply($builder, FilterData::fromArray([
            'type' => EqualOperatorType::TYPE_EQUAL,
            'value' => new DocumentStub(),
        ]));

        static::assertTrue($filter->isActive());
    }

    /**
     * @phpstan-return iterable<array{string, string}>
     */
    public static function provideDifferentIdentifiersBasedOnMappingCases(): iterable
    {
        yield [ClassMetadata::REFERENCE_STORE_AS_REF, '.id'];
        yield [ClassMetadata::REFERENCE_STORE_AS_ID, ''];
        yield [ClassMetadata::REFERENCE_STORE_AS_DB_REF_WITH_DB, '.$id'];
        yield [ClassMetadata::REFERENCE_STORE_AS_DB_REF, '.$id'];
    }

    public function testDefaultValues(): void
    {
        $filter = new ModelFilter();
        $filter->initialize('field_name', [
            'field_name' => 'field_name',
        ]);

        static::assertSame(DocumentType::class, $filter->getFieldType());
        static::assertSame(EqualOperatorType::class, $filter->getOption('operator_type'));
    }

    public function testGetFormOptionsExposesFieldAndOperatorMetadata(): void
    {
        $filter = new ModelFilter();
        $filter->initialize('field_name', [
            'field_name' => 'field',
            'field_options' => ['class' => 'FooBar'],
        ]);

        $options = $filter->getFormOptions();

        static::assertSame(DocumentType::class, $options['field_type']);
        static::assertSame(['class' => 'FooBar'], $options['field_options']);
        static::assertSame(EqualOperatorType::class, $options['operator_type']);
    }

    public function testHandleScalarNotEqualUsesNotEqualOperator(): void
    {
        $filter = new ModelFilter();
        $filter->initialize('field_name', [
            'field_name' => 'field',
            'field_options' => ['class' => 'FooBar'],
            'field_mapping' => ['type' => 'string'],
        ]);

        $document = new DocumentStub();

        $queryBuilder = $this->createMock(Builder::class);
        $queryBuilder->expects(static::once())->method('field')->with('field._id')->willReturnSelf();
        $queryBuilder->expects(static::once())->method('notEqual')->with(new ObjectId($document->getId()));

        $filter->apply(new ProxyQuery($queryBuilder), FilterData::fromArray([
            'type' => EqualOperatorType::TYPE_NOT_EQUAL,
            'value' => $document,
        ]));

        static::assertTrue($filter->isActive());
    }

    public function testHandleMultipleNotEqualUsesNotIn(): void
    {
        $filter = new ModelFilter();
        $filter->initialize('field_name', [
            'field_name' => 'field',
            'field_options' => ['class' => 'FooBar'],
            'field_mapping' => ['type' => 'collection'],
        ]);

        $a = new DocumentStub();
        $b = new DocumentStub();

        $queryBuilder = $this->createMock(Builder::class);
        $queryBuilder->expects(static::once())->method('field')->with('field._id')->willReturnSelf();
        $queryBuilder
            ->expects(static::once())
            ->method('notIn')
            ->with([new ObjectId($a->getId()), new ObjectId($b->getId())]);

        $filter->apply(new ProxyQuery($queryBuilder), FilterData::fromArray([
            'type' => EqualOperatorType::TYPE_NOT_EQUAL,
            'value' => [$a, $b],
        ]));

        static::assertTrue($filter->isActive());
    }

    public function testHandleMultipleSkipsNonObjectEntriesAndIsInactiveWhenEmpty(): void
    {
        $filter = new ModelFilter();
        $filter->initialize('field_name', [
            'field_name' => 'field',
            'field_options' => ['class' => 'FooBar'],
            'field_mapping' => ['type' => 'collection'],
        ]);

        $queryBuilder = $this->createMock(Builder::class);
        $queryBuilder->expects(static::never())->method('field');

        // Every entry is non-object → filter must stay inactive.
        $filter->apply(new ProxyQuery($queryBuilder), FilterData::fromArray([
            'type' => EqualOperatorType::TYPE_EQUAL,
            'value' => [null, 'string', 42, new \stdClass()],
        ]));

        static::assertFalse($filter->isActive());
    }

    public function testHandleMultipleIsInactiveWhenValueIsEmptyArray(): void
    {
        $filter = new ModelFilter();
        $filter->initialize('field_name', [
            'field_name' => 'field',
            'field_options' => ['class' => 'FooBar'],
            'field_mapping' => ['type' => 'collection'],
        ]);

        $queryBuilder = $this->createMock(Builder::class);
        $queryBuilder->expects(static::never())->method('field');

        $filter->apply(new ProxyQuery($queryBuilder), FilterData::fromArray([
            'type' => EqualOperatorType::TYPE_EQUAL,
            'value' => [],
        ]));

        static::assertFalse($filter->isActive());
    }

    public function testFixIdentifierAcceptsIntIdentifier(): void
    {
        static::assertSame(42, $this->callFixIdentifier(42));
    }

    public function testFixIdentifierReturnsCustomStringWhenNotAValidObjectId(): void
    {
        static::assertSame('not-an-oid', $this->callFixIdentifier('not-an-oid'));
    }

    /**
     * @phpstan-param mixed $id
     */
    #[DataProvider('provideFixIdentifierRejectsInvalidShapesCases')]
    public function testFixIdentifierRejectsInvalidShapes(mixed $id): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->callFixIdentifier($id);
    }

    /**
     * @phpstan-return iterable<array{mixed}>
     */
    public static function provideFixIdentifierRejectsInvalidShapesCases(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'array' => [['nested']];
        yield 'object' => [new \stdClass()];
    }

    /**
     * @phpstan-return iterable<array{string, string}>
     */
    public static function provideGetIdentifierFieldFollowsStoreAsForEachReferenceShapeCases(): iterable
    {
        yield 'REFERENCE_STORE_AS_REF' => [ClassMetadata::REFERENCE_STORE_AS_REF, 'field_name.id'];
        yield 'REFERENCE_STORE_AS_ID' => [ClassMetadata::REFERENCE_STORE_AS_ID, 'field_name'];
        yield 'REFERENCE_STORE_AS_DB_REF' => [ClassMetadata::REFERENCE_STORE_AS_DB_REF, 'field_name.$id'];
        yield 'REFERENCE_STORE_AS_DB_REF_WITH_DB' => [ClassMetadata::REFERENCE_STORE_AS_DB_REF_WITH_DB, 'field_name.$id'];
    }

    #[DataProvider('provideGetIdentifierFieldFollowsStoreAsForEachReferenceShapeCases')]
    public function testGetIdentifierFieldFollowsStoreAsForEachReferenceShape(
        string $storeAs,
        string $expectedField,
    ): void {
        $filter = new ModelFilter();
        $filter->initialize('field_name', [
            'mapping_type' => ClassMetadata::ONE,
            'field_name' => 'field_name',
            'field_mapping' => ['storeAs' => $storeAs],
        ]);

        $queryBuilder = $this->createMock(Builder::class);
        $queryBuilder
            ->expects(static::once())
            ->method('field')
            ->with($expectedField)
            ->willReturnSelf();

        $filter->apply(new ProxyQuery($queryBuilder), FilterData::fromArray([
            'type' => EqualOperatorType::TYPE_EQUAL,
            'value' => new DocumentStub(),
        ]));

        static::assertTrue($filter->isActive());
    }

    private function callFixIdentifier(mixed $id): mixed
    {
        // ModelFilter is final, so reach the protected static via reflection.
        $reflection = new \ReflectionMethod(ModelFilter::class, 'fixIdentifier');

        return $reflection->invoke(null, $id);
    }
}
