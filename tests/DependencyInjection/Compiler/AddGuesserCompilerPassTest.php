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

namespace IDCT\Adminata\DoctrineMongoDB\Tests\DependencyInjection\Compiler;

use IDCT\Adminata\DoctrineMongoDB\DependencyInjection\Compiler\AddGuesserCompilerPass;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class AddGuesserCompilerPassTest extends AbstractCompilerPassTestCase
{
    #[DataProvider('provideAddsGuessersCases')]
    public function testAddsGuessers(string $builderServiceId, string $guesserTag): void
    {
        $builderService = new Definition(null, [[]]);
        $this->setDefinition($builderServiceId, $builderService);

        $builderGuesserService = new Definition();
        $builderGuesserService->addTag($guesserTag);
        $this->setDefinition('builder_guesser_id', $builderGuesserService);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            $builderServiceId,
            0,
            [
                new Reference('builder_guesser_id'),
            ]
        );
    }

    /**
     * @phpstan-return iterable<array{string, string}>
     */
    public static function provideAddsGuessersCases(): iterable
    {
        yield 'list_builder' => [
            'adminata.admin.guesser.doctrine_mongodb_list_chain',
            'adminata.admin.guesser.doctrine_mongodb_list',
        ];
        yield 'datagrid_builder' => [
            'adminata.admin.guesser.doctrine_mongodb_datagrid_chain',
            'adminata.admin.guesser.doctrine_mongodb_datagrid',
        ];
        yield 'show_builder' => [
            'adminata.admin.guesser.doctrine_mongodb_show_chain',
            'adminata.admin.guesser.doctrine_mongodb_show',
        ];
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AddGuesserCompilerPass());
    }
}
