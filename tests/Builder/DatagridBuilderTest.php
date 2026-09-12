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
use IDCT\Adminata\Datagrid\Datagrid;
use IDCT\Adminata\Datagrid\DatagridInterface;
use IDCT\Adminata\Datagrid\Pager;
use IDCT\Adminata\Datagrid\SimplePager;
use IDCT\Adminata\DoctrineMongoDB\Builder\DatagridBuilder;
use IDCT\Adminata\DoctrineMongoDB\Datagrid\ProxyQueryInterface;
use IDCT\Adminata\DoctrineMongoDB\FieldDescription\FieldDescription;
use IDCT\Adminata\DoctrineMongoDB\Filter\ModelFilter;
use IDCT\Adminata\DoctrineMongoDB\Tests\ClassMetadataAnnotationTrait;
use IDCT\Adminata\DoctrineMongoDB\Tests\Fixtures\Document\DocumentWithReferences;
use IDCT\Adminata\FieldDescription\FieldDescriptionCollection;
use IDCT\Adminata\FieldDescription\TypeGuesserInterface;
use IDCT\Adminata\Filter\FilterFactoryInterface;
use IDCT\Adminata\Translator\FormLabelTranslatorStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;

final class DatagridBuilderTest extends TestCase
{
    use ClassMetadataAnnotationTrait;

    private DatagridBuilder $datagridBuilder;

    /**
     * @var Stub&TypeGuesserInterface
     */
    private TypeGuesserInterface $typeGuesser;

    /**
     * @var Stub&FormFactoryInterface
     */
    private FormFactoryInterface $formFactory;

    /**
     * @var Stub&FilterFactoryInterface
     */
    private FilterFactoryInterface $filterFactory;

    /**
     * @var Stub&AdminInterface<object>
     */
    private AdminInterface $admin;

    protected function setUp(): void
    {
        $this->formFactory = static::createStub(FormFactoryInterface::class);
        $this->filterFactory = static::createStub(FilterFactoryInterface::class);
        $this->typeGuesser = static::createStub(TypeGuesserInterface::class);

        $this->datagridBuilder = new DatagridBuilder(
            $this->formFactory,
            $this->filterFactory,
            $this->typeGuesser
        );

        $this->admin = static::createStub(AdminInterface::class);
    }

    /**
     * @phpstan-param class-string $pager
     */
    #[DataProvider('provideGetBaseDatagridCases')]
    public function testGetBaseDatagrid(string $pagerType, string $pager): void
    {
        $proxyQuery = static::createStub(ProxyQueryInterface::class);
        $fieldDescription = new FieldDescriptionCollection();
        $formBuilder = static::createStub(FormBuilderInterface::class);

        $this->admin->method('getPagerType')->willReturn($pagerType);
        $this->admin->method('createQuery')->willReturn($proxyQuery);
        $this->admin->method('getList')->willReturn($fieldDescription);

        $this->formFactory->method('createNamedBuilder')->willReturn($formBuilder);

        $datagrid = $this->datagridBuilder->getBaseDatagrid($this->admin);
        static::assertInstanceOf(Datagrid::class, $datagrid);
        static::assertInstanceOf($pager, $datagrid->getPager());
    }

    /**
     * @phpstan-return iterable<array-key, array{string, class-string}>
     */
    public static function provideGetBaseDatagridCases(): iterable
    {
        yield 'simple' => [
            Pager::TYPE_SIMPLE,
            SimplePager::class,
        ];
        yield 'default' => [
            Pager::TYPE_DEFAULT,
            Pager::class,
        ];
    }

    public function testFixFieldDescription(): void
    {
        $documentClass = DocumentWithReferences::class;
        $classMetadata = $this->getMetadataForDocumentWithAttributes($documentClass);

        $fieldDescription = new FieldDescription('name', [], $classMetadata->fieldMappings['name']);
        $fieldDescription->setAdmin($this->admin);

        $this->admin
            ->method('getClass')
            ->willReturn($documentClass);

        $this->datagridBuilder->fixFieldDescription($fieldDescription);

        static::assertSame($classMetadata->fieldMappings['name'], $fieldDescription->getOption('field_mapping'));
    }

