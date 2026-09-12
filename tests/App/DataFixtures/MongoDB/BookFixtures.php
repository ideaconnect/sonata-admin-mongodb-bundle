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

namespace IDCT\Adminata\DoctrineMongoDB\Tests\App\DataFixtures\MongoDB;

use Doctrine\Bundle\MongoDBBundle\Fixture\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use IDCT\Adminata\DoctrineMongoDB\Tests\App\Document\Author;
use IDCT\Adminata\DoctrineMongoDB\Tests\App\Document\Book;
use IDCT\Adminata\DoctrineMongoDB\Tests\App\Document\Category;

final class BookFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $author = $this->getReference(AuthorFixtures::AUTHOR, Author::class);

        $book = new Book('book_id', 'Don Quixote', $author);

        $category = $this->getReference(CategoryFixtures::CATEGORY, Category::class);

        $book->addCategory($category);

        $manager->persist($book);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CategoryFixtures::class,
            AuthorFixtures::class,
        ];
    }
}
