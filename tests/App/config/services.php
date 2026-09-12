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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use IDCT\Adminata\DoctrineMongoDB\Tests\App\Admin\AddressAdmin;
use IDCT\Adminata\DoctrineMongoDB\Tests\App\Admin\AuthorAdmin;
use IDCT\Adminata\DoctrineMongoDB\Tests\App\Admin\BookAdmin;
use IDCT\Adminata\DoctrineMongoDB\Tests\App\Admin\CategoryAdmin;
use IDCT\Adminata\DoctrineMongoDB\Tests\App\Admin\PhoneNumberAdmin;
use IDCT\Adminata\DoctrineMongoDB\Tests\App\Document\Address;
use IDCT\Adminata\DoctrineMongoDB\Tests\App\Document\Author;
use IDCT\Adminata\DoctrineMongoDB\Tests\App\Document\Book;
use IDCT\Adminata\DoctrineMongoDB\Tests\App\Document\Category;
use IDCT\Adminata\DoctrineMongoDB\Tests\App\Document\PhoneNumber;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->load('IDCT\\Adminata\\DoctrineMongoDB\\Tests\\App\\DataFixtures\\', \dirname(__DIR__).'/DataFixtures')

        ->set(CategoryAdmin::class)
            ->tag('adminata.admin', [
                'manager_type' => 'doctrine_mongodb',
                'model_class' => Category::class,
                'label' => 'Category',
            ])

        ->set(BookAdmin::class)
            ->tag('adminata.admin', [
                'manager_type' => 'doctrine_mongodb',
                'model_class' => Book::class,
                'label' => 'Book',
            ])

        ->set(AuthorAdmin::class)
            ->tag('adminata.admin', [
                'manager_type' => 'doctrine_mongodb',
                'model_class' => Author::class,
                'label' => 'Author',
            ])

        ->set(AddressAdmin::class)
            ->tag('adminata.admin', [
                'manager_type' => 'doctrine_mongodb',
                'model_class' => Address::class,
                'label' => 'Address',
            ])

        ->set(PhoneNumberAdmin::class)
            ->tag('adminata.admin', [
                'manager_type' => 'doctrine_mongodb',
                'model_class' => PhoneNumber::class,
                'label' => 'PhoneNumber',
            ]);
};
