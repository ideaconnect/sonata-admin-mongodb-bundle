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

namespace Sonata\DoctrineMongoDBAdminBundle\Builder;

use Sonata\AdminBundle\Builder\AbstractFormContractor;

/**
 * Mongo-flavoured form contractor.
 *
 * Currently a no-op subclass of {@see AbstractFormContractor}: it exists as a
 * stable extension point so applications can autowire / type-hint against this
 * class and bundle-specific behavior can be added here later without changing
 * the service id.
 */
final class FormContractor extends AbstractFormContractor
{
}
