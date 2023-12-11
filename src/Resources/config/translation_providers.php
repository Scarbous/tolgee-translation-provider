<?php

/*
* This file is part of the Symfony package.
*
* (c) Fabien Potencier <fabien@symfony.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Scarbous\TolgeeTranslationProvider\TolgeeProviderFactory;

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