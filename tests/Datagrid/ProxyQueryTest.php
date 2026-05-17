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

namespace Sonata\DoctrineMongoDBAdminBundle\Tests\Datagrid;

use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\Driver\AttributeDriver;
use Doctrine\ODM\MongoDB\Query\Builder;
use PHPUnit\Framework\TestCase;
use Sonata\DoctrineMongoDBAdminBundle\Datagrid\ProxyQuery;
use Sonata\DoctrineMongoDBAdminBundle\Tests\Fixtures\Document\DocumentWithReferences;
use Sonata\DoctrineMongoDBAdminBundle\Tests\Fixtures\Document\EmbeddedDocument;

final class ProxyQueryTest extends TestCase
{
    private DocumentManager $dm;

    protected function setUp(): void
    {
        $this->dm = DocumentManager::create(null, $this->createConfiguration());
    }

    protected function tearDown(): void
    {
        $this->dm->createQueryBuilder(DocumentWithReferences::class)
            ->remove()
            ->getQuery()
            ->execute();
    }

    public function testSettersDoNotMutateTheSharedQueryBuilder(): void
    {
        // Regression for B1: setMaxResults/setFirstResult used to call
        // ->limit()/->skip() on the QueryBuilder passed at construction,
        // leaking the proxy's pagination state into a builder the caller
        // may still hold a reference to.
        $queryBuilder = $this->createMock(Builder::class);
        $queryBuilder->expects(static::never())->method('limit');
        $queryBuilder->expects(static::never())->method('skip');

        $proxyQuery = new ProxyQuery($queryBuilder);
        $proxyQuery->setMaxResults(10);
        $proxyQuery->setFirstResult(5);

        static::assertSame(10, $proxyQuery->getMaxResults());
        static::assertSame(5, $proxyQuery->getFirstResult());
    }

    public function testSetMaxResultsAndFirstResultAreStoredAndReturnedVerbatim(): void
    {
        $proxyQuery = new ProxyQuery(static::createStub(Builder::class));

        $proxyQuery->setMaxResults(null);
        $proxyQuery->setFirstResult(null);

        static::assertNull($proxyQuery->getMaxResults());
        static::assertNull($proxyQuery->getFirstResult());
    }

    public function testSorting(): void
    {
        $proxyQuery = new ProxyQuery(static::createStub(Builder::class));
        $proxyQuery->setSortBy([], ['fieldName' => 'name']);
        $proxyQuery->setSortOrder('ASC');

        static::assertSame(
            'name',
            $proxyQuery->getSortBy()
        );

        // Sort order is normalised to lower case so it can be safely fed
        // straight into Doctrine's Builder::sort() without ambiguity.
        static::assertSame(
            'asc',
            $proxyQuery->getSortOrder()
        );
    }

