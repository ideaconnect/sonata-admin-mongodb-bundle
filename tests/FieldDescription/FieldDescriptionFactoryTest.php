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

use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\Types\Type;
use Sonata\DoctrineMongoDBAdminBundle\FieldDescription\FieldDescriptionFactory;
use Sonata\DoctrineMongoDBAdminBundle\Tests\Fixtures\Document\ContainerDocument;

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
}
