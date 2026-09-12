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

use Doctrine\Bundle\MongoDBBundle\Form\Type\DocumentType;
use Doctrine\Common\Collections\Collection;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use IDCT\Adminata\DoctrineMongoDB\Datagrid\ProxyQueryInterface;
use IDCT\Adminata\Filter\Model\FilterData;
use IDCT\Adminata\Form\Type\Operator\EqualOperatorType;
use MongoDB\BSON\ObjectId;
use MongoDB\Driver\Exception\InvalidArgumentException;

final class ModelFilter extends Filter
{
    public function getDefaultOptions(): array
    {
        return [
            'mapping_type' => false,
            'field_type' => DocumentType::class,
            'field_options' => [],
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

        if ($value instanceof Collection) {
            $data = $data->changeValue($value->toArray());
        }

        $field = $this->getIdentifierField($field);

        if (\is_array($data->getValue())) {
            $this->handleMultiple($query, $field, $data);
        } else {
            $this->handleScalar($query, $field, $data);
        }
    }

    /**
     * @param ProxyQueryInterface<object> $query
     */
    protected function handleMultiple(ProxyQueryInterface $query, string $field, FilterData $data): void
    {
        if (0 === \count($data->getValue())) {
            return;
        }

        $ids = [];
        foreach ($data->getValue() as $value) {
            if (!\is_object($value) || !method_exists($value, 'getId')) {
                continue;
            }

            $ids[] = self::fixIdentifier($value->getId());
        }

        if ([] === $ids) {
            return;
        }

        if ($data->isType(EqualOperatorType::TYPE_NOT_EQUAL)) {
            $query->getQueryBuilder()->field($field)->notIn($ids);
        } else {
            $query->getQueryBuilder()->field($field)->in($ids);
        }

        $this->setActive(true);
    }

    /**
     * @param ProxyQueryInterface<object> $query
     */
    protected function handleScalar(ProxyQueryInterface $query, string $field, FilterData $data): void
    {
        $value = $data->getValue();

        // Ignore non-object values (e.g. submitted strings/null from malformed
        // payloads) — the model filter only knows how to compare against a
        // domain object exposing getId().
        if (!\is_object($value) || !method_exists($value, 'getId')) {
            return;
        }

        $id = self::fixIdentifier($value->getId());

        if ($data->isType(EqualOperatorType::TYPE_NOT_EQUAL)) {
            $query->getQueryBuilder()->field($field)->notEqual($id);
        } else {
            $query->getQueryBuilder()->field($field)->equals($id);
        }

        $this->setActive(true);
    }

    /**
     * Return an ObjectId when $id is the string form of one, otherwise return
     * the identifier as-is (string or int). Empty / null / array / other
     * shapes are rejected: previously, fixIdentifier would echo them back
     * silently and the caller would push them into an `equals`/`in` query
     * where they'd match nothing or match unrelated documents.
     */
    protected static function fixIdentifier(mixed $id): string|int|ObjectId
    {
        if (\is_int($id)) {
            return $id;
        }

        if (!\is_string($id) || '' === $id) {
            throw new \InvalidArgumentException(\sprintf(
                'Expected identifier to be a non-empty string or int, got "%s".',
                get_debug_type($id),
            ));
        }

        try {
            return new ObjectId($id);
        } catch (InvalidArgumentException) {
            return $id;
        }
    }

    /**
     * Get identifier field name based on mapping type.
     */
    private function getIdentifierField(string $field): string
    {
        $fieldMapping = $this->getFieldMapping();

        return match ($fieldMapping['storeAs'] ?? null) {
            ClassMetadata::REFERENCE_STORE_AS_REF => $field.'.id',
            ClassMetadata::REFERENCE_STORE_AS_ID => $field,
            ClassMetadata::REFERENCE_STORE_AS_DB_REF_WITH_DB,
            ClassMetadata::REFERENCE_STORE_AS_DB_REF => $field.'.$id',
            default => $field.'._id',
        };
    }
}