    public function testSetSortByRejectsInvalidFieldName(): void
    {
        $proxyQuery = new ProxyQuery(static::createStub(Builder::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sort field');

        $proxyQuery->setSortBy([], ['fieldName' => '$where']);
    }

    public function testSetSortByAcceptsUnderscorePrefixedFieldName(): void
    {
        // _id is the canonical Mongo identifier and must remain sortable —
        // the validation regex allows a leading underscore deliberately.
        $proxyQuery = new ProxyQuery(static::createStub(Builder::class));
        $proxyQuery->setSortBy([], ['fieldName' => '_id']);

        static::assertSame('_id', $proxyQuery->getSortBy());
    }

    public function testSetSortOrderRejectsUnknownValues(): void
    {
        $proxyQuery = new ProxyQuery(static::createStub(Builder::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sort order');

        $proxyQuery->setSortOrder('sideways');
    }

    public function testSortingWithWithEmbedded(): void
    {
        $queryBuilder = $this->dm->createQueryBuilder(DocumentWithReferences::class);

        $proxyQuery = new ProxyQuery($queryBuilder);
        $proxyQuery->setSortBy([['fieldName' => 'embeddedDocument']], ['fieldName' => 'position']);

        static::assertSame(
            'embeddedDocument.position',
            $proxyQuery->getSortBy()
        );
    }

    public function testExecuteAllowsSorting(): void
    {
        $documentA = new DocumentWithReferences('A');
        $documentB = new DocumentWithReferences('B');

        $this->dm->persist($documentA);
        $this->dm->persist($documentB);
        $this->dm->flush();

        $queryBuilder = $this->dm->createQueryBuilder(DocumentWithReferences::class);
        $proxyQuery = new ProxyQuery($queryBuilder);
        $proxyQuery->setSortBy([], ['fieldName' => 'name']);
        $proxyQuery->setSortOrder('DESC');

        /** @var iterable<DocumentWithReferences> $result */
        $result = $proxyQuery->execute();

        static::assertSame(['B', 'A'], $this->getNames($result));
    }

    public function testExecuteAllowsSortingWithEmbedded(): void
    {
        $documentA = new DocumentWithReferences('A', new EmbeddedDocument(1));
        $documentB = new DocumentWithReferences('B', new EmbeddedDocument(2));

        $this->dm->persist($documentA);
        $this->dm->persist($documentB);
        $this->dm->flush();

        $queryBuilder = $this->dm->createQueryBuilder(DocumentWithReferences::class);

        $proxyQuery = new ProxyQuery($queryBuilder);
        $proxyQuery->setSortBy([['fieldName' => 'embeddedDocument']], ['fieldName' => 'position']);
        $proxyQuery->setSortOrder('DESC');

        /** @var iterable<DocumentWithReferences> $result */
        $result = $proxyQuery->execute();

        static::assertSame(['B', 'A'], $this->getNames($result));
    }

    public function testExecuteAppliesFirstResultAndMaxResultsWithoutMutatingSharedBuilder(): void
    {
        // Regression for B1: pagination must be applied on the clone produced
        // inside execute(), not on the original builder.
        $documents = [];
        foreach (['A', 'B', 'C', 'D'] as $name) {
            $documents[$name] = new DocumentWithReferences($name);
            $this->dm->persist($documents[$name]);
        }
        $this->dm->flush();

        $queryBuilder = $this->dm->createQueryBuilder(DocumentWithReferences::class);
        $proxyQuery = new ProxyQuery($queryBuilder);
        $proxyQuery->setSortBy([], ['fieldName' => 'name']);
        $proxyQuery->setSortOrder('ASC');
        $proxyQuery->setFirstResult(1);
        $proxyQuery->setMaxResults(2);

        /** @var iterable<DocumentWithReferences> $page1 */
        $page1 = $proxyQuery->execute();
        static::assertSame(['B', 'C'], $this->getNames($page1));

        // The original builder must still be unbounded; verify a fresh query
        // off the SAME builder returns everything in sort order.
        $proxyQueryAll = new ProxyQuery($queryBuilder);
        $proxyQueryAll->setSortBy([], ['fieldName' => 'name']);
        $proxyQueryAll->setSortOrder('ASC');
        /** @var iterable<DocumentWithReferences> $all */
        $all = $proxyQueryAll->execute();
        static::assertSame(['A', 'B', 'C', 'D'], $this->getNames($all));

        // And the original proxyQuery is reusable: changing maxResults and
        // executing again gives a different page off the same shared builder.
        $proxyQuery->setFirstResult(0);
        $proxyQuery->setMaxResults(1);
        /** @var iterable<DocumentWithReferences> $page2 */
        $page2 = $proxyQuery->execute();
        static::assertSame(['A'], $this->getNames($page2));
    }

    /**
     * @param iterable<DocumentWithReferences> $results
     *
     * @return string[]
     */
    private function getNames(iterable $results): array
    {
        $names = [];

        foreach ($results as $result) {
            $names[] = $result->name;
        }

        return $names;
    }

    private function createConfiguration(): Configuration
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
}
