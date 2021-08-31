<?php

namespace KTPL\CurrencyRateConversionBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class CurrencyRateConversionExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('commands.yml');
        $loader->load('controllers.yml');
        $loader->load('job_constraints.yml');
        $loader->load('job_defaults.yml');
        $loader->load('readers.yml');
        $loader->load('processors.yml');
        $loader->load('steps.yml');
        $loader->load('jobs.yml');
        $loader->load('repositories.yml');
        $loader->load('converters.yml');
    }
}
