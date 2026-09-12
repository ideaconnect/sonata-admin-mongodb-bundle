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

namespace IDCT\Adminata\DoctrineMongoDB\FieldDescription;

use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\Types\Type;
use IDCT\Adminata\DoctrineMongoDB\Filter\BooleanFilter;
use IDCT\Adminata\DoctrineMongoDB\Filter\DateFilter;
use IDCT\Adminata\DoctrineMongoDB\Filter\DateTimeFilter;
use IDCT\Adminata\DoctrineMongoDB\Filter\IdFilter;
use IDCT\Adminata\DoctrineMongoDB\Filter\ModelFilter;
use IDCT\Adminata\DoctrineMongoDB\Filter\NumberFilter;
use IDCT\Adminata\DoctrineMongoDB\Filter\StringFilter;
use IDCT\Adminata\DoctrineMongoDB\Model\MissingPropertyMetadataException;
use IDCT\Adminata\FieldDescription\FieldDescriptionInterface;
use IDCT\Adminata\FieldDescription\TypeGuesserInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;

final class FilterTypeGuesser implements TypeGuesserInterface
{
    public function guess(FieldDescriptionInterface $fieldDescription): TypeGuess
    {
        $options = [
            'parent_association_mappings' => $fieldDescription->getParentAssociationMappings(),
            'field_name' => $fieldDescription->getFieldName(),
        ];

        if ([] !== $fieldDescription->getAssociationMapping()) {
            switch ($fieldDescription->getMappingType()) {
                case ClassMetadata::ONE:
                case ClassMetadata::MANY:
                    $options['field_options'] = [
                        'class' => $fieldDescription->getTargetModel(),
                    ];
                    $options['field_name'] = $fieldDescription->getFieldName();
                    $options['mapping_type'] = $fieldDescription->getMappingType();

                    return new TypeGuess(ModelFilter::class, $options, Guess::HIGH_CONFIDENCE);
            }
        }

        if ([] === $fieldDescription->getFieldMapping()) {
            throw new MissingPropertyMetadataException(
                $fieldDescription->getAdmin()->getClass(),
                $fieldDescription->getFieldName()
            );
        }

        // ODM 2.x ships both short (BOOL/INT) and long (BOOLEAN/INTEGER) Type aliases as
        // distinct mapping strings; we match both so legacy mappings keep working without
        // forcing users onto the post-3.0 canonical names.
        return match ($fieldDescription->getMappingType()) {
            Type::BOOL, Type::BOOLEAN => new TypeGuess(BooleanFilter::class, $options, Guess::HIGH_CONFIDENCE),
            Type::TIMESTAMP => new TypeGuess(DateTimeFilter::class, $options, Guess::HIGH_CONFIDENCE),
            Type::DATE, Type::DATE_IMMUTABLE => new TypeGuess(DateFilter::class, $options, Guess::HIGH_CONFIDENCE),
            Type::FLOAT, Type::INT, Type::INTEGER => new TypeGuess(NumberFilter::class, $options, Guess::MEDIUM_CONFIDENCE),
            Type::ID => new TypeGuess(IdFilter::class, $options, Guess::MEDIUM_CONFIDENCE),
            Type::STRING => new TypeGuess(StringFilter::class, $options, Guess::MEDIUM_CONFIDENCE),
            default => new TypeGuess(StringFilter::class, $options, Guess::LOW_CONFIDENCE),
        };
    }
}
