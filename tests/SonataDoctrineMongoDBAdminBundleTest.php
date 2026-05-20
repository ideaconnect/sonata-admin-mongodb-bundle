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

namespace Sonata\DoctrineMongoDBAdminBundle\Tests;

use PHPUnit\Framework\TestCase;
use Sonata\DoctrineMongoDBAdminBundle\DependencyInjection\Compiler\AddGuesserCompilerPass;
use Sonata\DoctrineMongoDBAdminBundle\DependencyInjection\Compiler\AddTemplatesCompilerPass;
use Sonata\DoctrineMongoDBAdminBundle\SonataDoctrineMongoDBAdminBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SonataDoctrineMongoDBAdminBundleTest extends TestCase
{
    public function testBuild(): void
    {
        $containerBuilder = new ContainerBuilder();

        $bundle = new SonataDoctrineMongoDBAdminBundle();
        $bundle->build($containerBuilder);

        static::assertNotNull($this->findCompilerPass($containerBuilder, AddGuesserCompilerPass::class));
        static::assertNotNull($this->findCompilerPass($containerBuilder, AddTemplatesCompilerPass::class));
    }

    public function testAddTemplatesCompilerPassRegistersAtPriorityMinusOne(): void
    {
        // The pass MUST be registered with priority -1 so it runs *after*
        // default-priority passes that publish admin definitions. Increment
        // (-> 0) or decrement (-> -2) mutants would silently re-order the
        // compile pipeline.
        $containerBuilder = new ContainerBuilder();
        new SonataDoctrineMongoDBAdminBundle()->build($containerBuilder);

        $passConfig = $containerBuilder->getCompiler()->getPassConfig();
        $buckets = new \ReflectionProperty(PassConfig::class, 'beforeOptimizationPasses')->getValue($passConfig);

        $priority = null;
        foreach ($buckets as $prio => $passes) {
            foreach ($passes as $pass) {
                if ($pass instanceof AddTemplatesCompilerPass) {
                    $priority = $prio;
                    break 2;
                }
            }
        }

        static::assertSame(-1, $priority);
    }

    /** @param class-string $class */
    private function findCompilerPass(ContainerBuilder $container, string $class): ?CompilerPassInterface
    {
        foreach ($container->getCompiler()->getPassConfig()->getPasses() as $pass) {
            if ($pass instanceof $class) {
                return $pass;
            }
        }

        return null;
    }
}