    public function testFixFieldDescriptionWithAssociationMapping(): void
    {
        $documentClass = DocumentWithReferences::class;
        $classMetadata = $this->getMetadataForDocumentWithAttributes($documentClass);

        $admin = $this->createMock(AdminInterface::class);

        $fieldDescription = new FieldDescription(
            'embeddedDocument',
            [],
            $classMetadata->fieldMappings['embeddedDocument'],
            $classMetadata->associationMappings['embeddedDocument']
        );
        $fieldDescription->setAdmin($admin);

        $admin
            ->expects(static::once())
            ->method('attachAdminClass');

        $this->datagridBuilder->fixFieldDescription($fieldDescription);

        static::assertSame($classMetadata->associationMappings['embeddedDocument'], $fieldDescription->getOption('association_mapping'));
    }

    public function testAddFilterNoType(): void
    {
        $admin = $this->createMock(AdminInterface::class);
        $admin
            ->expects(static::once())
            ->method('addFilterFieldDescription');

        $datagrid = $this->createMock(DatagridInterface::class);
        $guessType = new TypeGuess(ModelFilter::class, [
            'guess_option' => 'guess_value',
            'guess_array_option' => [
                'guess_array_value',
            ],
        ], Guess::VERY_HIGH_CONFIDENCE);

        $fieldDescription = new FieldDescription('test');
        $fieldDescription->setAdmin($admin);

        $this->typeGuesser->method('guess')->willReturn($guessType);

        $admin->method('getCode')->willReturn('someFakeCode');

        $filterFactory = $this->createMock(FilterFactoryInterface::class);
        $filterFactory->method('create')->willReturn(new ModelFilter());

        $admin->method('getLabelTranslatorStrategy')->willReturn(new FormLabelTranslatorStrategy());

        $datagrid
            ->expects(static::once())
            ->method('addFilter')
            ->with(static::isInstanceOf(ModelFilter::class));

        $filterFactory
            ->expects(static::once())
            ->method('create')
            ->with('test', ModelFilter::class);

        $datagridBuilder = new DatagridBuilder(
            $this->formFactory,
            $filterFactory,
            $this->typeGuesser,
        );

        $datagridBuilder->addFilter(
            $datagrid,
            null,
            $fieldDescription
        );

        static::assertSame('guess_value', $fieldDescription->getOption('guess_option'));
        static::assertSame(['guess_array_value'], $fieldDescription->getOption('guess_array_option'));
    }

    public function testAddFilterWithType(): void
    {
        $admin = $this->createMock(AdminInterface::class);
        $admin
            ->expects(static::once())
            ->method('addFilterFieldDescription');

        $datagrid = $this->createMock(DatagridInterface::class);

        $fieldDescription = new FieldDescription('test');
        $fieldDescription->setAdmin($admin);

        $this->filterFactory->method('create')->willReturn(new ModelFilter());

        $admin->method('getLabelTranslatorStrategy')->willReturn(new FormLabelTranslatorStrategy());

        $datagrid
            ->expects(static::once())
            ->method('addFilter')
            ->with(static::isInstanceOf(ModelFilter::class));

        $this->datagridBuilder->addFilter(
            $datagrid,
            ModelFilter::class,
            $fieldDescription
        );

        static::assertSame(ModelFilter::class, $fieldDescription->getType());
    }

    public function testFixFieldDescriptionSetsFieldName(): void
    {
        $fieldDescription = new FieldDescription('name', [], [], [], [], 'fieldName');

        $this->datagridBuilder->fixFieldDescription($fieldDescription);

        static::assertSame('fieldName', $fieldDescription->getOption('field_name'));
    }

