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

namespace IDCT\Adminata\DoctrineMongoDB;

use IDCT\Adminata\DoctrineMongoDB\DependencyInjection\AdminataDoctrineMongoDBExtension;
use IDCT\Adminata\DoctrineMongoDB\DependencyInjection\Compiler\AddGuesserCompilerPass;
use IDCT\Adminata\DoctrineMongoDB\DependencyInjection\Compiler\AddTemplatesCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class AdminataDoctrineMongoDBBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AddGuesserCompilerPass());
        $container->addCompilerPass(new AddTemplatesCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -1);
    }

    /**
     * Spelled out because the root Symfony would derive from the bundle name splits the acronym
     * (`adminata_doctrine_mongo_db`); the extension answers to `adminata_doctrine_mongodb`.
     */
    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new AdminataDoctrineMongoDBExtension();
        }

        return $this->extension;
    }
}
