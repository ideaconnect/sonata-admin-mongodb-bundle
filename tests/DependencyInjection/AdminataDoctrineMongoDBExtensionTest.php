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

namespace IDCT\Adminata\DoctrineMongoDB\Tests\DependencyInjection;

use IDCT\Adminata\DoctrineMongoDB\DependencyInjection\AdminataDoctrineMongoDBExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\Reference;

final class AdminataDoctrineMongoDBExtensionTest extends AbstractExtensionTestCase
{
    public function testEntityManagerSetFactory(): void
    {
        $this->container->setParameter('kernel.bundles', []);
        $this->load();

        $this->assertContainerBuilderHasService('adminata.admin.manager.doctrine_mongodb');
        $this->assertContainerBuilderHasService('adminata.admin.builder.doctrine_mongodb_form');
        $this->assertContainerBuilderHasService('adminata.admin.builder.doctrine_mongodb_list');
        $this->assertContainerBuilderHasService('adminata.admin.guesser.doctrine_mongodb_list');
        $this->assertContainerBuilderHasService('adminata.admin.guesser.doctrine_mongodb_list_chain');
        $this->assertContainerBuilderHasService('adminata.admin.builder.doctrine_mongodb_show');
        $this->assertContainerBuilderHasService('adminata.admin.guesser.doctrine_mongodb_show');
        $this->assertContainerBuilderHasService('adminata.admin.guesser.doctrine_mongodb_show_chain');
        $this->assertContainerBuilderHasService('adminata.admin.builder.doctrine_mongodb_datagrid');
        $this->assertContainerBuilderHasService('adminata.admin.guesser.doctrine_mongodb_datagrid');
        $this->assertContainerBuilderHasService('adminata.admin.guesser.doctrine_mongodb_datagrid_chain');

        $this->assertContainerBuilderHasService('adminata.admin.manager.doctrine_mongodb');
        $this->assertContainerBuilderHasService('adminata.admin.odm.filter.type.boolean');
        $this->assertContainerBuilderHasService('adminata.admin.odm.filter.type.callback');
        $this->assertContainerBuilderHasService('adminata.admin.odm.filter.type.choice');
        $this->assertContainerBuilderHasService('adminata.admin.odm.filter.type.id');
        $this->assertContainerBuilderHasService('adminata.admin.odm.filter.type.model');
        $this->assertContainerBuilderHasService('adminata.admin.odm.filter.type.string');
        $this->assertContainerBuilderHasService('adminata.admin.odm.filter.type.number');
        $this->assertContainerBuilderHasService('adminata.admin.odm.filter.type.date');
        $this->assertContainerBuilderHasService('adminata.admin.odm.filter.type.datetime');
        $this->assertContainerBuilderHasService('adminata.admin.odm.filter.type.date_range');
        $this->assertContainerBuilderHasService('adminata.admin.odm.filter.type.datetime_range');

        $this->assertContainerBuilderHasService('adminata.admin.manipulator.acl.object.doctrine_mongodb');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'adminata.admin.manipulator.acl.object.doctrine_mongodb',
            0,
            new Reference('doctrine_mongodb')
        );
    }

    public function testLoadWiresCustomTemplatesIntoBuildersAndParameter(): void
    {
        $this->container->setParameter('kernel.bundles', []);

        $this->load([
            'templates' => [
                'types' => [
                    'list' => ['custom_marker' => 'custom/list_array.twig.html'],
                    'show' => ['custom_marker' => 'custom/show_array.twig.html'],
                ],
            ],
        ]);

        // adminata_doctrine_mongodb_admin.templates parameter must be exposed
        // (kills the setParameter MethodCallRemoval mutant on line 41).
        $this->assertContainerBuilderHasParameter('adminata_doctrine_mongodb_admin.templates');

        // fixTemplatesConfiguration merges defaults under types.list / types.show
        // so we can't pre-compute the full expected map. Instead, fetch the
        // post-merge value from the parameter and assert the builder
        // definitions point at the SAME, post-merge per-type array.
        $templates = $this->container->getParameter('adminata_doctrine_mongodb_admin.templates');
        \assert(\is_array($templates) && \is_array($templates['types']));

        // Our custom marker must survive the merge — otherwise we'd be
        // asserting equality against an empty default in the next two checks.
        static::assertSame('custom/list_array.twig.html', $templates['types']['list']['custom_marker']);
        static::assertSame('custom/show_array.twig.html', $templates['types']['show']['custom_marker']);

        // The list/show builder definitions must have their *index-1* argument
        // replaced with the configured per-type templates. Mutating the index
        // to 0 or dropping the call must not pass.
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'adminata.admin.builder.doctrine_mongodb_list',
            1,
            $templates['types']['list'],
        );
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'adminata.admin.builder.doctrine_mongodb_show',
            1,
            $templates['types']['show'],
        );
    }

    protected function getContainerExtensions(): array
    {
        return [
            new AdminataDoctrineMongoDBExtension(),
        ];
    }
}
