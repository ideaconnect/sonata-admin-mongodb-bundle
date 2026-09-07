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

namespace Sonata\DoctrineMongoDBAdminBundle\Exporter;

use Sonata\AdminBundle\Datagrid\ProxyQueryInterface as BaseProxyQueryInterface;
use Sonata\AdminBundle\Exporter\DataSourceInterface;
use Sonata\AdminBundle\Exporter\Source\DoctrineODMQuerySourceIterator;
use Sonata\DoctrineMongoDBAdminBundle\Datagrid\ProxyQueryInterface;

final readonly class DataSource implements DataSourceInterface
{
    /**
     * @param bool $hydrate When false, exports skip ODM hydration and stream raw arrays
     *                      straight from the driver. Materially faster on wide collections
     *                      but `$object->getX()` accessors on the FieldDescription side
     *                      will not be available — only mapped field names. Defaults to
     *                      `true` to preserve the historical behavior.
     */
    public function __construct(private bool $hydrate = true)
    {
    }

    public function createIterator(BaseProxyQueryInterface $query, array $fields): \Iterator
    {
        if (!$query instanceof ProxyQueryInterface) {
            throw new \TypeError(\sprintf(
                'Argument 1 passed to "%s()" MUST be an instance of "%s", instance of "%s" given.',
                __METHOD__,
                ProxyQueryInterface::class,
                $query::class
            ));
        }

        $query->setFirstResult(null);
        $query->setMaxResults(null);

        // Clone the builder so flipping the hydrate flag for this export doesn't
        // leak into the shared QueryBuilder the proxy was constructed with.
        $builder = clone $query->getQueryBuilder();
        $builder->hydrate($this->hydrate);

        return new DoctrineODMQuerySourceIterator($builder->getQuery(), $fields);
    }
}
