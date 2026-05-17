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

namespace Sonata\DoctrineMongoDBAdminBundle\Filter;

use Doctrine\ODM\MongoDB\Query\Expr;
use MongoDB\BSON\Regex;
use Sonata\AdminBundle\Filter\Model\FilterData;
use Sonata\AdminBundle\Form\Type\Operator\ContainsOperatorType;
use Sonata\AdminBundle\Form\Type\Operator\StringOperatorType;
use Sonata\AdminBundle\Search\SearchableFilterInterface;
use Sonata\DoctrineMongoDBAdminBundle\Datagrid\ProxyQueryInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class StringFilter extends Filter implements SearchableFilterInterface
{
    public function getDefaultOptions(): array
    {
        return [
            'field_type' => TextType::class,
            'global_search' => true,
            // When false (default), regex-based operators run case-insensitive
            // — the historical behavior. Set true to flip the 'i' modifier off.
            'case_sensitive' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormOptions(): array
    {
        return [
            'field_type' => $this->getFieldType(),
            'field_options' => $this->getFieldOptions(),
            'label' => $this->getLabel(),
            'operator_type' => ContainsOperatorType::class,
        ];
    }

    public function isSearchEnabled(): bool
    {
        return true === $this->getOption('global_search');
    }

    protected function filter(ProxyQueryInterface $query, string $field, FilterData $data): void
    {
        if (!$data->hasValue()) {
            return;
        }

        $raw = $data->getValue();

        // Submitted shape must be a scalar (or null) — anything else (array,
        // object) means a malformed payload, never something we can string-cast
        // into a regex. Skip silently rather than fatal in `(string) []`.
        if (null === $raw || !\is_scalar($raw)) {
            return;
        }

        $value = trim((string) $raw);

        if ('' === $value) {
            return;
        }

        $type = $data->getType() ?? ContainsOperatorType::TYPE_CONTAINS;

        $obj = $query->getQueryBuilder();
        if (self::CONDITION_OR === $this->condition) {
            $obj = $query->getQueryBuilder()->expr();
        }

        // Anchored variants (STARTS_WITH/ENDS_WITH) require the escaped pattern
        // bracketed by ^ or $; everything else hands the raw escape through.
        // Regex flags are computed once: case-insensitive unless the user opts out.
        $flags = true === $this->getOption('case_sensitive') ? '' : 'i';
        $escaped = preg_quote($value, '/');

        // Each arm is a closure so we can detect "unknown operator" via the
        // null default and short-circuit before flipping the filter to
        // "active" on a query the builder never saw.
        // Match against the int constants shared by ContainsOperatorType and
        // StringOperatorType — both expose the same numeric value for TYPE_EQUAL,
        // TYPE_CONTAINS, TYPE_NOT_CONTAINS, and StringOperatorType adds 4/5/6.
        $apply = match ($type) {
            ContainsOperatorType::TYPE_EQUAL => static fn () => $obj->field($field)->equals($value),
            StringOperatorType::TYPE_NOT_EQUAL => static fn () => $obj->field($field)->notEqual($value),
            ContainsOperatorType::TYPE_CONTAINS => static fn () => $obj->field($field)->equals(new Regex($escaped, $flags)),
            ContainsOperatorType::TYPE_NOT_CONTAINS => static fn () => $obj->field($field)->not(new Regex($escaped, $flags)),
            StringOperatorType::TYPE_STARTS_WITH => static fn () => $obj->field($field)->equals(new Regex('^'.$escaped, $flags)),
            StringOperatorType::TYPE_ENDS_WITH => static fn () => $obj->field($field)->equals(new Regex($escaped.'$', $flags)),
            default => null,
        };

        if (null === $apply) {
            return;
        }

        $apply();

        if (self::CONDITION_OR === $this->condition) {
            \assert($obj instanceof Expr);
            $query->getQueryBuilder()->addOr($obj);
        }

        $this->setActive(true);
    }
}