    public function testAddFilterThrowsWhenTypeIsNullAndGuesserReturnsNull(): void
    {
        $this->typeGuesser->method('guess')->willReturn(null);

        $admin = static::createStub(AdminInterface::class);

        $fieldDescription = new FieldDescription('test');
        $fieldDescription->setAdmin($admin);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Cannot guess a type/');

        $this->datagridBuilder->addFilter(
            static::createStub(DatagridInterface::class),
            null,
            $fieldDescription,
        );
    }

    public function testGetBaseDatagridThrowsWhenAdminQueryIsForeign(): void
    {
        $admin = static::createStub(AdminInterface::class);
        $admin->method('getPagerType')->willReturn(Pager::TYPE_DEFAULT);
        // Return a foreign ProxyQueryInterface, not ours — TypeError expected.
        $admin->method('createQuery')->willReturn(
            static::createStub(\IDCT\Adminata\Datagrid\ProxyQueryInterface::class),
        );
        $this->formFactory
            ->method('createNamedBuilder')
            ->willReturn(static::createStub(FormBuilderInterface::class));

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('MUST implement');

        $this->datagridBuilder->getBaseDatagrid($admin);
    }

    public function testGetBaseDatagridThrowsForUnknownPagerType(): void
    {
        $admin = static::createStub(AdminInterface::class);
        $admin->method('getPagerType')->willReturn('unknown-pager-type');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unknown pager type/');

        $this->datagridBuilder->getBaseDatagrid($admin);
    }

    public function testGetBaseDatagridDisablesCsrfWhenEnabledByDefault(): void
    {
        // The constructor default is csrfTokenEnabled=true. When enabled, the
        // form is built with `csrf_protection => false` (the default form
        // shouldn't carry tokens). Mutants flipping the default to false
        // (skip the entire if block) or flipping the literal to true (turn
        // CSRF *on* on the filter form) both must fail this assertion.
        $proxyQuery = static::createStub(ProxyQueryInterface::class);
        $admin = static::createStub(AdminInterface::class);
        $admin->method('getPagerType')->willReturn(Pager::TYPE_DEFAULT);
        $admin->method('createQuery')->willReturn($proxyQuery);
        $admin->method('getList')->willReturn(new FieldDescriptionCollection());

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory
            ->expects(static::once())
            ->method('createNamedBuilder')
            ->with('filter', static::anything(), [], ['csrf_protection' => false])
            ->willReturn(static::createStub(FormBuilderInterface::class));

        new DatagridBuilder($formFactory, $this->filterFactory, $this->typeGuesser)
            ->getBaseDatagrid($admin);
    }

    public function testGetBaseDatagridDoesNotInjectCsrfOptionWhenDisabled(): void
    {
        // Symmetric guard: when csrfTokenEnabled=false, no csrf_protection key
        // should leak into the form options. The TrueValue mutant on the
        // constructor default would force the option in unintentionally.
        $proxyQuery = static::createStub(ProxyQueryInterface::class);
        $admin = static::createStub(AdminInterface::class);
        $admin->method('getPagerType')->willReturn(Pager::TYPE_DEFAULT);
        $admin->method('createQuery')->willReturn($proxyQuery);
        $admin->method('getList')->willReturn(new FieldDescriptionCollection());

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory
            ->expects(static::once())
            ->method('createNamedBuilder')
            ->with('filter', static::anything(), [], [])
            ->willReturn(static::createStub(FormBuilderInterface::class));

        new DatagridBuilder($formFactory, $this->filterFactory, $this->typeGuesser, false)
            ->getBaseDatagrid($admin);
    }

    public function testFixFieldDescriptionKeepsUserProvidedMappingOption(): void
    {
        // The defaultable-mappings loop uses `&&`: only fill the option when
        // the field already has a non-empty mapping AND the option is null.
        // An OR mutant would overwrite a user-supplied option whenever the
        // mapping is non-empty — pre-seed the option to a sentinel and assert
        // it survives.
        $documentClass = DocumentWithReferences::class;
        $classMetadata = $this->getMetadataForDocumentWithAttributes($documentClass);

        $sentinel = ['user' => 'override'];
        $fieldDescription = new FieldDescription(
            'name',
            ['field_mapping' => $sentinel],
            $classMetadata->fieldMappings['name'],
        );
        $fieldDescription->setAdmin($this->admin);
        $this->admin->method('getClass')->willReturn($documentClass);

        $this->datagridBuilder->fixFieldDescription($fieldDescription);

        static::assertSame($sentinel, $fieldDescription->getOption('field_mapping'));
    }

