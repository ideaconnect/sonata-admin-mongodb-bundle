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
use IDCT\Adminata\Form\Type\AdminType;
use IDCT\Adminata\Form\Type\CollectionType;
use IDCT\Adminata\DoctrineMongoDB\Tests\App\Document\Author;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @phpstan-extends AbstractAdmin<Author>
 */
final class AuthorAdmin extends AbstractAdmin
{
    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('id')
            ->addIdentifier('name')
            ->addIdentifier('address.street');
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('name')
            ->add('address.street')
            ->add('phoneNumbers.number');
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('id', TextType::class, [
                'attr' => [
                    'class' => 'author_id',
                ],
                'empty_data' => '',
            ])
            ->add('name', TextType::class, [
                'attr' => [
                    'class' => 'author_name',
                ],
                'empty_data' => '',
            ])
            ->add('address', AdminType::class, [
                'attr' => [
                    'class' => 'author_address',
                ],
            ])
            ->add('phoneNumbers', CollectionType::class, [
                'attr' => [
                    'class' => 'author_phoneNumbers',
                ],
            ], [
                'edit' => 'inline',
                'inline' => 'table',
            ]);
    }
}
