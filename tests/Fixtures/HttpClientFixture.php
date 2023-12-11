<?php

declare(strict_types=1);

namespace Netlogix\SymfonyTolgeeTranslationProvider\Test\Fixtures;

class HttpClientFixture
{
    private static function getPath(string $fixture): string
    {
        return __DIR__ . '/' . $fixture;
    }

    public static function getData(string $fixture, string $path, string  $method): string
    {
        return  file_get_contents(sprintf(
            '%s/%s.%s.json',
            self::getPath($fixture),
            $path,
            strtolower($method)
        ));
    }

    public static function getPagedData(string $fixture, string $path, string  $method, int $page=0): string
    {
        return  file_get_contents(sprintf(
            '%s/%s.%s.%d.json',
            self::getPath($fixture),
            $path,
            strtolower($method),
            $page
        ));
    }
}
