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

use IDCT\Adminata\Datagrid\Pager as BasePager;

/**
 * Doctrine pager class.
 *
 * @author Jonathan H. Wage <jonwage@gmail.com>
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @phpstan-extends BasePager<ProxyQueryInterface<object>>
 */
final class Pager extends BasePager
{
    private ?int $resultsCount = null;

    public function __clone()
    {
        // Don't carry the original Pager's count into the clone — the clone
        // is meant to wrap a different (or freshly configured) query, so its
        // count is unknown until init() runs. Without this reset, a stale
        // resultsCount from the source instance would slip through as if it
        // had been computed for the clone.
        $this->resultsCount = null;
    }

    public function countResults(): int
    {
        if (null === $this->resultsCount) {
            throw new \LogicException('Pager has not been initialized. Call init() before countResults().');
        }

        return $this->resultsCount;
    }

    public function getCurrentPageResults(): iterable
    {
        $query = $this->getQuery();

        if (null === $query) {
            throw new \RuntimeException('Uninitialized query.');
        }

        return $query->execute();
    }

    public function init(): void
    {
        $query = $this->getQuery();

        if (null === $query) {
            throw new \RuntimeException('Uninitialized query.');
        }

        $this->resultsCount = $this->computeResultsCount($query);

        // Reset to null (proxy's "no pagination" state) — post-5.0 setters
        // store proxy state instead of mutating the shared builder, so 0
        // would just be a slower way to say "skip(0), limit(0)" on every
        // subsequent execute().
        $query->setFirstResult(null);
        $query->setMaxResults(null);

        if (0 === $this->getPage() || 0 === $this->getMaxPerPage()) {
            $this->setLastPage(0);
        } elseif (0 === $this->resultsCount) {
            $this->setLastPage(1);
        } else {
            $offset = ($this->getPage() - 1) * $this->getMaxPerPage();

            $this->setLastPage((int) ceil($this->resultsCount / $this->getMaxPerPage()));

            $query->setFirstResult($offset);
            $query->setMaxResults($this->getMaxPerPage());
        }
    }

    /**
     * @param ProxyQueryInterface<object> $query
     */
    private function computeResultsCount(ProxyQueryInterface $query): int
    {
        // Clone the underlying Builder directly: $queryBuilder->count() flips
        // the builder into TYPE_COUNT, so we need an isolated copy, but we
        // don't need a second ProxyQuery wrapper around it.
        $countBuilder = clone $query->getQueryBuilder();
        $result = $countBuilder->count()->getQuery()->execute();

        \assert(\is_int($result));

        return $result;
    }
}
