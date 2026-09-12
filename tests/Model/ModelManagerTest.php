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

namespace IDCT\Adminata\DoctrineMongoDB\Tests\Model;

use Doctrine\Common\EventManager;
use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Hydrator\HydratorFactory;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\Mapping\Driver\AttributeDriver;
use Doctrine\ODM\MongoDB\MongoDBException;
use Doctrine\ODM\MongoDB\Query\Builder;
use Doctrine\ODM\MongoDB\Query\Query;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use Doctrine\ODM\MongoDB\UnitOfWork;
use MongoDB\BSON\Int64;
use MongoDB\Collection;
use MongoDB\Driver\CursorInterface;
use MongoDB\Driver\Exception\RuntimeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use IDCT\Adminata\Datagrid\ProxyQueryInterface;
use IDCT\Adminata\Exception\ModelManagerException;
use IDCT\Adminata\DoctrineMongoDB\Datagrid\ProxyQuery;
use IDCT\Adminata\DoctrineMongoDB\Model\ModelManager;
use IDCT\Adminata\DoctrineMongoDB\Tests\ClassMetadataAnnotationTrait;
use IDCT\Adminata\DoctrineMongoDB\Tests\Fixtures\Document\DocumentWithReferences;
use IDCT\Adminata\DoctrineMongoDB\Tests\Fixtures\Document\EmbeddedDocument;
use IDCT\Adminata\DoctrineMongoDB\Tests\Fixtures\Document\SimpleDocumentWithPrivateSetter;
use IDCT\Adminata\DoctrineMongoDB\Tests\Fixtures\Document\TestDocument;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

final class ModelManagerTest extends TestCase
{
    use ClassMetadataAnnotationTrait;

    private PropertyAccessor $propertyAccessor;

    /**
     * @var Stub&ManagerRegistry
     */
    private Stub $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = static::createStub(ManagerRegistry::class);
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function testGetIdentifierFieldNames(): void
    {
        $dm = static::createStub(DocumentManager::class);

        $modelManager = new ModelManager($this->registry, $this->propertyAccessor);

        $this->registry
            ->method('getManagerForClass')
            ->willReturn($dm);

        $documentWithReferencesClass = DocumentWithReferences::class;

        $dm
            ->method('getClassMetadata')
            ->willReturn($this->getMetadataForDocumentWithAttributes($documentWithReferencesClass));

        static::assertSame(['id'], $modelManager->getIdentifierFieldNames($documentWithReferencesClass));
    }

    public function testGetIdentifierFieldNamesStripsNulls(): void
    {
        // `array_filter(..., static fn (?string $id) => null !== $id)` keeps
        // only non-null identifiers. Unwrap the array_filter mutant returns
        // the raw `getIdentifier()` payload — including any nulls — which
        // would later break the downstream `implode()` in
        // getNormalizedIdentifier(). Lock the filtered shape.
        $classMetadata = static::createStub(ClassMetadata::class);
        $classMetadata->method('getIdentifier')->willReturn(['a', null, 'b']);

        $dm = static::createStub(DocumentManager::class);
        $dm->method('getClassMetadata')->willReturn($classMetadata);

        $this->registry->method('getManagerForClass')->willReturn($dm);

        $modelManager = new ModelManager($this->registry, $this->propertyAccessor);

        static::assertSame([0 => 'a', 2 => 'b'], $modelManager->getIdentifierFieldNames(TestDocument::class));
    }

    public function testReverseTransformPrefersFieldMappingsOverAssociationMappings(): void
    {
        // getFieldName() consults fieldMappings first and `return`s; remove
        // that return and a name present in BOTH maps would resolve via the
        // associationMappings branch, sending the value to the wrong setter.
        $object = new class {
            public string $fieldOne = '';
            public string $assocOne = '';
        };

        $classMetadata = static::createStub(ClassMetadata::class);
        // Mocked mapping arrays are intentionally minimal — `getFieldName()`
        // only reads the `fieldName` key off each entry.
        // @phpstan-ignore-next-line assign.propertyType
        $classMetadata->fieldMappings = ['shared' => ['fieldName' => 'fieldOne']];
        // @phpstan-ignore-next-line assign.propertyType
        $classMetadata->associationMappings = ['shared' => ['fieldName' => 'assocOne']];

        $dm = static::createStub(DocumentManager::class);
        $dm->method('getClassMetadata')->willReturn($classMetadata);
        $this->registry->method('getManagerForClass')->willReturn($dm);

        $modelManager = new ModelManager($this->registry, $this->propertyAccessor);
        $modelManager->reverseTransform($object, ['shared' => 'value']);

        static::assertSame('value', $object->fieldOne);
        static::assertSame('', $object->assocOne);
    }

