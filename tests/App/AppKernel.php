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

namespace IDCT\Adminata\DoctrineMongoDB\Tests\App;

use Doctrine\Bundle\MongoDBBundle\DoctrineMongoDBBundle;
use Knp\Bundle\MenuBundle\KnpMenuBundle;
use IDCT\Adminata\AdminataBundle;
use IDCT\Adminata\DoctrineMongoDB\AdminataDoctrineMongoDBBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\StimulusBundle\StimulusBundle;

final class AppKernel extends Kernel
{
    use MicroKernelTrait;

    #[\Override]
    public function registerBundles(): iterable
    {
        return [
            new DoctrineMongoDBBundle(),
            new FrameworkBundle(),
            new KnpMenuBundle(),
            new SecurityBundle(),
            new AdminataBundle(),
            new AdminataDoctrineMongoDBBundle(),
            new TwigBundle(),
            new StimulusBundle(),
        ];
    }

    #[\Override]
    public function getCacheDir(): string
    {
        return $this->getBaseDir().'cache';
    }

    #[\Override]
    public function getLogDir(): string
    {
        return $this->getBaseDir().'log';
    }

    #[\Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\sprintf('%s/config/routes.yaml', $this->getProjectDir()));
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $loader->load(__DIR__.'/config/config.yaml');
        $loader->load(__DIR__.'/config/config_symfony.yaml');
        $loader->load(__DIR__.'/config/services.php');
    }

    private function getBaseDir(): string
    {
        // Include the PID so parallel test runners (ParaTest, multiple CI
        // workers on the same host) don't trample each other's caches.
        return sys_get_temp_dir().'/adminata-doctrine-mongodb-admin-bundle-'.getmypid().'/var/';
    }
}
