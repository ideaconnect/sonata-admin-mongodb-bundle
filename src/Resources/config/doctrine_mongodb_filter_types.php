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

use IDCT\Adminata\DoctrineMongoDB\Filter\BooleanFilter;
use IDCT\Adminata\DoctrineMongoDB\Filter\CallbackFilter;
use IDCT\Adminata\DoctrineMongoDB\Filter\ChoiceFilter;
use IDCT\Adminata\DoctrineMongoDB\Filter\DateFilter;
use IDCT\Adminata\DoctrineMongoDB\Filter\DateRangeFilter;
use IDCT\Adminata\DoctrineMongoDB\Filter\DateTimeFilter;
use IDCT\Adminata\DoctrineMongoDB\Filter\DateTimeRangeFilter;
use IDCT\Adminata\DoctrineMongoDB\Filter\EmptyFilter;
use IDCT\Adminata\DoctrineMongoDB\Filter\IdFilter;
use IDCT\Adminata\DoctrineMongoDB\Filter\ModelFilter;
use IDCT\Adminata\DoctrineMongoDB\Filter\NumberFilter;
use IDCT\Adminata\DoctrineMongoDB\Filter\StringFilter;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->services()

        ->set('adminata.admin.odm.filter.type.boolean', BooleanFilter::class)
            ->tag('adminata.admin.filter.type')

        ->set('adminata.admin.odm.filter.type.callback', CallbackFilter::class)
            ->tag('adminata.admin.filter.type')

        ->set('adminata.admin.odm.filter.type.choice', ChoiceFilter::class)
            ->tag('adminata.admin.filter.type')

        ->set('adminata.admin.odm.filter.type.empty', EmptyFilter::class)
            ->tag('adminata.admin.filter.type')

        ->set('adminata.admin.odm.filter.type.id', IdFilter::class)
            ->tag('adminata.admin.filter.type')

        ->set('adminata.admin.odm.filter.type.model', ModelFilter::class)
            ->tag('adminata.admin.filter.type')

        ->set('adminata.admin.odm.filter.type.string', StringFilter::class)
            ->tag('adminata.admin.filter.type')

        ->set('adminata.admin.odm.filter.type.number', NumberFilter::class)
            ->tag('adminata.admin.filter.type')

        ->set('adminata.admin.odm.filter.type.date', DateFilter::class)
            ->tag('adminata.admin.filter.type')

        ->set('adminata.admin.odm.filter.type.datetime', DateTimeFilter::class)
            ->tag('adminata.admin.filter.type')

        ->set('adminata.admin.odm.filter.type.date_range', DateRangeFilter::class)
            ->tag('adminata.admin.filter.type')

        ->set('adminata.admin.odm.filter.type.datetime_range', DateTimeRangeFilter::class)
            ->tag('adminata.admin.filter.type');
};