    public function testReverseTransformWithSetter(): void
    {
        $class = TestDocument::class;

        $manager = $this->createModelManagerForClass($class);
        $testDocument = new TestDocument();

        $manager->reverseTransform(
            $testDocument,
            [
                'schmeckles' => 42,
                'multi_word_property' => 'hello',
                'schwifty' => true,
            ]
        );

        static::assertSame(42, $testDocument->getSchmeckles());
        static::assertSame('hello', $testDocument->getMultiWordProperty());
        static::assertTrue($testDocument->schwifty);
    }

    public function testReverseTransformFailsWithPrivateSetter(): void
    {
        $class = SimpleDocumentWithPrivateSetter::class;
        $manager = $this->createModelManagerForClass($class);

        $this->expectException(NoSuchPropertyException::class);

        $manager->reverseTransform(new SimpleDocumentWithPrivateSetter(1), ['schmeckles' => 42]);
    }

    public function testReverseTransformFailsWithPrivateProperties(): void
    {
        $class = TestDocument::class;
        $manager = $this->createModelManagerForClass($class);

        $this->expectException(NoSuchPropertyException::class);

        $manager->reverseTransform(new TestDocument(), ['plumbus' => 42]);
    }

    public function testGetUrlSafeIdentifierException(): void
    {
        $model = new ModelManager($this->registry, $this->propertyAccessor);

        $this->expectException(\RuntimeException::class);

        $model->getNormalizedIdentifier(new \stdClass());
    }

    public function testCreateQuery(): void
    {
        $repository = $this->createMock(DocumentRepository::class);
        $repository
            ->expects(static::once())
            ->method('createQueryBuilder')
            ->willReturn(static::createStub(Builder::class));

        $documentManager = static::createStub(DocumentManager::class);
        $documentManager
            ->method('getRepository')
            ->willReturn($repository);

        $this->registry
            ->method('getManagerForClass')
            ->willReturn($documentManager);

        $modelManager = new ModelManager($this->registry, $this->propertyAccessor);
        $modelManager->createQuery(TestDocument::class);
    }

    public function testCreatePersistsAndFlushes(): void
    {
        $object = new TestDocument();

        $dm = $this->createMock(DocumentManager::class);
        $dm->expects(static::once())->method('persist')->with($object);
        $dm->expects(static::once())->method('flush');

        $this->registry
            ->method('getManagerForClass')
            ->willReturn($dm);

        new ModelManager($this->registry, $this->propertyAccessor)->create($object);
    }

    public function testCreateWrapsMongoExceptionInModelManagerException(): void
    {
        $dm = static::createStub(DocumentManager::class);
        $dm->method('persist')->willThrowException(new RuntimeException('boom'));

        $this->registry
            ->method('getManagerForClass')
            ->willReturn($dm);

        $this->expectException(ModelManagerException::class);
        $this->expectExceptionMessageMatches('/Failed to create object/');

        new ModelManager($this->registry, $this->propertyAccessor)->create(new TestDocument());
    }

    public function testCreateWrapsOdmMongoDBExceptionInModelManagerException(): void
    {
        // The catch clause is `Exception|MongoDBException` — the first arm is
        // the *driver* exception, the second is the *ODM* exception. Drop
        // `MongoDBException` from the union and an ODM-side failure would
        // propagate uncaught instead of being wrapped.
        $dm = static::createStub(DocumentManager::class);
        $dm->method('persist')->willThrowException(new MongoDBException('odm-side'));

        $this->registry->method('getManagerForClass')->willReturn($dm);

        $this->expectException(ModelManagerException::class);
        $this->expectExceptionMessageMatches('/Failed to create object/');

        new ModelManager($this->registry, $this->propertyAccessor)->create(new TestDocument());
    }

