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

namespace IDCT\Adminata\DoctrineMongoDB\Tests\Datagrid;

use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\Driver\AttributeDriver;
use PHPUnit\Framework\TestCase;
use IDCT\Adminata\DoctrineMongoDB\Datagrid\Pager;
use IDCT\Adminata\DoctrineMongoDB\Datagrid\ProxyQuery;
use IDCT\Adminata\DoctrineMongoDB\Tests\Fixtures\Document\DocumentWithReferences;

final class PagerTest extends TestCase
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

    public function testCountResultsThrowsBeforeInit(): void
    {
        $pager = new Pager();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Pager has not been initialized');

        $pager->countResults();
    }

    public function testInitThrowsWhenNoQueryIsSet(): void
    {
        $pager = new Pager();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Uninitialized query.');

        $pager->init();
    }

    public function testGetCurrentPageResultsThrowsWhenNoQueryIsSet(): void
    {
        $pager = new Pager();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Uninitialized query.');

        $pager->getCurrentPageResults();
    }

    public function testInitCountsResultsAndSetsLastPage(): void
    {
        $this->persistNames(['A', 'B', 'C', 'D', 'E']);

        $pager = $this->createInitializedPager(maxPerPage: 2, page: 1);

        static::assertSame(5, $pager->countResults());
        static::assertSame(3, $pager->getLastPage(), '5 / 2 rounded up');
    }

    public function testInitSetsLastPageToOneOnEmptyCollection(): void
    {
        $pager = $this->createInitializedPager(maxPerPage: 10, page: 1);

        static::assertSame(0, $pager->countResults());
        static::assertSame(1, $pager->getLastPage());
    }

    public function testInitSetsLastPageToZeroWhenMaxPerPageIsZero(): void
    {
        // Note: BasePager::setPage(0) normalises page to 1 when maxPerPage > 0,
        // so the only way to land in the "0 === page || 0 === maxPerPage"
        // branch in Pager::init() is via maxPerPage = 0.
        $this->persistNames(['A', 'B']);

        $pager = $this->createInitializedPager(maxPerPage: 0, page: 1);

        static::assertSame(0, $pager->getLastPage());
    }

    public function testInitResetsProxyPaginationToNull(): void
    {
        // R3: post-init the proxy's first/maxResults should be null (proxy's
        // "no pagination" state), not 0. Previously init() reset to 0, which
        // forced a useless skip(0)/limit(0) on every subsequent execute().
        // Pre-seed both with non-null values so a missing reset would leave
        // them intact (otherwise a fresh proxy already has null defaults and
        // the assertions can't distinguish "reset" from "untouched").
        $this->persistNames(['A', 'B']);

        $queryBuilder = $this->dm->createQueryBuilder(DocumentWithReferences::class);
        $proxyQuery = new ProxyQuery($queryBuilder);
        $proxyQuery->setFirstResult(123);
        $proxyQuery->setMaxResults(456);

        $pager = new Pager();
        $pager->setMaxPerPage(0);
        $pager->setPage(1);
        $pager->setQuery($proxyQuery);
        $pager->init();

        static::assertNull($proxyQuery->getFirstResult());
        static::assertNull($proxyQuery->getMaxResults());
    }

    public function testInitRoundsUpPartialLastPage(): void
    {
        // 11 results / 10 per page → ceil = 2, round = 1. Locks ceil semantics
        // so the last partial page is always reachable.
        $this->persistNames(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K']);

        $pager = $this->createInitializedPager(maxPerPage: 10, page: 1);

        static::assertSame(11, $pager->countResults());
        static::assertSame(2, $pager->getLastPage());
    }

    public function testCloneResetsResultsCount(): void
    {
        // R12: cloning an initialized pager must not carry the source's
        // resultsCount through — the clone's count is undefined until it
        // gets its own init().
        $this->persistNames(['A', 'B']);

        $pager = $this->createInitializedPager(maxPerPage: 10, page: 1);
        static::assertSame(2, $pager->countResults());

        $clone = clone $pager;

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Pager has not been initialized');
        $clone->countResults();
    }

    public function testGetCurrentPageResultsReturnsTheRightSlice(): void
    {
        $this->persistNames(['A', 'B', 'C', 'D', 'E']);

        $pager = $this->createInitializedPager(maxPerPage: 2, page: 2, sortBy: 'name');

        /** @var iterable<DocumentWithReferences> $results */
        $results = $pager->getCurrentPageResults();

        static::assertSame(['C', 'D'], $this->getNames($results));
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

    /**
     * @param string[] $names
     */
    private function persistNames(array $names): void
    {
        foreach ($names as $name) {
            $this->dm->persist(new DocumentWithReferences($name));
        }
        $this->dm->flush();
    }

    private function createInitializedPager(int $maxPerPage, int $page, ?string $sortBy = null): Pager
    {
        $queryBuilder = $this->dm->createQueryBuilder(DocumentWithReferences::class);
        $proxyQuery = new ProxyQuery($queryBuilder);
        if (null !== $sortBy) {
            $proxyQuery->setSortBy([], ['fieldName' => $sortBy]);
            $proxyQuery->setSortOrder('ASC');
        }

        $pager = new Pager();
        $pager->setMaxPerPage($maxPerPage);
        $pager->setPage($page);
        $pager->setQuery($proxyQuery);
        $pager->init();

        return $pager;
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
