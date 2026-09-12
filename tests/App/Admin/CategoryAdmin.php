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

namespace IDCT\Adminata\DoctrineMongoDB\Tests\App\Admin;

use IDCT\Adminata\Admin\AbstractAdmin;
use IDCT\Adminata\Datagrid\DatagridMapper;
use IDCT\Adminata\Datagrid\ListMapper;
use IDCT\Adminata\Form\FormMapper;
use IDCT\Adminata\Show\ShowMapper;
use IDCT\Adminata\DoctrineMongoDB\Filter\ChoiceFilter;
use IDCT\Adminata\DoctrineMongoDB\Tests\App\Document\Category;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @phpstan-extends AbstractAdmin<Category>
 */
final class CategoryAdmin extends AbstractAdmin
{
    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('id')
            ->addIdentifier('name');
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('id', TextType::class, [
                'attr' => [
                    'class' => 'category_id',
                ],
                'empty_data' => '',
            ])
            ->add('name', null, [
                'attr' => [
                    'class' => 'category_name',
                ],
            ]);
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('id')
            ->add('name')
            ->add('type', ChoiceFilter::class, [
                'field_type' => ChoiceType::class,
                'field_options' => [
                    'choices' => [
                        'Type 1' => 'type1',
                        'Type 2' => 'type2',
                    ],
                    'expanded' => false,
                ],
            ]);
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('name');
    }
}