    public function testUpdatePersistsAndFlushes(): void
    {
        $object = new TestDocument();

        $dm = $this->createMock(DocumentManager::class);
        $dm->expects(static::once())->method('persist')->with($object);
        $dm->expects(static::once())->method('flush');

        $this->registry
            ->method('getManagerForClass')
            ->willReturn($dm);

        new ModelManager($this->registry, $this->propertyAccessor)->update($object);
    }

    public function testUpdateWrapsMongoExceptionInModelManagerException(): void
    {
        $dm = static::createStub(DocumentManager::class);
        $dm->method('persist')->willThrowException(new RuntimeException('boom'));

        $this->registry
            ->method('getManagerForClass')
            ->willReturn($dm);

        $this->expectException(ModelManagerException::class);
        $this->expectExceptionMessageMatches('/Failed to update object/');

        new ModelManager($this->registry, $this->propertyAccessor)->update(new TestDocument());
    }

    public function testUpdateWrapsOdmMongoDBExceptionInModelManagerException(): void
    {
        $dm = static::createStub(DocumentManager::class);
        $dm->method('persist')->willThrowException(new MongoDBException('odm-side'));

        $this->registry->method('getManagerForClass')->willReturn($dm);

        $this->expectException(ModelManagerException::class);
        $this->expectExceptionMessageMatches('/Failed to update object/');

        new ModelManager($this->registry, $this->propertyAccessor)->update(new TestDocument());
    }

    public function testDeleteRemovesAndFlushes(): void
    {
        $object = new TestDocument();

        $dm = $this->createMock(DocumentManager::class);
        $dm->expects(static::once())->method('remove')->with($object);
        $dm->expects(static::once())->method('flush');

        $this->registry
            ->method('getManagerForClass')
            ->willReturn($dm);

        new ModelManager($this->registry, $this->propertyAccessor)->delete($object);
    }

    public function testDeleteWrapsMongoExceptionInModelManagerException(): void
    {
        $dm = static::createStub(DocumentManager::class);
        $dm->method('remove')->willThrowException(new RuntimeException('boom'));

        $this->registry
            ->method('getManagerForClass')
            ->willReturn($dm);

        $this->expectException(ModelManagerException::class);
        $this->expectExceptionMessageMatches('/Failed to delete object/');

        new ModelManager($this->registry, $this->propertyAccessor)->delete(new TestDocument());
    }

    public function testDeleteWrapsOdmMongoDBExceptionInModelManagerException(): void
    {
        $dm = static::createStub(DocumentManager::class);
        $dm->method('remove')->willThrowException(new MongoDBException('odm-side'));

        $this->registry->method('getManagerForClass')->willReturn($dm);

        $this->expectException(ModelManagerException::class);
        $this->expectExceptionMessageMatches('/Failed to delete object/');

        new ModelManager($this->registry, $this->propertyAccessor)->delete(new TestDocument());
    }

    public function testGetDocumentManagerThrowsWhenNoneRegisteredForClass(): void
    {
        $this->registry
            ->method('getManagerForClass')
            ->willReturn(null);

        $modelManager = new ModelManager($this->registry, $this->propertyAccessor);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No document manager defined for class');

        $modelManager->create(new TestDocument());
    }

    public function testFindDelegatesToRepository(): void
    {
        $expected = new TestDocument();

        $repository = $this->createMock(DocumentRepository::class);
        $repository->expects(static::once())->method('find')->with('the-id')->willReturn($expected);

        $dm = $this->createMock(DocumentManager::class);
        $dm->method('getRepository')->with(TestDocument::class)->willReturn($repository);

        $this->registry
            ->method('getManagerForClass')
            ->willReturn($dm);

        $modelManager = new ModelManager($this->registry, $this->propertyAccessor);

        static::assertSame($expected, $modelManager->find(TestDocument::class, 'the-id'));
    }

    public function testFindByDelegatesToRepository(): void
    {
        $expected = [new TestDocument()];

        $repository = $this->createMock(DocumentRepository::class);
        $repository->expects(static::once())->method('findBy')->with(['name' => 'A'])->willReturn($expected);

        $dm = $this->createMock(DocumentManager::class);
        $dm->method('getRepository')->with(TestDocument::class)->willReturn($repository);

        $this->registry
            ->method('getManagerForClass')
            ->willReturn($dm);

        $modelManager = new ModelManager($this->registry, $this->propertyAccessor);

        static::assertSame($expected, $modelManager->findBy(TestDocument::class, ['name' => 'A']));
    }

