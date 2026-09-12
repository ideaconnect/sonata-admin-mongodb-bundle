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

use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use IDCT\Adminata\Admin\AdminInterface;
use IDCT\Adminata\FieldDescription\FieldDescriptionCollection;
use IDCT\Adminata\FieldDescription\FieldDescriptionInterface;
use IDCT\Adminata\FieldDescription\TypeGuesserInterface;
use IDCT\Adminata\DoctrineMongoDB\Builder\ShowBuilder;
use IDCT\Adminata\DoctrineMongoDB\FieldDescription\FieldDescription;
use IDCT\Adminata\DoctrineMongoDB\Tests\ClassMetadataAnnotationTrait;
use Symfony\Component\Form\Guess\TypeGuess;

final class ShowBuilderTest extends TestCase
{
    use ClassMetadataAnnotationTrait;

    /**
     * @var Stub&TypeGuesserInterface
     */
    private TypeGuesserInterface $guesser;

    private ShowBuilder $showBuilder;

    protected function setUp(): void
    {
        $this->guesser = static::createStub(TypeGuesserInterface::class);

        $this->showBuilder = new ShowBuilder(
            $this->guesser,
            [
                'fakeTemplate' => 'fake',
                FieldDescriptionInterface::TYPE_MANY_TO_ONE => '@Adminata/CRUD/Association/show_many_to_one.html.twig',
            ]
        );
    }

    public function testAddFieldNoType(): void
    {
        $admin = $this->createMock(AdminInterface::class);
        $typeGuess = static::createStub(TypeGuess::class);

        $fieldDescription = new FieldDescription('FakeName', [], ['type' => ClassMetadata::ONE]);
        $fieldDescription->setAdmin($admin);

        $admin->expects(static::once())->method('attachAdminClass');
        $admin->expects(static::once())->method('addShowFieldDescription');

        $typeGuess->method('getType')->willReturn('fakeType');

        $this->guesser->method('guess')->willReturn($typeGuess);

        $this->showBuilder->addField(
            new FieldDescriptionCollection(),
            null,
            $fieldDescription
        );

        static::assertSame('fakeType', $fieldDescription->getType());
    }

    public function testAddFieldWithType(): void
    {
        $admin = $this->createMock(AdminInterface::class);

        $fieldDescription = new FieldDescription('FakeName');
        $fieldDescription->setAdmin($admin);

        $admin->expects(static::once())->method('addShowFieldDescription');

        $this->showBuilder->addField(
            new FieldDescriptionCollection(),
            'someType',
            $fieldDescription
        );

        static::assertSame('someType', $fieldDescription->getType());
    }

    public function testFixFieldDescriptionException(): void
    {
        $admin = static::createStub(AdminInterface::class);

        $fieldDescription = new FieldDescription('name');
        $fieldDescription->setAdmin($admin);

        $this->expectException(\RuntimeException::class);

        $this->showBuilder->fixFieldDescription($fieldDescription);
    }

    public function testAddFieldThrowsWhenTypeGuesserReturnsNull(): void
    {
        $admin = static::createStub(AdminInterface::class);
        $this->guesser->method('guess')->willReturn(null);

        $fieldDescription = new FieldDescription('foo');
        $fieldDescription->setAdmin($admin);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Cannot guess a type/');

        $this->showBuilder->addField(new FieldDescriptionCollection(), null, $fieldDescription);
    }

    public function testFixFieldDescriptionDefaultsTemplateAndLabel(): void
    {
        $admin = static::createStub(AdminInterface::class);

        $fieldDescription = new FieldDescription('FakeName');
        $fieldDescription->setAdmin($admin);
        $fieldDescription->setType(FieldDescriptionInterface::TYPE_MANY_TO_ONE);

        $this->showBuilder->fixFieldDescription($fieldDescription);

        static::assertSame('FakeName', $fieldDescription->getOption('label'));
        static::assertSame(
            '@Adminata/CRUD/Association/show_many_to_one.html.twig',
            $fieldDescription->getTemplate(),
        );
    }

    public function testGetBaseListReturnsAnEmptyFieldDescriptionCollection(): void
    {
        // Return type already enforces FieldDescriptionCollection; assert
        // shape (empty by default) so the test exercises observable behavior.
        static::assertCount(0, $this->showBuilder->getBaseList()->getElements());
    }

    public function testAddFieldAppendsToList(): void
    {
        // Locks the trailing `$list->add($fieldDescription)` — without it
        // addShowFieldDescription would still fire on the admin but the
        // collection passed by the caller would silently come back empty.
        $admin = static::createStub(AdminInterface::class);

        $fieldDescription = new FieldDescription('appended');
        $fieldDescription->setAdmin($admin);

        $list = new FieldDescriptionCollection();
        $this->showBuilder->addField($list, 'someType', $fieldDescription);

        static::assertTrue($list->has('appended'));
        static::assertSame($fieldDescription, $list->get('appended'));
    }
}
