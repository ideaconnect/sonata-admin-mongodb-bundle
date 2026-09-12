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

namespace IDCT\Adminata\DoctrineMongoDB\Tests\Builder;

use IDCT\Adminata\Admin\AdminInterface;
use IDCT\Adminata\Datagrid\ListMapper;
use IDCT\Adminata\DoctrineMongoDB\Builder\ListBuilder;
use IDCT\Adminata\DoctrineMongoDB\FieldDescription\FieldDescription;
use IDCT\Adminata\DoctrineMongoDB\Tests\AbstractModelManagerTestCase;
use IDCT\Adminata\DoctrineMongoDB\Tests\ClassMetadataAnnotationTrait;
use IDCT\Adminata\DoctrineMongoDB\Tests\Fixtures\Document\DocumentWithReferences;
use IDCT\Adminata\FieldDescription\FieldDescriptionInterface;
use IDCT\Adminata\FieldDescription\TypeGuesserInterface;
use PHPUnit\Framework\MockObject\Stub;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;

/**
 * @author Andrew Mor-Yaroslavtsev <andrejs@gmail.com>
 */
final class ListBuilderTest extends AbstractModelManagerTestCase
{
    use ClassMetadataAnnotationTrait;

    /**
     * @var TypeGuesserInterface&Stub
     */
    protected $typeGuesser;

    protected ListBuilder $listBuilder;

    /**
     * @var Stub&AdminInterface<object>
     */
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->typeGuesser = static::createStub(TypeGuesserInterface::class);
        $this->admin = static::createStub(AdminInterface::class);