    public function testFindOneByDelegatesToRepository(): void
    {
        $expected = new TestDocument();

        $repository = $this->createMock(DocumentRepository::class);
        $repository->expects(static::once())->method('findOneBy')->with(['name' => 'A'])->willReturn($expected);

        $dm = $this->createMock(DocumentManager::class);
        $dm->method('getRepository')->with(TestDocument::class)->willReturn($repository);

        $this->registry
            ->method('getManagerForClass')
            ->willReturn($dm);

        $modelManager = new ModelManager($this->registry, $this->propertyAccessor);

        static::assertSame($expected, $modelManager->findOneBy(TestDocument::class, ['name' => 'A']));
    }

    public function testGetIdentifierValuesAndManagedNormalizedIdentifierIntegration(): void
    {
        // UnitOfWork is final and can't be doubled — exercise the live path
        // against an in-memory DocumentManager to cover both methods.
        $dm = DocumentManager::create(null, $this->createInMemoryConfiguration());

        $document = new DocumentWithReferences('integration');
        $dm->persist($document);
        $dm->flush();

        $registry = static::createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($dm);

        $modelManager = new ModelManager($registry, $this->propertyAccessor);

        $values = $modelManager->getIdentifierValues($document);
        static::assertCount(1, $values);
        static::assertSame($document->id, $values[0]);
        static::assertSame($document->id, $modelManager->getNormalizedIdentifier($document));

        $dm->createQueryBuilder(DocumentWithReferences::class)
            ->remove()
            ->getQuery()
            ->execute();
    }

    public function testGetNormalizedIdentifierReturnsNullForUnmanagedObject(): void
    {
        $object = new TestDocument();

        $dm = $this->createMock(DocumentManager::class);
        $dm->expects(static::once())->method('contains')->with($object)->willReturn(false);

        $this->registry
            ->method('getManagerForClass')
            ->willReturn($dm);

        $modelManager = new ModelManager($this->registry, $this->propertyAccessor);

        static::assertNull($modelManager->getNormalizedIdentifier($object));
    }

    public function testGetUrlSafeIdentifierMirrorsGetNormalizedIdentifier(): void
    {
        $object = new TestDocument();

        $dm = static::createStub(DocumentManager::class);
        $dm->method('contains')->willReturn(false);

        $this->registry
            ->method('getManagerForClass')
            ->willReturn($dm);

        $modelManager = new ModelManager($this->registry, $this->propertyAccessor);

        static::assertNull($modelManager->getUrlSafeIdentifier($object));
    }

    public function testAddIdentifiersToQueryAppliesInClauseOnIdField(): void
    {
        $queryBuilder = $this->createMock(Builder::class);
        $queryBuilder->expects(static::once())->method('field')->with('_id')->willReturnSelf();
        $queryBuilder->expects(static::once())->method('in')->with(['1', '2']);

        $proxyQuery = new ProxyQuery($queryBuilder);

        $modelManager = new ModelManager($this->registry, $this->propertyAccessor);
        $modelManager->addIdentifiersToQuery(TestDocument::class, $proxyQuery, ['1', '2']);
    }

    public function testAddIdentifiersToQueryThrowsForForeignProxyQuery(): void
    {
        $modelManager = new ModelManager($this->registry, $this->propertyAccessor);

        $this->expectException(\TypeError::class);

        $modelManager->addIdentifiersToQuery(
            TestDocument::class,
            static::createStub(ProxyQueryInterface::class),
            ['1'],
        );
    }

