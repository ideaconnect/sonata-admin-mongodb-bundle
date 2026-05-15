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

namespace Sonata\DoctrineMongoDBAdminBundle\Datagrid;

use Doctrine\ODM\MongoDB\Iterator\Iterator;
use Doctrine\ODM\MongoDB\Query\Builder;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface as BaseProxyQueryInterface;

/**
 * Unifies the query usage with Doctrine MongoDB ODM.
 *
 * Pagination and sorting are stored on the proxy and applied to a clone of
 * the wrapped {@see Builder} inside {@see self::execute()}; the underlying
 * builder is never mutated by setters.
 *
 * @phpstan-template-covariant T of object
 * @phpstan-implements ProxyQueryInterface<T>
 */
final class ProxyQuery implements ProxyQueryInterface
{
    private const SORT_FIELD_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/';

    private const SORT_ORDERS = ['asc', 'desc'];

    private ?string $sortBy = null;

    private ?string $sortOrder = null;

    private ?int $firstResult = null;

    private ?int $maxResults = null;

    public function __construct(private Builder $queryBuilder)
    {
    }

    public function __clone()
    {
        // Deep-clone the wrapped builder so two ProxyQuery clones don't share
        // mutable state. The property isn't readonly because PHPStan's
        // bleedingEdge rejects readonly reassignment in __clone even though
        // PHP 8.3+ allows it.
        $this->queryBuilder = clone $this->queryBuilder;
    }

    public function execute()
    {
        // Always work on a clone so the proxy's settings never leak into the
        // QueryBuilder passed at construction time, and successive execute()
        // calls remain independent.
        $queryBuilder = clone $this->queryBuilder;

        if (null !== $this->sortBy) {
            $queryBuilder->sort($this->sortBy, $this->sortOrder ?? 'asc');
        }

        if (null !== $this->firstResult) {
            $queryBuilder->skip($this->firstResult);
        }

        // setMaxResults(null) means "no limit" — don't call ->limit() at all.
        // setMaxResults(0) is honored as MongoDB's "no limit" idiom.
        if (null !== $this->maxResults) {
            $queryBuilder->limit($this->maxResults);
        }

        $result = $queryBuilder->getQuery()->execute();
        \assert($result instanceof Iterator);

        return $result;
    }

    public function setSortBy(array $parentAssociationMappings, array $fieldMapping): BaseProxyQueryInterface
    {
        $parents = '';

        foreach ($parentAssociationMappings as $mapping) {
            $parents .= $mapping['fieldName'].'.';
        }

        $sortBy = $parents.$fieldMapping['fieldName'];

        // Defense in depth: sort field names come from Doctrine ClassMetadata
        // in the standard Sonata flow, but reject anything that doesn't look
        // like a dot-separated identifier path before handing it to MongoDB,
        // so an attacker who can influence the FieldDescription cannot smuggle
        // operators (e.g. "$where") through the sort stage.
        if (1 !== preg_match(self::SORT_FIELD_PATTERN, $sortBy)) {
            throw new \InvalidArgumentException(\sprintf('Invalid sort field "%s".', $sortBy));
        }

        $this->sortBy = $sortBy;

        return $this;
    }

    public function getSortBy(): ?string
    {
        return $this->sortBy;
    }

    public function setSortOrder(string $sortOrder): BaseProxyQueryInterface
    {
        $normalized = strtolower($sortOrder);

        if (!\in_array($normalized, self::SORT_ORDERS, true)) {
            throw new \InvalidArgumentException(\sprintf(
                'Invalid sort order "%s", expected one of: "%s".',
                $sortOrder,
                implode('", "', self::SORT_ORDERS),
            ));
        }

        $this->sortOrder = $normalized;

        return $this;
    }

    public function getSortOrder(): ?string
    {
        return $this->sortOrder;
    }

    public function getQueryBuilder(): Builder
    {
        return $this->queryBuilder;
    }

    public function setFirstResult(?int $firstResult): BaseProxyQueryInterface
    {
        $this->firstResult = $firstResult;

        return $this;
    }

    public function getFirstResult(): ?int
    {
        return $this->firstResult;
    }

    public function setMaxResults(?int $maxResults): BaseProxyQueryInterface
    {
        $this->maxResults = $maxResults;

        return $this;
    }

    public function getMaxResults(): ?int
    {
        return $this->maxResults;
    }
}
