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

namespace IDCT\Adminata\DoctrineMongoDB\Filter;

use IDCT\Adminata\Datagrid\ProxyQueryInterface as BaseProxyQueryInterface;
use IDCT\Adminata\DoctrineMongoDB\Datagrid\ProxyQueryInterface;
use IDCT\Adminata\Filter\Filter as BaseFilter;
use IDCT\Adminata\Filter\Model\FilterData;

abstract class Filter extends BaseFilter
{
    final public function apply(BaseProxyQueryInterface $query, FilterData $filterData): void
    {
        if (!$query instanceof ProxyQueryInterface) {
            throw new \TypeError(\sprintf('The query MUST implement "%s".', ProxyQueryInterface::class));
        }

        $field = [] !== $this->getParentAssociationMappings() ? $this->getName() : $this->getFieldName();

        $this->filter($query, $field, $filterData);
    }

    /**
     * @param ProxyQueryInterface<object> $query
     */
    abstract protected function filter(ProxyQueryInterface $query, string $field, FilterData $data): void;
}