    public function testBatchDeleteClearsDocumentManagerOnEachFullBatch(): void
    {
        // BATCH_SIZE = 20. With 21 documents we expect the loop to hit the
        // batch boundary once (clear+flush at i=20) and then a final flush+clear
        // after the trailing item — two clear() calls in total. Dropping the
        // in-loop `clear()` (mutant 84, MethodCallRemoval) would leave only the
        // final one.
        $documents = array_fill(0, 21, new DocumentWithReferences('test', new EmbeddedDocument()));

        $classMetadata = static::createStub(ClassMetadata::class);
        $classMetadata->method('newInstance')->willReturn(new DocumentWithReferences('test', new EmbeddedDocument()));
        $classMetadata->name = DocumentWithReferences::class;
        $classMetadata->reflClass = static::createStub(\ReflectionClass::class);

        $dm = $this->createMock(DocumentManager::class);
        $dm->method('contains')->willReturnCallback(
            static fn (object $document): bool => $document instanceof DocumentWithReferences,
        );

        $cursor = $this->createBatchCursor($documents);

        $collection = static::createStub(Collection::class);
        $collection->method('find')->willReturn($cursor);

        $queryBuilder = static::createStub(Builder::class);
        $queryBuilder->method('getQuery')->willReturn(new Query(
            $dm,
            $classMetadata,
            $collection,
            ['type' => Query::TYPE_FIND, 'query' => []],
        ));

        $documentRepository = static::createStub(DocumentRepository::class);
        $documentRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $dm->method('getRepository')->willReturn($documentRepository);
        $dm->method('getClassMetadata')->willReturn($classMetadata);
        $dm->expects(static::exactly(2))->method('clear');
        $dm->expects(static::exactly(2))->method('flush');

        $eventManager = new EventManager();
        $hydratorFactory = new HydratorFactory(
            $dm,
            $eventManager,
            sys_get_temp_dir(),
            'IDCT\Adminata\DoctrineMongoDB\Tests\Hydrator',
            Configuration::AUTOGENERATE_FILE_NOT_EXISTS,
        );
        $dm->method('getUnitOfWork')->willReturn(new UnitOfWork($dm, $eventManager, $hydratorFactory));

        $registry = static::createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($dm);

        $modelManager = new ModelManager($registry, $this->propertyAccessor);
        $proxyQuery = $modelManager->createQuery(DocumentWithReferences::class);

        $modelManager->batchDelete(DocumentWithReferences::class, $proxyQuery, 20);
    }

    public function testBatchDeleteThrowsForForeignProxyQuery(): void
    {
        $modelManager = new ModelManager($this->registry, $this->propertyAccessor);

        $this->expectException(\TypeError::class);

        $modelManager->batchDelete(
            TestDocument::class,
            static::createStub(ProxyQueryInterface::class),
        );
    }

    public function testGetExportFieldsReturnsClassMetadataFieldNames(): void
    {
        $classMetadata = static::createStub(ClassMetadata::class);
        $classMetadata->method('getFieldNames')->willReturn(['id', 'name']);

        $dm = static::createStub(DocumentManager::class);
        $dm->method('getClassMetadata')->willReturn($classMetadata);

        $this->registry
            ->method('getManagerForClass')
            ->willReturn($dm);

        $modelManager = new ModelManager($this->registry, $this->propertyAccessor);

        static::assertSame(['id', 'name'], $modelManager->getExportFields(TestDocument::class));
    }

    public function testExecuteQueryThrowsForUnsupportedQueryType(): void
    {
        $modelManager = new ModelManager($this->registry, $this->propertyAccessor);

        $this->expectException(\TypeError::class);

        $modelManager->executeQuery(new \stdClass());
    }

    public function testExecuteQueryWithBuilderIntegration(): void
    {
        // Doctrine\ODM\MongoDB\Query\Query is final and can't be doubled, so
        // exercise both Builder and ProxyQuery against an in-memory DocumentManager.
        $dm = DocumentManager::create(null, $this->createInMemoryConfiguration());
        $dm->persist(new DocumentWithReferences('exec-builder'));
        $dm->flush();

        $registry = static::createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($dm);

        $modelManager = new ModelManager($registry, $this->propertyAccessor);

        $builder = $dm->createQueryBuilder(DocumentWithReferences::class);
        $result = $modelManager->executeQuery($builder);

        $names = [];
        foreach ($result as $doc) {
            static::assertInstanceOf(DocumentWithReferences::class, $doc);
            $names[] = $doc->name;
        }
        static::assertSame(['exec-builder'], $names);

        $dm->createQueryBuilder(DocumentWithReferences::class)
            ->remove()
            ->getQuery()
            ->execute();
    }

    public function testExecuteQueryWithProxyQueryIntegration(): void
    {
        $dm = DocumentManager::create(null, $this->createInMemoryConfiguration());
        $dm->persist(new DocumentWithReferences('exec-proxy'));
        $dm->flush();

        $registry = static::createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($dm);

        $modelManager = new ModelManager($registry, $this->propertyAccessor);

        $proxyQuery = new ProxyQuery($dm->createQueryBuilder(DocumentWithReferences::class));
        $result = $modelManager->executeQuery($proxyQuery);

        $names = [];
        foreach ($result as $doc) {
            static::assertInstanceOf(DocumentWithReferences::class, $doc);
            $names[] = $doc->name;
        }
        static::assertSame(['exec-proxy'], $names);

        $dm->createQueryBuilder(DocumentWithReferences::class)
            ->remove()
            ->getQuery()
            ->execute();
    }

