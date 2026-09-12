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
use IDCT\Adminata\Datagrid\ListMapper;
use IDCT\Adminata\Form\FormMapper;
use IDCT\Adminata\DoctrineMongoDB\Tests\App\Document\Address;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @phpstan-extends AbstractAdmin<Address>
 */
final class AddressAdmin extends AbstractAdmin
{
    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('street');
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('street', TextType::class, [
                'attr' => [
                    'class' => 'address_street',
                ],
                'empty_data' => '',
            ]);
    }
}
