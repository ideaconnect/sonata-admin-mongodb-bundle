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

namespace IDCT\Adminata\DoctrineMongoDB\Tests\Exporter;

use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\Mapping\Driver\AttributeDriver;
use Doctrine\ODM\MongoDB\Query\Builder;
use Doctrine\ODM\MongoDB\Query\Query;
use MongoDB\Collection;
use PHPUnit\Framework\TestCase;
use IDCT\Adminata\Datagrid\ProxyQueryInterface;
use IDCT\Adminata\DoctrineMongoDB\Datagrid\ProxyQuery;
use IDCT\Adminata\DoctrineMongoDB\Exporter\DataSource;
use IDCT\Adminata\DoctrineMongoDB\Tests\Fixtures\Document\DocumentWithReferences;

final class DataSourceTest extends TestCase
{
    private DataSource $dataSource;

    protected function setUp(): void
    {
        $this->dataSource = new DataSource();
    }

    public function testItResetsTheQueryBeforeCreatingIterator(): void
    {
        $query = new Query(
            static::createStub(DocumentManager::class),
            static::createStub(ClassMetadata::class),
            static::createStub(Collection::class),
            ['type' => Query::TYPE_FIND]
        );

        $queryBuilder = static::createStub(Builder::class);
        $queryBuilder
            ->method('getQuery')
            ->willReturn($query);

        $proxyQuery = new ProxyQuery($queryBuilder);
        $proxyQuery->setFirstResult(10);
        $proxyQuery->setMaxResults(10);

        $this->dataSource->createIterator($proxyQuery, []);

        static::assertNull($proxyQuery->getFirstResult());
        static::assertNull($proxyQuery->getMaxResults());
    }

    public function testItThrowAnExceptionWithInvalidQuery(): void
    {
        $proxyQuery = static::createStub(ProxyQueryInterface::class);

        $this->expectException(\TypeError::class);

        $this->dataSource->createIterator($proxyQuery, []);
    }

    public function testHydrateFlagFlowsThroughToClonedBuilder(): void
    {
        $query = new Query(
            static::createStub(DocumentManager::class),
            static::createStub(ClassMetadata::class),
            static::createStub(Collection::class),
            ['type' => Query::TYPE_FIND]
        );

        $queryBuilder = $this->createMock(Builder::class);
        $queryBuilder
            ->expects(static::once())
            ->method('hydrate')
            ->with(false)
            ->willReturnSelf();
        $queryBuilder
            ->method('getQuery')
            ->willReturn($query);

        $dataSource = new DataSource(false);
        $dataSource->createIterator(new ProxyQuery($queryBuilder), []);
    }

    public function testHydrateDefaultsToTrueForBackwardsCompatibility(): void
    {
        $query = new Query(
            static::createStub(DocumentManager::class),
            static::createStub(ClassMetadata::class),
            static::createStub(Collection::class),
            ['type' => Query::TYPE_FIND]
        );

        $queryBuilder = $this->createMock(Builder::class);
        $queryBuilder
            ->expects(static::once())
            ->method('hydrate')
            ->with(true)
            ->willReturnSelf();
        $queryBuilder
            ->method('getQuery')
            ->willReturn($query);

        $this->dataSource->createIterator(new ProxyQuery($queryBuilder), []);
    }

    public function testCreateIteratorDoesNotMutateSharedBuilder(): void
    {
        // The CloneRemoval mutant strips the `clone` in createIterator, which
        // would let the hydrate(false) call mutate the source builder the
        // proxy was constructed with. Use a real Builder so the setter
        // actually flips state — mocks intercept the call and don't expose
        // mutation we can observe.
        $dm = DocumentManager::create(null, $this->createConfiguration());
        $queryBuilder = $dm->createQueryBuilder(DocumentWithReferences::class);
        $queryBuilder->hydrate(true);

        $proxyQuery = new ProxyQuery($queryBuilder);

        new DataSource(false)->createIterator($proxyQuery, []);

        $hydrateProp = new \ReflectionProperty(Builder::class, 'hydrate');
        static::assertTrue(
            $hydrateProp->getValue($queryBuilder),
            'Source builder hydrate flag must not be touched (clone protects it).',
        );
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