    #[DataProvider('provideSupportsQueryCases')]
    public function testSupportsQuery(bool $expected, object $object): void
    {
        $modelManager = new ModelManager($this->registry, $this->propertyAccessor);

        static::assertSame($expected, $modelManager->supportsQuery($object));
    }

    public function testGetRealClassWithProxyObject(): void
    {
        $proxyClass = TestDocument::class;
        $baseClass = \stdClass::class;

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->expects(static::once())
            ->method('getName')
            ->willReturn($baseClass);

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(static::once())
            ->method('getClassMetadata')
            ->with($proxyClass)
            ->willReturn($classMetadata);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(static::once())
            ->method('getManagerForClass')
            ->with($proxyClass)
            ->willReturn($documentManager);

        $modelManager = new ModelManager($registry, $this->propertyAccessor);

        static::assertSame($baseClass, $modelManager->getRealClass(new TestDocument()));
    }

    public function testGetRealClassWithNonProxyObject(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(static::once())
            ->method('getManagerForClass')
            ->with(\DateTime::class)
            ->willReturn(null);

        $modelManager = new ModelManager($registry, $this->propertyAccessor);

        static::assertSame(\DateTime::class, $modelManager->getRealClass(new \DateTime()));
    }

    /**
     * @phpstan-return iterable<array{bool, object}>
     */
    public static function provideSupportsQueryCases(): iterable
    {
        yield [true, new ProxyQuery(static::createStub(Builder::class))];
        yield [true, static::createStub(Builder::class)];
        yield [false, new \stdClass()];
    }

    /**
     * @return iterable<int|string, array<int, string|array<int, DocumentWithReferences|null>>>
     *
     * @phpstan-return iterable<int|string, array{0: string, 1: array<int, DocumentWithReferences>, 2: array<int,
     *                 mixed>}>
     */
    public static function provideFailingBatchDeleteCases(): iterable
    {
        yield [
            '#^Failed to delete object "IDCT\\\Adminata\\\DoctrineMongoDB\\\Tests\\\Fixtures\\\Document\\\DocumentWithReferences"'
            .' \(id: [a-z0-9]*\) while performing batch deletion \(20 objects were successfully deleted before this error\)$#',
            array_fill(0, 21, new DocumentWithReferences('test', new EmbeddedDocument())),
            [null, new RuntimeException()],
        ];

        yield [
            '#^Failed to delete object "IDCT\\\Adminata\\\DoctrineMongoDB\\\Tests\\\Fixtures\\\Document\\\DocumentWithReferences"'
            .' \(id: [a-z0-9]*\) while performing batch deletion$#',
            [new DocumentWithReferences('test', new EmbeddedDocument()), new DocumentWithReferences('test', new EmbeddedDocument())],
            [new RuntimeException()],
        ];

        yield [
            '#^Failed to perform batch deletion for "IDCT\\\Adminata\\\DoctrineMongoDB\\\Tests\\\Fixtures\\\Document\\\DocumentWithReferences"'
            .' objects$#',
            [],
            [new RuntimeException()],
        ];

        // Locks the `$i > $batchSize` boundary (mutant 86 flips to `>=`).
        // With exactly batchSize items and a failure on the first (and only)
        // flush, confirmedDeletionsCount is still 0 — the message must NOT
        // carry the `(N objects were successfully deleted…)` suffix.
        yield 'exactly one batch, fails on first flush, no confirmed-deletions suffix' => [
            '#^Failed to delete object "IDCT\\\Adminata\\\DoctrineMongoDB\\\Tests\\\Fixtures\\\Document\\\DocumentWithReferences"'
            .' \(id: [a-z0-9]*\) while performing batch deletion$#',
            array_fill(0, 20, new DocumentWithReferences('test', new EmbeddedDocument())),
            [new RuntimeException()],
        ];

        // Locks the ODM-side MongoDBException in the catch union (mutant 85).
        // Removing `|MongoDBException` would let this case propagate uncaught
        // instead of being wrapped into a ModelManagerException.
        yield 'odm-side MongoDBException is wrapped' => [
            '#^Failed to perform batch deletion for "IDCT\\\Adminata\\\DoctrineMongoDB\\\Tests\\\Fixtures\\\Document\\\DocumentWithReferences"'
            .' objects$#',
            [],
            [new MongoDBException('odm-side')],
        ];
    }

