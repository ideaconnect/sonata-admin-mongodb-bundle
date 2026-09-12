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
use IDCT\Adminata\Form\Type\ModelListType;
use IDCT\Adminata\DoctrineMongoDB\Tests\App\Document\Book;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @phpstan-extends AbstractAdmin<Book>
 */
final class BookAdmin extends AbstractAdmin
{
    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('id')
            ->addIdentifier('name')
            ->add('author')
            ->add('categories');
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('author')
            ->add('categories');
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('id', TextType::class, [
                'attr' => [
                    'class' => 'book_id',
                ],
                'empty_data' => '',
            ])
            ->add('name', TextType::class, [
                'attr' => [
                    'class' => 'book_name',
                ],
                'empty_data' => '',
            ])
            ->add('author', ModelListType::class, [
                'attr' => [
                    'class' => 'book_author',
                ],
            ])
            ->add('categories', null, [
                'attr' => [
                    'class' => 'book_categories',
                ],
            ]);
    }
}
