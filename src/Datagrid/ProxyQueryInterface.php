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

namespace IDCT\Adminata\DoctrineMongoDB\Datagrid;

use Doctrine\ODM\MongoDB\Query\Builder;
use IDCT\Adminata\Datagrid\ProxyQueryInterface as BaseProxyQueryInterface;

/**
 * @phpstan-template-covariant T of object
 * @phpstan-extends BaseProxyQueryInterface<T>
 */
interface ProxyQueryInterface extends BaseProxyQueryInterface
{
    public function getQueryBuilder(): Builder;
}