    /**
     * @param array<int, DocumentWithReferences> $result
     * @param array<int, mixed>                  $onConsecutiveFlush
     */
    #[DataProvider('provideFailingBatchDeleteCases')]
    public function testFailingBatchDelete(string $expectedExceptionMessage, array $result, array $onConsecutiveFlush): void
    {
        $batchSize = 20;

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->expects([] === $result ? static::never() : static::atLeastOnce())
            ->method('newInstance')
            ->willReturn(new DocumentWithReferences('test', new EmbeddedDocument()));
        $classMetadata->name = DocumentWithReferences::class;
        $classMetadata->reflClass = static::createStub(\ReflectionClass::class);

        $dm = $this->createMock(DocumentManager::class);
        $dm
            ->expects([] === $result ? static::never() : static::atLeastOnce())
            ->method('contains')
            ->willReturnCallback(static fn (object $document): bool => $document instanceof DocumentWithReferences);

        /**
         * @psalm-suppress MissingTemplateParam
         *
         * @phpstan-implements \Iterator<int, array{'_id': string|null}>
         */
        $cursor = new class($result) implements CursorInterface {
            /**
             * @var \Iterator<int, array{'_id': string|null}>
             */
            private \Iterator $iterator;

            /**
             * @param array<int, DocumentWithReferences> $result
             */
            public function __construct(private array $result)
            {
                $elements = [];
                foreach ($this->result as $document) {
                    $elements[] = [
                        '_id' => $document->id,
                    ];
                }

                $this->iterator = new \ArrayIterator($elements);
            }

            /** @psalm-suppress ImplementedReturnTypeMismatch */
            public function getId(): Int64
            {
                return new Int64(42);
            }

            /**
             * @throws \BadMethodCallException
             */
            public function getServer(): never
            {
                throw new \BadMethodCallException();
            }

            /**
             * @phpstan-throws void
             */
            public function isDead(): bool
            {
                return false;
            }

            /**
             * @param array<mixed> $typemap
             *
             * @phpstan-throws void
             */
            public function setTypeMap(array $typemap): void
            {
            }

            /**
             * @phpstan-throws void
             *
             * @return DocumentWithReferences[]
             */
            public function toArray(): array
            {
                return $this->result;
            }

            public function valid(): bool
            {
                return $this->iterator->valid();
            }

            /**
             * @return array{'_id': string|null}
             */
            public function current(): array
            {
                $current = $this->iterator->current();
                \assert(null !== $current);

                return $current;
            }

            public function next(): void
            {
                $this->iterator->next();
            }

            public function rewind(): void
            {
                $this->iterator->rewind();
            }

            public function key(): int
            {
                $key = $this->iterator->key();
                \assert(null !== $key);

                return $key;
            }
        };

        $collection = $this->createMock(Collection::class);
        $collection
            ->expects(static::atLeastOnce())
            ->method('find')
            ->willReturn($cursor);

        $queryBuilder = $this->createMock(Builder::class);
        $queryBuilder
            ->expects(static::atLeastOnce())
            ->method('getQuery')
            ->willReturn(new Query(
                $dm,
                $classMetadata,
                $collection,
                [
                    'type' => Query::TYPE_FIND,
                    'query' => ['$id' => '00000000000011190000000000000000'],
                ]
            ));

        $documentRepository = $this->createMock(DocumentRepository::class);
        $documentRepository
            ->expects(static::atLeastOnce())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $dm
            ->expects(static::atLeastOnce())
            ->method('getRepository')
            ->with(DocumentWithReferences::class)
            ->willReturn($documentRepository);
        $dm->expects([] === $result ? static::never() : static::atLeastOnce())
            ->method('getClassMetadata')
            ->with(DocumentWithReferences::class)
            ->willReturn($classMetadata);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(static::atLeastOnce())
            ->method('getManagerForClass')
            ->with(DocumentWithReferences::class)
            ->willReturn($dm);

        $dm
            ->expects(static::exactly(\count($result)))
            ->method('remove');
        $dm
            ->expects(static::exactly([] === $result ? 1 : (int) ceil(\count($result) / $batchSize)))
            ->method('flush')
            ->willReturnCallback(static function () use (&$onConsecutiveFlush): void {
                $e = array_shift($onConsecutiveFlush);
                if ($e instanceof \Exception) {
                    throw $e;
                }
                // Non-exception values in the stub queue are inert: flush()
                // returns void, so the callback returns void to match.
            });

        $eventManager = new EventManager();
        $hydratorFactory = new HydratorFactory(
            $dm,
            $eventManager,
            sys_get_temp_dir(),
            'IDCT\Adminata\DoctrineMongoDB\Tests\Hydrator',
            Configuration::AUTOGENERATE_FILE_NOT_EXISTS
        );
        $uow = new UnitOfWork($dm, $eventManager, $hydratorFactory);

        $dm
            ->expects(static::atLeastOnce())
            ->method('getUnitOfWork')
            ->willReturn($uow);

        $modelManager = new ModelManager($registry, $this->propertyAccessor);

        $proxyQuery = $modelManager->createQuery(DocumentWithReferences::class);

        $this->expectException(ModelManagerException::class);
        $this->expectExceptionMessageMatches($expectedExceptionMessage);

        $modelManager->batchDelete(DocumentWithReferences::class, $proxyQuery, $batchSize);
    }

