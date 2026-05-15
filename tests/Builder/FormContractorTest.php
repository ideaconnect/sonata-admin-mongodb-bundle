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

namespace Sonata\DoctrineMongoDBAdminBundle\Tests\Builder;

use PHPUnit\Framework\TestCase;
use Sonata\AdminBundle\Builder\AbstractFormContractor;
use Sonata\DoctrineMongoDBAdminBundle\Builder\FormContractor;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRegistryInterface;

final class FormContractorTest extends TestCase
{
    public function testItExtendsAbstractFormContractor(): void
    {
        $contractor = new FormContractor(
            static::createStub(FormFactoryInterface::class),
            static::createStub(FormRegistryInterface::class),
        );

        static::assertInstanceOf(AbstractFormContractor::class, $contractor);
    }
}
