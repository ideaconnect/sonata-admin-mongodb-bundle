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

namespace Sonata\DoctrineMongoDBAdminBundle\Builder;

use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Builder\DatagridBuilderInterface;
use Sonata\AdminBundle\Datagrid\Datagrid;
use Sonata\AdminBundle\Datagrid\DatagridInterface;
use Sonata\AdminBundle\Datagrid\Pager as AdminPager;
use Sonata\AdminBundle\Datagrid\PagerInterface;
use Sonata\AdminBundle\Datagrid\SimplePager;
use Sonata\AdminBundle\FieldDescription\FieldDescriptionInterface;
use Sonata\AdminBundle\FieldDescription\TypeGuesserInterface;
use Sonata\AdminBundle\Filter\FilterFactoryInterface;
use Sonata\DoctrineMongoDBAdminBundle\Datagrid\Pager;
use Sonata\DoctrineMongoDBAdminBundle\Datagrid\ProxyQueryInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * @phpstan-implements DatagridBuilderInterface<ProxyQueryInterface<object>>
 */
final readonly class DatagridBuilder implements DatagridBuilderInterface
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private FilterFactoryInterface $filterFactory,
        private TypeGuesserInterface $guesser,
        private bool $csrfTokenEnabled = true,
    ) {
    }

    public function fixFieldDescription(FieldDescriptionInterface $fieldDescription): void
    {
        // For each "default this option from the mapping if no user override" pair,
        // only the unset branch is meaningful — `setOption(x, getOption(x, default))`
        // is a no-op when x is already set.
        $defaultableMappings = [
            'field_mapping' => $fieldDescription->getFieldMapping(),
            'association_mapping' => $fieldDescription->getAssociationMapping(),
            'parent_association_mappings' => $fieldDescription->getParentAssociationMappings(),
        ];

        foreach ($defaultableMappings as $option => $value) {
            if ([] !== $value && null === $fieldDescription->getOption($option)) {
                $fieldDescription->setOption($option, $value);
            }
        }

        if (null === $fieldDescription->getOption('field_name')) {
            $fieldDescription->setOption('field_name', $fieldDescription->getFieldName());
        }

        if ($fieldDescription->describesAssociation()) {
            $fieldDescription->getAdmin()->attachAdminClass($fieldDescription);
        }
    }

    public function addFilter(DatagridInterface $datagrid, ?string $type, FieldDescriptionInterface $fieldDescription): void
    {
        if (null === $type) {
            $guessType = $this->guesser->guess($fieldDescription);
            if (null === $guessType) {
                throw new \InvalidArgumentException(\sprintf(
                    'Cannot guess a type for the field description "%s", you MUST provide a type.',
                    $fieldDescription->getName()
                ));
            }

            /** @phpstan-var class-string $type */
            $type = $guessType->getType();

            $fieldDescription->setType($type);

            $options = $guessType->getOptions();

            foreach ($options as $name => $value) {
                if (\is_array($value)) {
                    $fieldDescription->setOption($name, array_merge($value, $fieldDescription->getOption($name, [])));
                } else {
                    $fieldDescription->setOption($name, $fieldDescription->getOption($name, $value));
                }
            }
        } else {
            $fieldDescription->setType($type);
        }

        $this->fixFieldDescription($fieldDescription);
        $fieldDescription->getAdmin()->addFilterFieldDescription($fieldDescription->getName(), $fieldDescription);

        $fieldDescription->mergeOption('field_options', ['required' => false]);
        $filter = $this->filterFactory->create($fieldDescription->getName(), $type, $fieldDescription->getOptions());
        $datagrid->addFilter($filter);
    }

    public function getBaseDatagrid(AdminInterface $admin, array $values = []): DatagridInterface
    {
        $pager = $this->getPager($admin->getPagerType());

        $defaultOptions = [];
        if ($this->csrfTokenEnabled) {
            $defaultOptions['csrf_protection'] = false;
        }

        $formBuilder = $this->formFactory->createNamedBuilder('filter', FormType::class, [], $defaultOptions);

        $query = $admin->createQuery();
        if (!$query instanceof ProxyQueryInterface) {
            throw new \TypeError(\sprintf('The admin query MUST implement %s.', ProxyQueryInterface::class));
        }
        /** @phpstan-var ProxyQueryInterface<object> $query */

        return new Datagrid($query, $admin->getList(), $pager, $formBuilder, $values);
    }

    /**
     * @throws \RuntimeException If invalid pager type is set
     *
     * @return PagerInterface<ProxyQueryInterface<object>>
     */
    private function getPager(string $pagerType): PagerInterface
    {
        if (AdminPager::TYPE_DEFAULT === $pagerType) {
            return new Pager();
        }

        if (AdminPager::TYPE_SIMPLE === $pagerType) {
            /** @var SimplePager<ProxyQueryInterface<object>> $simplePager */
            $simplePager = new SimplePager();

            return $simplePager;
        }

        throw new \RuntimeException(\sprintf('Unknown pager type "%s".', $pagerType));
    }
}
