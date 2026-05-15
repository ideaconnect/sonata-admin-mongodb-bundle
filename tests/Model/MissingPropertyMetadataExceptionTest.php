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

namespace Sonata\DoctrineMongoDBAdminBundle\Tests\Model;

use PHPUnit\Framework\TestCase;
use Sonata\DoctrineMongoDBAdminBundle\Model\MissingPropertyMetadataException;

final class MissingPropertyMetadataExceptionTest extends TestCase
{
    public function testItIsALogicException(): void
    {
        $exception = new MissingPropertyMetadataException('App\\Document\\Book', 'title');

        static::assertInstanceOf(\LogicException::class, $exception);
    }

    public function testItFormatsTheMessageWithClassAndProperty(): void
    {
        $exception = new MissingPropertyMetadataException('App\\Document\\Book', 'title');

        static::assertSame(
            'No metadata found for property `App\\Document\\Book::$title`. Please make sure your Doctrine mapping is properly configured.',
            $exception->getMessage(),
        );
    }
}
