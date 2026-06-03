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

namespace Sonata\DoctrineMongoDBAdminBundle\Tests\Filter;

use Sonata\AdminBundle\Filter\Model\FilterData;
use Sonata\DoctrineMongoDBAdminBundle\Datagrid\ProxyQuery;
use Sonata\DoctrineMongoDBAdminBundle\Datagrid\ProxyQueryInterface;
use Sonata\DoctrineMongoDBAdminBundle\Filter\CallbackFilter;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class CallbackFilterTest extends FilterWithQueryBuilderTestCase
{
    public function testFilterClosureEmpty(): void
    {
        $builder = new ProxyQuery($this->getQueryBuilder());

        $filter = new CallbackFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
            'callback' => static fn (ProxyQueryInterface $proxyQuery, string $field, FilterData $data): bool => $data->hasValue(),
        ]);

        $filter->apply($builder, FilterData::fromArray([]));

        static::assertFalse($filter->isActive());
    }

    public function testFilterClosureNotEmpty(): void
    {
        $builder = new ProxyQuery($this->getQueryBuilder());

        $filter = new CallbackFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
            'callback' => static fn (): bool => true,
        ]);

        $filter->apply($builder, FilterData::fromArray(['value' => 'myValue']));

        static::assertTrue($filter->isActive());
    }

    public function testFilterMethodEmpty(): void
    {
        $builder = new ProxyQuery($this->getQueryBuilder());

        $filter = new CallbackFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
            'callback' => $this->customCallback(...),
        ]);

        $filter->apply($builder, FilterData::fromArray([]));

        static::assertFalse($filter->isActive());
    }

    public function testFilterMethodNotEmpty(): void
    {
        $builder = new ProxyQuery($this->getQueryBuilder());

        $filter = new CallbackFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
            'callback' => $this->customCallback(...),
        ]);

        $filter->apply($builder, FilterData::fromArray(['value' => 'myValue']));

        static::assertTrue($filter->isActive());
    }

    /**
     * @param ProxyQueryInterface<object> $proxyQuery
     */
    public function customCallback(ProxyQueryInterface $proxyQuery, string $field, FilterData $data): bool
    {
        return $data->hasValue();
    }

    public function testFilterException(): void
    {
        $builder = new ProxyQuery($this->getQueryBuilder());

        $filter = new CallbackFilter();
        $filter->initialize('field_name', []);

        $this->expectException(\RuntimeException::class);

        $filter->apply($builder, FilterData::fromArray(['value' => 'myValue']));
    }

    public function testFilterThrowsWhenCallbackReturnsNonBoolScalar(): void
    {
        $builder = new ProxyQuery($this->getQueryBuilder());

        $filter = new CallbackFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
            'callback' => static fn (): string => 'not-a-bool',
        ]);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/should return a boolean/');
        // Lock the literal "<type>" form (with both surrounding quotes).
        // Five Concat / ConcatOperandRemoval mutants on the quoting expression
        // all change either the order or drop one of the quote-string operands;
        // matching the exact `"string"` token kills the lot.
        $this->expectExceptionMessageMatches('/"string"/');

        $filter->apply($builder, FilterData::fromArray(['value' => 'x']));
    }

    public function testFilterThrowsWhenCallbackReturnsNonBoolObject(): void
    {
        $builder = new ProxyQuery($this->getQueryBuilder());

        $filter = new CallbackFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
            'callback' => static fn (): \stdClass => new \stdClass(),
        ]);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/instance of "stdClass"/');

        $filter->apply($builder, FilterData::fromArray(['value' => 'x']));
    }

    public function testGetDefaultOptions(): void
    {
        $filter = new CallbackFilter();

        static::assertSame(
            [
                'callback' => null,
                'field_type' => TextType::class,
                'operator_type' => HiddenType::class,
                'operator_options' => [],
            ],
            $filter->getDefaultOptions(),
        );
    }

    public function testGetFormOptionsExposesFieldAndOperatorMetadata(): void
    {
        $filter = new CallbackFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
        ]);

        $options = $filter->getFormOptions();

        static::assertSame(TextType::class, $options['field_type']);
        static::assertSame(HiddenType::class, $options['operator_type']);
        static::assertSame([], $options['operator_options']);
    }

    public function testGetFormOptionsHasExactShape(): void
    {
        $filter = new CallbackFilter();
        $filter->initialize('field_name', [
            'field_name' => self::DEFAULT_FIELD_NAME,
        ]);

        static::assertSame(
            [
                'field_type' => TextType::class,
                'field_options' => [],
                'operator_type' => HiddenType::class,
                'operator_options' => [],
                'label' => null,
            ],
            $filter->getFormOptions(),
        );
    }
}