        $this->listBuilder = new ListBuilder($this->typeGuesser, [
            'fakeTemplate' => 'fake',
            FieldDescriptionInterface::TYPE_STRING => '@Adminata/CRUD/list_string.html.twig',
        ]);
    }

    public function testAddListActionField(): void
    {
        $admin = $this->createMock(AdminInterface::class);

        $fieldDescription = new FieldDescription('foo');
        $fieldDescription->setAdmin($admin);

        $list = $this->listBuilder->getBaseList();

        $admin
            ->expects(static::once())
            ->method('addListFieldDescription');

        $this->listBuilder
            ->addField($list, 'actions', $fieldDescription);

        static::assertSame(
            '@Adminata/CRUD/list__action.html.twig',
            $list->get('foo')->getTemplate(),
            'Custom list action field has a default list action template assigned'
        );
    }

    public function testCorrectFixedActionsFieldType(): void
    {
        $admin = $this->createMock(AdminInterface::class);

        $this->typeGuesser
            ->method('guess')
            ->willReturn(
                new TypeGuess('actions', [], Guess::LOW_CONFIDENCE)
            );

        $fieldDescription = new FieldDescription(ListMapper::NAME_ACTIONS);
        $fieldDescription->setAdmin($admin);

        $list = $this->listBuilder->getBaseList();

        $admin
            ->expects(static::once())
            ->method('addListFieldDescription');

        $this->listBuilder->addField($list, null, $fieldDescription);

        static::assertSame(
            ListMapper::TYPE_ACTIONS,
            $list->get(ListMapper::NAME_ACTIONS)->getType(),
            'Standard list _action field has "actions" type'
        );
    }

    public function testFixFieldDescriptionWithFieldMapping(): void
    {
        $documentClass = DocumentWithReferences::class;
        $classMetadata = $this->getMetadataForDocumentWithAttributes($documentClass);

        $fieldDescription = new FieldDescription(
            'name',
            ['sortable' => true],
            $classMetadata->fieldMappings['name']
        );
        $fieldDescription->setAdmin($this->admin);
        $fieldDescription->setType('string');

        $this->admin
            ->method('getClass')
            ->willReturn($documentClass);

        $this->listBuilder->fixFieldDescription($fieldDescription);

        static::assertSame('@Adminata/CRUD/list_string.html.twig', $fieldDescription->getTemplate());
        static::assertSame($classMetadata->getFieldMapping('name'), $fieldDescription->getFieldMapping());
    }

    public function testFixFieldDescriptionException(): void
    {
        $fieldDescription = new FieldDescription('name');
        $fieldDescription->setAdmin($this->admin);

        $this->expectException(\RuntimeException::class);

        $this->listBuilder->fixFieldDescription($fieldDescription);
    }

    public function testBuildFieldThrowsWhenTypeGuesserReturnsNull(): void
    {
        $this->typeGuesser->method('guess')->willReturn(null);

        $fieldDescription = new FieldDescription('foo');
        $fieldDescription->setAdmin($this->admin);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Cannot guess a type/');

        $this->listBuilder->buildField(null, $fieldDescription);
    }

    public function testFixFieldDescriptionDefaultsTemplateAndLabelForAssociationField(): void
    {
        $documentClass = DocumentWithReferences::class;
        $classMetadata = $this->getMetadataForDocumentWithAttributes($documentClass);

        $admin = $this->createMock(AdminInterface::class);
        $admin->expects(static::once())->method('attachAdminClass');

        $fieldDescription = new FieldDescription(
            'embeddedDocument',
            [],
            $classMetadata->fieldMappings['embeddedDocument'],
            $classMetadata->associationMappings['embeddedDocument'],
        );
        $fieldDescription->setAdmin($admin);
        $fieldDescription->setType('string');

        $this->listBuilder->fixFieldDescription($fieldDescription);

        static::assertSame('embeddedDocument', $fieldDescription->getOption('label'));
        static::assertSame('@Adminata/CRUD/list_string.html.twig', $fieldDescription->getTemplate());
    }

    public function testFixFieldDescriptionSortableOptionFalseDoesNotPopulateSortDetails(): void
    {
        $documentClass = DocumentWithReferences::class;
        $classMetadata = $this->getMetadataForDocumentWithAttributes($documentClass);

        $fieldDescription = new FieldDescription(
            'name',
            ['sortable' => false],
            $classMetadata->fieldMappings['name'],
        );
        $fieldDescription->setAdmin($this->admin);
        $fieldDescription->setType('string');

        $this->listBuilder->fixFieldDescription($fieldDescription);

        static::assertNull($fieldDescription->getOption('sort_parent_association_mappings'));
        static::assertNull($fieldDescription->getOption('sort_field_mapping'));
    }

    public function testFixFieldDescriptionAppliesAllSortDefaultsWhenUnset(): void
    {
        // Locks the four `null === …` branches inside the sort-defaults block:
        // sortable → true, sort_parent_association_mappings → field's parents,
        // sort_field_mapping → field's fieldMapping, _sort_order → 'ASC'.
        $documentClass = DocumentWithReferences::class;
        $classMetadata = $this->getMetadataForDocumentWithAttributes($documentClass);

        $parents = [['fieldName' => 'parent']];
        $fieldDescription = new FieldDescription(
            'name',
            [],
            $classMetadata->fieldMappings['name'],
            [],
            $parents,
        );
        $fieldDescription->setAdmin($this->admin);
        $fieldDescription->setType('string');

        $this->admin->method('getClass')->willReturn($documentClass);

        $this->listBuilder->fixFieldDescription($fieldDescription);

        static::assertTrue($fieldDescription->getOption('sortable'));
        static::assertSame($parents, $fieldDescription->getOption('sort_parent_association_mappings'));
        static::assertSame($classMetadata->fieldMappings['name'], $fieldDescription->getOption('sort_field_mapping'));
        static::assertSame('ASC', $fieldDescription->getOption('_sort_order'));
    }

    public function testFixFieldDescriptionPreservesUserProvidedSortOptions(): void
    {
        // Symmetric guard for the same four defaults: when the caller has
        // already supplied a value, fixFieldDescription must NOT overwrite
        // it. An `Identical` → `NotIdentical` mutant on any of the four
        // `null ===` checks would flip the gate and clobber the sentinels.
        $documentClass = DocumentWithReferences::class;
        $classMetadata = $this->getMetadataForDocumentWithAttributes($documentClass);

        $fieldDescription = new FieldDescription(
            'name',
            [
                'sortable' => true,
                'sort_parent_association_mappings' => [['user' => 'parent']],
                'sort_field_mapping' => ['user' => 'mapping'],
                '_sort_order' => 'DESC',
            ],
            $classMetadata->fieldMappings['name'],
        );
        $fieldDescription->setAdmin($this->admin);
        $fieldDescription->setType('string');
        $this->admin->method('getClass')->willReturn($documentClass);

        $this->listBuilder->fixFieldDescription($fieldDescription);

        static::assertTrue($fieldDescription->getOption('sortable'));
        static::assertSame([['user' => 'parent']], $fieldDescription->getOption('sort_parent_association_mappings'));
        static::assertSame(['user' => 'mapping'], $fieldDescription->getOption('sort_field_mapping'));
        static::assertSame('DESC', $fieldDescription->getOption('_sort_order'));
    }

    public function testFixFieldDescriptionSkipsSortDefaultsWhenFieldMappingIsEmpty(): void
    {
        // The whole sort-defaults block is gated on `[] !== getFieldMapping()`.
        // Flip the operator to `===` and we'd default sort options for fields
        // without mappings (e.g. virtual fields), which is exactly the case
        // this guard exists to skip.
        $fieldDescription = new FieldDescription('virtual');
        $fieldDescription->setAdmin($this->admin);
        $fieldDescription->setType('string');

        $this->listBuilder->fixFieldDescription($fieldDescription);

        static::assertNull($fieldDescription->getOption('sortable'));
        static::assertNull($fieldDescription->getOption('sort_parent_association_mappings'));
        static::assertNull($fieldDescription->getOption('sort_field_mapping'));
        static::assertNull($fieldDescription->getOption('_sort_order'));
    }

    public function testActionsHelperTemplatesGetDefaultsAppliedPerAction(): void
    {
        $fieldDescription = new FieldDescription('_action', [
            'actions' => [
                'edit' => [],
                'delete' => ['template' => 'custom.html.twig'],
            ],
        ]);
        $fieldDescription->setAdmin($this->admin);

        $this->listBuilder->fixFieldDescription($fieldDescription);

        $actions = $fieldDescription->getOption('actions');
        static::assertSame('@Adminata/CRUD/list__action_edit.html.twig', $actions['edit']['template']);
        static::assertSame('custom.html.twig', $actions['delete']['template'], 'Pre-set template must not be overwritten');
        static::assertSame('Action', $fieldDescription->getOption('name'));
    }
}
