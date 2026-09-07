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

use Sonata\AdminBundle\Filter\Model\FilterData;
use Sonata\AdminBundle\Form\Type\BooleanType;
use Sonata\DoctrineMongoDBAdminBundle\Datagrid\ProxyQueryInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

/**
 * "Is the field empty?" filter for MongoDB documents.
 *
 * MongoDB treats a query like `{field: null}` as matching documents where the
 * field is `null` OR absent entirely — that's the same meaning users expect
 * from "empty" in an admin UI, so this filter uses that semantic for YES
 * (empty) and the negation for NO (has a value).
 */
final class EmptyFilter extends Filter
{
    public function getDefaultOptions(): array
    {
        return [
            'field_type' => BooleanType::class,
            'operator_type' => HiddenType::class,
            'operator_options' => [],
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
            'operator_type' => $this->getOption('operator_type'),
            'operator_options' => $this->getOption('operator_options'),
            'label' => $this->getLabel(),
        ];
    }

    protected function filter(ProxyQueryInterface $query, string $field, FilterData $data): void
    {
        if (!$data->hasValue()) {
            return;
        }

        $value = $data->getValue();

        if (!\in_array($value, [BooleanType::TYPE_NO, BooleanType::TYPE_YES], true)) {
            return;
        }

        $queryBuilder = $query->getQueryBuilder();

        if (BooleanType::TYPE_YES === $value) {
            // Mongo's null-equality covers both `null` values and missing fields.
            $queryBuilder->field($field)->equals(null);
        } else {
            $queryBuilder->field($field)->notEqual(null);
        }

        $this->setActive(true);
    }
}
