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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use IDCT\Adminata\DoctrineMongoDB\Builder\DatagridBuilder;
use IDCT\Adminata\DoctrineMongoDB\Builder\FormContractor;
use IDCT\Adminata\DoctrineMongoDB\Builder\ListBuilder;
use IDCT\Adminata\DoctrineMongoDB\Builder\ShowBuilder;
use IDCT\Adminata\DoctrineMongoDB\Exporter\DataSource;
use IDCT\Adminata\DoctrineMongoDB\FieldDescription\FieldDescriptionFactory;
use IDCT\Adminata\DoctrineMongoDB\FieldDescription\FilterTypeGuesser;
use IDCT\Adminata\DoctrineMongoDB\FieldDescription\TypeGuesser;
use IDCT\Adminata\DoctrineMongoDB\Model\ModelManager;
use IDCT\Adminata\FieldDescription\TypeGuesserChain;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->services()

        ->set('adminata.admin.manager.doctrine_mongodb', ModelManager::class)
            ->tag('adminata.admin.manager')
            ->args([
                service('doctrine_mongodb'),
                service('property_accessor'),
            ])

        ->set('adminata.admin.builder.doctrine_mongodb_form', FormContractor::class)
            ->args([
                service('form.factory'),
                service('form.registry'),
            ])

        ->set('adminata.admin.builder.doctrine_mongodb_list', ListBuilder::class)
            ->args([
                service('adminata.admin.guesser.doctrine_mongodb_list_chain'),
                abstract_arg('templates'),
            ])

        ->set('adminata.admin.guesser.doctrine_mongodb_list', TypeGuesser::class)
            ->tag('adminata.admin.guesser.doctrine_mongodb_list')

        ->set('adminata.admin.guesser.doctrine_mongodb_list_chain', TypeGuesserChain::class)
            ->args([
                [
                    service('adminata.admin.guesser.doctrine_mongodb_list'),
                ],
            ])

        ->set('adminata.admin.builder.doctrine_mongodb_show', ShowBuilder::class)
            ->args([
                service('adminata.admin.guesser.doctrine_mongodb_show_chain'),
                abstract_arg('templates'),
            ])

        ->set('adminata.admin.guesser.doctrine_mongodb_show', TypeGuesser::class)
            ->tag('adminata.admin.guesser.doctrine_mongodb_show')

        ->set('adminata.admin.guesser.doctrine_mongodb_show_chain', TypeGuesserChain::class)
            ->args([
                [
                    service('adminata.admin.guesser.doctrine_mongodb_show'),
                ],
            ])

        ->set('adminata.admin.builder.doctrine_mongodb_datagrid', DatagridBuilder::class)
            ->args([
                service('form.factory'),
                service('adminata.admin.builder.filter.factory'),
                service('adminata.admin.guesser.doctrine_mongodb_datagrid_chain'),
                param('form.type_extension.csrf.enabled'),
            ])

        ->set('adminata.admin.guesser.doctrine_mongodb_datagrid', FilterTypeGuesser::class)
            ->tag('adminata.admin.guesser.doctrine_mongodb_datagrid')

        ->set('adminata.admin.guesser.doctrine_mongodb_datagrid_chain', TypeGuesserChain::class)
            ->args([
                [
                    service('adminata.admin.guesser.doctrine_mongodb_datagrid'),
                ],
            ])

        ->set('adminata.admin.data_source.doctrine_mongodb', DataSource::class)

        ->set('adminata.admin.field_description_factory.doctrine_mongodb', FieldDescriptionFactory::class)
            ->args([
                service('doctrine_mongodb'),
            ]);
};
