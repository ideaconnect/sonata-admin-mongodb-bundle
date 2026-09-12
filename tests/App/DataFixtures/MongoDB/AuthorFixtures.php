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
use Doctrine\Persistence\ObjectManager;
use IDCT\Adminata\DoctrineMongoDB\Tests\App\Document\Address;
use IDCT\Adminata\DoctrineMongoDB\Tests\App\Document\Author;
use IDCT\Adminata\DoctrineMongoDB\Tests\App\Document\PhoneNumber;

final class AuthorFixtures extends Fixture
{
    public const string AUTHOR = 'author';

    public function load(ObjectManager $manager): void
    {
        $author = new Author('author_id', 'Miguel de Cervantes');
        $author->setAddress(new Address('Somewhere in La Mancha, in a place whose name I do not care to remember'));
        $author->addPhoneNumber(new PhoneNumber('666-666-666'));

        $manager->persist($author);
        $manager->flush();

        $this->addReference(self::AUTHOR, $author);
    }
}