    public function testAddFilterNoTypeRegistersTypeAndMergesArrayOption(): void
    {
        // Triple-purpose test: locks
        //   (3) setType($type) is actually called (covers MethodCallRemoval),
        //   (4) array_merge order — user override on the right wins,
        //   (6) mergeOption('field_options', ['required' => false]) lands on
        //       the *options array sent to the filter factory*.
        $admin = $this->createMock(AdminInterface::class);
        $admin->expects(static::once())->method('addFilterFieldDescription');
        $admin->method('getCode')->willReturn('someFakeCode');
        $admin->method('getLabelTranslatorStrategy')->willReturn(new FormLabelTranslatorStrategy());

        $datagrid = $this->createMock(DatagridInterface::class);
        $datagrid->expects(static::once())->method('addFilter');

        $guessType = new TypeGuess(ModelFilter::class, [
            'guess_array_option' => ['from_guesser'],
        ], Guess::VERY_HIGH_CONFIDENCE);

        $fieldDescription = new FieldDescription('test', [
            'guess_array_option' => ['from_user'],
        ]);
        $fieldDescription->setAdmin($admin);

        $this->typeGuesser->method('guess')->willReturn($guessType);

        $filterFactory = $this->createMock(FilterFactoryInterface::class);
        $capturedOptions = null;
        $filterFactory
            ->expects(static::once())
            ->method('create')
            ->with('test', ModelFilter::class, static::callback(
                static function (array $options) use (&$capturedOptions): bool {
                    $capturedOptions = $options;

                    return true;
                },
            ))
            ->willReturn(new ModelFilter());

        new DatagridBuilder($this->formFactory, $filterFactory, $this->typeGuesser)
            ->addFilter($datagrid, null, $fieldDescription);

        static::assertSame(ModelFilter::class, $fieldDescription->getType());
        // array_merge($guesser, $user) — guesser values come first, then user
        // entries OVERWRITE matching keys; here both arrays have a single
        // numeric entry and `array_merge` re-indexes so we get both.
        static::assertSame(
            ['from_guesser', 'from_user'],
            $fieldDescription->getOption('guess_array_option'),
        );
        // mergeOption('field_options', ['required' => false]) — locks
        // mutants 6/7/8 (FalseValue, ArrayItemRemoval, MethodCallRemoval).
        static::assertIsArray($capturedOptions);
        static::assertArrayHasKey('field_options', $capturedOptions);
        static::assertIsArray($capturedOptions['field_options']);
        static::assertFalse($capturedOptions['field_options']['required']);
    }

    public function testAddFilterCallsFixFieldDescriptionSettingFieldNameOption(): void
    {
        // fixFieldDescription is the only path that fills 'field_name' from
        // the description's fieldName property. Drop the call (mutant 5,
        // MethodCallRemoval) and 'field_name' stays null on the resulting
        // filter options.
        $admin = static::createStub(AdminInterface::class);
        $admin->method('getCode')->willReturn('code');
        $admin->method('getLabelTranslatorStrategy')->willReturn(new FormLabelTranslatorStrategy());

        $datagrid = static::createStub(DatagridInterface::class);

        $fieldDescription = new FieldDescription('test', [], [], [], [], 'expectedFieldName');
        $fieldDescription->setAdmin($admin);

        $filterFactory = static::createStub(FilterFactoryInterface::class);
        $filterFactory->method('create')->willReturn(new ModelFilter());

        new DatagridBuilder($this->formFactory, $filterFactory, $this->typeGuesser)
            ->addFilter($datagrid, ModelFilter::class, $fieldDescription);

        static::assertSame('expectedFieldName', $fieldDescription->getOption('field_name'));
    }
}
