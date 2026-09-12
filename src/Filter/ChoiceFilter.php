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

use IDCT\Adminata\DoctrineMongoDB\Datagrid\ProxyQueryInterface;
use IDCT\Adminata\Filter\Model\FilterData;
use IDCT\Adminata\Form\Type\Operator\EqualOperatorType;

final class ChoiceFilter extends Filter
{
    public function getDefaultOptions(): array
    {
        return [
            'operator_type' => EqualOperatorType::class,
            'operator_options' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormOptions(): array
    {
        return [
            'operator_type' => $this->getOption('operator_type'),
            'operator_options' => $this->getOption('operator_options'),
            'field_type' => $this->getFieldType(),
            'field_options' => $this->getFieldOptions(),
            'label' => $this->getLabel(),
        ];
    }

    protected function filter(ProxyQueryInterface $query, string $field, FilterData $data): void
    {
        if (!$data->hasValue()) {
            return;
        }

        $queryBuilder = $query->getQueryBuilder();

        $value = $data->getValue();

        if (\is_array($value)) {
            if (0 === \count($value)) {
                return;
            }

            if ($data->isType(EqualOperatorType::TYPE_NOT_EQUAL)) {
                $queryBuilder->field($field)->notIn($value);
            } else {
                $queryBuilder->field($field)->in($value);
            }

            $this->setActive(true);
        } else {
            if ('' === $value || null === $value || false === $value) {
                return;
            }

            if ($data->isType(EqualOperatorType::TYPE_NOT_EQUAL)) {
                $queryBuilder->field($field)->notEqual($value);
            } else {
                $queryBuilder->field($field)->equals($value);
            }

            $this->setActive(true);
        }
    }
}
