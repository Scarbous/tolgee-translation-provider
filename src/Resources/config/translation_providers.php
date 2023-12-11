<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Netlogix\SymfonyTolgeeTranslationProvider\TolgeeProviderFactory;

// @codeCoverageIgnoreStart
return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('translation.provider_factory.tolgee', TolgeeProviderFactory::class)
        ->autowire(true)
        ->args([
            service('http_client'),
            service('logger'),
            param('kernel.default_locale'),
            service('translation.loader.json')
        ])
        ->tag('translation.provider_factory');
};
// @codeCoverageIgnoreEnd