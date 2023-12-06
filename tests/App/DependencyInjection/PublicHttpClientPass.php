<?php

declare(strict_types=1);


namespace Scarbous\TolgeeTranslationProvider\Test\App\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class PublicHttpClientPass implements CompilerPassInterface
{
    public function process(\Symfony\Component\DependencyInjection\ContainerBuilder $container)
    {
        $container->getDefinition('http_client')->setPublic(true);
    }
}
