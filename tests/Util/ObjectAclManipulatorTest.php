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

namespace IDCT\Adminata\DoctrineMongoDB\Tests\Util;

use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\Driver\AttributeDriver;
use PHPUnit\Framework\TestCase;
use IDCT\Adminata\Admin\AdminInterface;
use IDCT\Adminata\Exception\ModelManagerException;
use IDCT\Adminata\Model\ModelManagerInterface;
use IDCT\Adminata\Security\Handler\AclSecurityHandlerInterface;
use IDCT\Adminata\Security\Handler\NoopSecurityHandler;
use IDCT\Adminata\DoctrineMongoDB\Tests\Fixtures\Document\DocumentForAcl;
use IDCT\Adminata\DoctrineMongoDB\Util\ObjectAclManipulator;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Acl\Model\MutableAclInterface;

final class ObjectAclManipulatorTest extends TestCase
{
    private DocumentManager $dm;

    protected function setUp(): void
    {
        $this->dm = DocumentManager::create(null, $this->createConfiguration());
        $this->cleanup();
    }

    public function testFailsWithoutACLSecurityHandler(): void
    {
        $admin = static::createStub(AdminInterface::class);
        $admin
            ->method('getSecurityHandler')
            ->willReturn(new NoopSecurityHandler());

        $objectAclManipulator = new ObjectAclManipulator(static::createStub(ManagerRegistry::class));

        $output = new BufferedOutput();

        $objectAclManipulator->batchConfigureAcls($output, $admin);

        static::assertStringContainsString('Admin class is not configured to use ACL', $output->fetch());
    }

    public function testBatchConfigureAcls(): void
    {
        $this->dm->persist(new DocumentForAcl());
        $this->dm->flush();

        $output = $this->runBatchConfigureAcls();

        static::assertStringContainsString('[TOTAL] generated class ACEs for 1 objects (added 1, updated 0)', $output);

        $this->dm->createQueryBuilder(DocumentForAcl::class)
            ->remove()
            ->getQuery()
            ->execute();
    }

    public function testBatchConfigureAclsAcrossExactlyOneFullBatch(): void
    {
        // BATCH_SIZE = 20 — persisting 20 exactly hits the batch flush branch
        // and the trailing-partial-batch branch must be a no-op.
        for ($i = 0; $i < 20; ++$i) {
            $this->dm->persist(new DocumentForAcl());
        }
        $this->dm->flush();

        $output = $this->runBatchConfigureAcls();

        static::assertStringContainsString('[TOTAL] generated class ACEs for 20 objects (added 20, updated 0)', $output);

        $this->cleanup();
    }

    public function testBatchConfigureAclsAcrossPartialTrailingBatch(): void
    {
        // 21 docs = one full batch (20) + one trailing — must hit both the
        // in-loop flush *and* the "if (count > 0)" tail path.
        for ($i = 0; $i < 21; ++$i) {
            $this->dm->persist(new DocumentForAcl());
        }
        $this->dm->flush();

        $output = $this->runBatchConfigureAcls();

        static::assertStringContainsString('[TOTAL] generated class ACEs for 21 objects (added 21, updated 0)', $output);

        $this->cleanup();
    }

    public function testBatchConfigureAclsEmitsProgressReportEveryProgressInterval(): void
    {
        // PROGRESS_REPORT_INTERVAL = 200 — must emit one mid-run progress line
        // *and* the final [TOTAL] line.
        for ($i = 0; $i < 200; ++$i) {
            $this->dm->persist(new DocumentForAcl());
        }
        $this->dm->flush();

        $output = $this->runBatchConfigureAcls();

        static::assertStringContainsString('generated class ACEs for 200 objects', $output);
        static::assertStringContainsString('[TOTAL] generated class ACEs for 200 objects', $output);

        $this->cleanup();
    }

    public function testBatchConfigureAclsWithSecurityIdentityIncludesObjectOwnerMessage(): void
    {
        $this->dm->persist(new DocumentForAcl());
        $this->dm->flush();

        $output = $this->runBatchConfigureAcls(
            new UserSecurityIdentity('user', 'App\\Entity\\User'),
        );

        static::assertStringContainsString('and set the object owner', $output);

        $this->cleanup();
    }

    /**
     * T2: BadMethodCallException raised during ACL configuration is now wrapped
     * into a ModelManagerException that names the admin code and the original
     * message — previously the wrap was an empty-string ModelManagerException
     * with the cause only reachable via getPrevious().
     */
    public function testBatchConfigureAclsWrapsBadMethodCallExceptionWithContext(): void
    {
        $this->dm->persist(new DocumentForAcl());
        $this->dm->flush();

        $aclSecurityHandler = static::createStub(AclSecurityHandlerInterface::class);
        $aclSecurityHandler
            ->method('findObjectAcls')
            ->willThrowException(new \BadMethodCallException('ACL store missing'));

        $admin = static::createStub(AdminInterface::class);
        $admin->method('getSecurityHandler')->willReturn($aclSecurityHandler);
        $admin->method('getClass')->willReturn(DocumentForAcl::class);
        $admin->method('getCode')->willReturn('admin.code.for.acl');
        $admin->method('getModelManager')->willReturn(static::createStub(ModelManagerInterface::class));

        $managerRegistry = static::createStub(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')->willReturn($this->dm);

        $objectAclManipulator = new ObjectAclManipulator($managerRegistry);

        try {
            $objectAclManipulator->batchConfigureAcls(new BufferedOutput(), $admin);
            static::fail('Expected ModelManagerException to be thrown');
        } catch (ModelManagerException $e) {
            static::assertStringContainsString('admin.code.for.acl', $e->getMessage());
            static::assertStringContainsString('ACL store missing', $e->getMessage());
            static::assertInstanceOf(\BadMethodCallException::class, $e->getPrevious());
        }

        $this->cleanup();
    }

    private function runBatchConfigureAcls(
        ?UserSecurityIdentity $identity = null,
    ): string {
        $aclSecurityHandler = static::createStub(AclSecurityHandlerInterface::class);
        $aclSecurityHandler->method('findObjectAcls')->willReturn(new \SplObjectStorage());
        $aclSecurityHandler->method('buildSecurityInformation')->willReturn([]);
        $aclSecurityHandler->method('createAcl')->willReturn(static::createStub(MutableAclInterface::class));

        $admin = static::createStub(AdminInterface::class);
        $admin->method('getSecurityHandler')->willReturn($aclSecurityHandler);
        $admin->method('getClass')->willReturn(DocumentForAcl::class);
        $admin->method('getModelManager')->willReturn(static::createStub(ModelManagerInterface::class));

        $managerRegistry = static::createStub(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')->willReturn($this->dm);

        $objectAclManipulator = new ObjectAclManipulator($managerRegistry);

        $output = new BufferedOutput();
        $objectAclManipulator->batchConfigureAcls($output, $admin, $identity);

        return $output->fetch();
    }

    private function cleanup(): void
    {
        $this->dm->createQueryBuilder(DocumentForAcl::class)
            ->remove()
            ->getQuery()
            ->execute();
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
