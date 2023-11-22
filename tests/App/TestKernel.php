<?php

declare(strict_types=1);

namespace Scarbous\TolgeeTranslationProvider\Test\App;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class TestKernel extends \Symfony\Component\HttpKernel\Kernel
{

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function registerBundles(): iterable
    {
        return [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new \Scarbous\TolgeeTranslationProvider\TolgeeTranslationProviderBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'secret' => 'SOME_SECRET',
                'test' => true,
                "translator" => [
                    "enabled" => true,
                    "default_path" => '%kernel.project_dir%/translations',
                    "enabled_locales" => ["en", "de", "sk"],
                    "providers" => [
                        "tolgee" => [
                            "dsn" => '%env(TOLGEE_DSN)%'
                        ]
                    ]
                ],
            ]);
        });
    }
}