    /**
     * @param array<int, DocumentWithReferences> $documents
     */
    private function createBatchCursor(array $documents): CursorInterface
    {
        return new class($documents) implements CursorInterface {
            /** @var \Iterator<int, array{'_id': string|null}> */
            private \Iterator $iterator;

            /** @param array<int, DocumentWithReferences> $documents */
            public function __construct(private array $documents)
            {
                $elements = [];
                foreach ($this->documents as $document) {
                    $elements[] = ['_id' => $document->id];
                }

                $this->iterator = new \ArrayIterator($elements);
            }

            public function getId(): Int64
            {
                return new Int64(42);
            }

            public function getServer(): never
            {
                throw new \BadMethodCallException();
            }

            public function isDead(): bool
            {
                return false;
            }

            /** @param array<mixed> $typemap */
            public function setTypeMap(array $typemap): void
            {
            }

            /** @return DocumentWithReferences[] */
            public function toArray(): array
            {
                return $this->documents;
            }

            public function valid(): bool
            {
                return $this->iterator->valid();
            }

            /** @return array{'_id': string|null} */
            public function current(): array
            {
                $current = $this->iterator->current();
                \assert(null !== $current);

                return $current;
            }

            public function next(): void
            {
                $this->iterator->next();
            }

            public function rewind(): void
            {
                $this->iterator->rewind();
            }

            public function key(): int
            {
                $key = $this->iterator->key();
                \assert(null !== $key);

                return $key;
            }
        };
    }

    private function createInMemoryConfiguration(): Configuration
    {
        $config = new Configuration();

        $directory = sys_get_temp_dir().'/mongodb';

        $config->setProxyDir($directory);
        $config->setProxyNamespace('Proxies');
        $config->setHydratorDir($directory);
        $config->setHydratorNamespace('Hydrators');
        $config->setPersistentCollectionDir($directory);
        $config->setPersistentCollectionNamespace('PersistentCollections');
        $config->setMetadataDriverImpl(new AttributeDriver());

        $config->setUseNativeLazyObject(true);

        return $config;
    }

    /**
     * @phpstan-template T of object
     * @phpstan-param class-string<T> $class
     * @phpstan-return ModelManager<T>
     */
    private function createModelManagerForClass(string $class): ModelManager
    {
        $modelManager = $this->createMock(DocumentManager::class);
        $registry = $this->createMock(ManagerRegistry::class);

        $classMetadata = $this->getMetadataForDocumentWithAttributes($class);

        $modelManager->expects(static::once())
            ->method('getClassMetadata')
            ->with($class)
            ->willReturn($classMetadata);
        $registry->expects(static::once())
            ->method('getManagerForClass')
            ->with($class)
            ->willReturn($modelManager);

        /** @phpstan-var ModelManager<T> $modelManager */
        $modelManager = new ModelManager($registry, $this->propertyAccessor);

        return $modelManager;
    }
}
