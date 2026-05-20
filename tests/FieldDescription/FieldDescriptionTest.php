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

namespace Sonata\DoctrineMongoDBAdminBundle\Tests\FieldDescription;

use PHPUnit\Framework\TestCase;
use Sonata\DoctrineMongoDBAdminBundle\FieldDescription\FieldDescription;

final class FieldDescriptionTest extends TestCase
{
    public function testAssociationMapping(): void
    {
        $field = new FieldDescription(
            'name',
            [],
            [],
            [
                'type' => 'integer',
                'fieldName' => 'position',
            ]
        );

        static::assertSame('integer', $field->getMappingType());
    }

    public function testGetAssociationMapping(): void
    {
        $associationMapping = [
            'type' => 'integer',
            'fieldName' => 'position',
        ];

        $field = new FieldDescription('name', [], [], $associationMapping);

        static::assertSame($associationMapping, $field->getAssociationMapping());
    }

    public function testSetFieldMappingSetMappingType(): void
    {
        $fieldMapping = [
            'type' => 'integer',
            'fieldName' => 'position',
        ];

        $field = new FieldDescription('name', [], $fieldMapping);

        static::assertSame('integer', $field->getMappingType());
    }

    public function testGetTargetModel(): void
    {
        $associationMapping = [
            'type' => 'integer',
            'fieldName' => 'position',
            'targetDocument' => \stdClass::class,
        ];

        $field = new FieldDescription('name');

        static::assertNull($field->getTargetModel());

        $field = new FieldDescription('name', [], [], $associationMapping);

        static::assertSame(\stdClass::class, $field->getTargetModel());
    }

    public function testIsIdentifierFromFieldMapping(): void
    {
        $fieldMapping = [
            'type' => 'integer',
            'fieldName' => 'position',
            'id' => true,
        ];

        $field = new FieldDescription('name', [], $fieldMapping);

        static::assertTrue($field->isIdentifier());
    }

    public function testGetFieldMapping(): void
    {
        $fieldMapping = [
            'type' => 'integer',
            'fieldName' => 'position',
            'id' => 'someId',
        ];

        $field = new FieldDescription('name', [], $fieldMapping);

        static::assertSame($fieldMapping, $field->getFieldMapping());
    }

    public function testIsIdentifierIsFalseWhenIdKeyMissing(): void
    {
        // Defensive default: fieldMapping with no 'id' key must report false,
        // not whatever happens to be truthy.
        $field = new FieldDescription('name', [], ['type' => 'string', 'fieldName' => 'name']);

        static::assertFalse($field->isIdentifier());
    }

    public function testSetAssociationMappingDoesNotOverwriteAlreadyResolvedMappingType(): void
    {
        // Constructor processes fieldMapping first → mappingType resolves to
        // its 'type'. The associationMapping ??= must NOT clobber it.
        $field = new FieldDescription(
            'name',
            [],
            ['type' => 'integer', 'fieldName' => 'position'],
            ['type' => 'string', 'fieldName' => 'position'],
        );

        static::assertSame('integer', $field->getMappingType());
    }

    public function testSetFieldMappingDoesNotOverwriteAlreadyResolvedMappingType(): void
    {
        // Symmetric guard for setFieldMapping: when mappingType is already
        // resolved (here: from a prior setAssociationMapping call), a second
        // call to setFieldMapping must keep it. FieldDescription is final, so
        // we drive the protected setters via reflection in reverse of the
        // constructor's order to expose this.
        $field = new FieldDescription('name');

        $assoc = new \ReflectionMethod(FieldDescription::class, 'setAssociationMapping');
        $assoc->invoke($field, ['type' => 'string', 'fieldName' => 'position']);

        $fieldMapping = new \ReflectionMethod(FieldDescription::class, 'setFieldMapping');
        $fieldMapping->invoke($field, ['type' => 'integer', 'fieldName' => 'position']);

        static::assertSame('string', $field->getMappingType());
    }

    public function testSetParentAssociationMappingsThrowsOnNonArrayEntry(): void
    {
        // The foreach validates each entry; a `foreach ([])` mutant would
        // silently skip validation and the bad payload would propagate.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('An association mapping must be an array');

        new FieldDescription('name', [], [], [], ['not-an-array']);
    }

    public function testGetParentValue(): void
    {
        $parentAssociationMappings = [
            ['fieldName' => 'parent'],
        ];

        $field = new FieldDescription('name', [], [], [], $parentAssociationMappings);

        $dummyParent = new class {
            public function name(): string
            {
                return 'hi';
            }
        };

        $dummyChild = new class($dummyParent) {
            public function __construct(private object $parent)
            {
            }

            public function parent(): object
            {
                return $this->parent;
            }
        };

        static::assertSame('hi', $field->getValue($dummyChild));
    }
}
