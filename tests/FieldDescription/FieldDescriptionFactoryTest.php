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

namespace IDCT\Adminata\DoctrineMongoDB\Tests\FieldDescription;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\Types\Type;
use Doctrine\Persistence\ManagerRegistry;
use IDCT\Adminata\DoctrineMongoDB\FieldDescription\FieldDescriptionFactory;
use IDCT\Adminata\DoctrineMongoDB\Tests\Fixtures\Document\ContainerDocument;

final class FieldDescriptionFactoryTest extends RegistryTestCase
{
    public function testCreate(): void
    {
        $fieldDescriptionFactory = new FieldDescriptionFactory($this->registry);

        $fieldDescription = $fieldDescriptionFactory->create(ContainerDocument::class, 'plainField');
        static::assertSame(Type::INT, $fieldDescription->getMappingType());

        $fieldDescription = $fieldDescriptionFactory->create(ContainerDocument::class, 'associatedDocument.plainField');
        static::assertSame(Type::INT, $fieldDescription->getMappingType());

        $fieldDescription = $fieldDescriptionFactory->create(ContainerDocument::class, 'embeddedDocument.plainField');
        static::assertSame(Type::BOOL, $fieldDescription->getMappingType());

        $fieldDescription = $fieldDescriptionFactory->create(ContainerDocument::class, 'embeddedDocument');
        static::assertSame(ClassMetadata::ONE, $fieldDescription->getMappingType());

        $fieldDescription = $fieldDescriptionFactory->create(ContainerDocument::class, 'embeddedDocument');
        static::assertNotSame([], $fieldDescription->getAssociationMapping());
    }

    /**
     * R6: a non-association segment in a dot-separated path used to emit an
     * "undefined array key" warning and let the loop continue with null,
     * crashing deep in ODM. It now throws an InvalidArgumentException that
     * names the bad segment.
     */
    public function testCreateThrowsWhenPathSegmentIsNotAnAssociation(): void
    {
        $fieldDescriptionFactory = new FieldDescriptionFactory($this->registry);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"plainField" is not an association');

        // plainField is a scalar — using it as a parent in a dot-path is illegal.
        $fieldDescriptionFactory->create(ContainerDocument::class, 'plainField.something');
    }

    /**
     * R6 / coverage: an association whose `getAssociationTargetClass()` returns
     * null (no targetDocument configured) throws a distinct error that names
     * the segment and the parent class.
     */
    public function testCreateThrowsWhenAssociationHasNoTargetDocument(): void
    {
        // Minimal AssociationFieldMapping satisfying ODM's @phpstan-type shape.
        // The factory only inspects existence + reads getAssociationTargetClass(),
        // but the property's declared shape requires the full set of bools and
        // identity fields below.
        $mapping = [
            'fieldName' => 'rel',
            'name' => 'rel',
            'isCascadeRemove' => false,
            'isCascadePersist' => false,
            'isCascadeRefresh' => false,
            'isCascadeMerge' => false,
            'isCascadeDetach' => false,
            'isOwningSide' => true,
            'isInverseSide' => false,
            'targetDocument' => null,
            'association' => 1,
        ];

        $metadata = static::createStub(ClassMetadata::class);
        $metadata->associationMappings = ['rel' => $mapping];
        $metadata->method('getAssociationTargetClass')->willReturn(null);

        $dm = static::createStub(DocumentManager::class);
        $dm->method('getClassMetadata')->willReturn($metadata);

        $registry = static::createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($dm);

        $fieldDescriptionFactory = new FieldDescriptionFactory($registry);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Association "rel"');
        $this->expectExceptionMessage('has no targetDocument');

        $fieldDescriptionFactory->create(ContainerDocument::class, 'rel.something');
    }

    /**
     * Coverage: `getDocumentManager` throws RuntimeException when the registry
     * yields nothing (or a non-DocumentManager). Drives the previously
     * uncovered branch of the private helper through the public surface.
     */
    public function testCreateThrowsRuntimeExceptionWhenNoDocumentManagerForClass(): void
    {
        $registry = static::createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        $fieldDescriptionFactory = new FieldDescriptionFactory($registry);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No document manager defined for class');

        $fieldDescriptionFactory->create(ContainerDocument::class, 'plainField');
    }
}
