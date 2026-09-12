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
use Doctrine\Persistence\ManagerRegistry;
use IDCT\Adminata\DoctrineMongoDB\Tests\ClassMetadataAnnotationTrait;
use IDCT\Adminata\DoctrineMongoDB\Tests\Fixtures\Document\AssociatedDocument;
use IDCT\Adminata\DoctrineMongoDB\Tests\Fixtures\Document\ContainerDocument;
use IDCT\Adminata\DoctrineMongoDB\Tests\Fixtures\Document\EmbeddedDocument;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

abstract class RegistryTestCase extends TestCase
{
    use ClassMetadataAnnotationTrait;

    /**
     * @var Stub&ManagerRegistry
     */
    protected $registry;

    protected function setUp(): void
    {
        $this->registry = static::createStub(ManagerRegistry::class);

        $containerDocumentClass = ContainerDocument::class;
        $associatedDocumentClass = AssociatedDocument::class;
        $embeddedDocumentClass = EmbeddedDocument::class;

        $dm = static::createStub(DocumentManager::class);

        $this->registry
            ->method('getManagerForClass')
            ->willReturn($dm);

        $containerDocumentMetadata = $this->getMetadataForDocumentWithAttributes($containerDocumentClass);
        $associatedDocumentMetadata = $this->getMetadataForDocumentWithAttributes($associatedDocumentClass);
        $embeddedDocumentMetadata = $this->getMetadataForDocumentWithAttributes($embeddedDocumentClass);

        $dm
            ->method('getClassMetadata')
            ->willReturnMap(
                [
                    [$containerDocumentClass, $containerDocumentMetadata],
                    [$embeddedDocumentClass, $embeddedDocumentMetadata],
                    [$associatedDocumentClass, $associatedDocumentMetadata],
                ]
            );
    }
}
