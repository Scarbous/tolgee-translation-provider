<?php

declare(strict_types=1);

namespace Netlogix\SymfonyTolgeeTranslationProvider\Test\Symfony;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Netlogix\SymfonyTolgeeTranslationProvider\TolgeeProviderFactory as ProviderFactory;
use Symfony\Component\Translation\Dumper\JsonFileDumper;
use Symfony\Component\Translation\Loader\JsonFileLoader;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Component\Translation\Provider\ProviderFactoryInterface;
use Symfony\Component\Translation\Test\ProviderFactoryTestCase;

class TolgeeProviderFactoryTest extends ProviderFactoryTestCase
{
    /**
     * @var JsonFileDumper
     */
    protected $jsonFileDumper;

    public static function supportsProvider(): iterable
    {
        yield "http" => [true, 'tolgee://1:API_KEY@tolgee.dev'];
        yield "https" => [true, 'tolgees://2:API_KEY@tolgee.dev:8080'];
        yield "wrong shema" => [false, 'somethingElse://1:API_KEY@app.tolgee.io'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield "wrong shema" => ['somethingElse://1:API_KEY@app.tolgee.io'];
    }

    public static function createProvider(): iterable
    {
        yield 'http' => [
            'tolgee://app.tolgee.io',
            'tolgee://2:API_KEY@app.tolgee.io'
        ];
        yield 'https' => [
            'tolgee://tolgee.dev:8080',
            'tolgees://2:API_KEY@tolgee.dev:8080'
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield "mising password and user - http" => [
            'tolgee://default',
            'Invalid "tolgee://default" provider DSN: User is not set.'

        ];
        yield "mising password and user - https" => [
            'tolgees://default',
            'Invalid "tolgees://default" provider DSN: User is not set.'
        ];
        yield "wrong filter" => [
            'tolgee://1:API_KEY@tolgee.dev/foo'
        ];
    }

    public function testBaseUri()
    {
        $response = new MockResponse(json_encode(['foo' => 'bar']));
        $httpClient = new MockHttpClient([$response]);
        $loader = $this->getLoader();
        $loader->expects($this->once())->method('load')->willReturn(new \Symfony\Component\Translation\MessageCatalogue('en', ['foo' => 'bar']));
        $factory = new ProviderFactory($httpClient, $this->getLogger(),  $this->getDefaultLocale(), $loader, $this->getJsonFileDumper());
        $provider = $factory->create(new Dsn('tolgees://2:API_KEY@tolgee.dev:8080'));

        // Make a real HTTP request.
        $provider->read(['messages'], ['en']);

        $this->assertEquals('https://tolgee.dev:8080/v2/projects/2/export?filterNamespace=messages&languages=en&format=JSON&zip=0', $response->getRequestUrl());
    }

    public function createFactory(): ProviderFactoryInterface
    {
        return new ProviderFactory($this->getClient(), $this->getLogger(), $this->getDefaultLocale(), $this->getLoader(), $this->getJsonFileDumper());
    }

    protected function getLoader(): JsonFileLoader
    {
        return $this->loader ?? $this->loader = $this->createMock(JsonFileLoader::class);
    }

    protected function getJsonFileDumper(): JsonFileDumper
    {
        return $this->jsonFileDumper ?? $this->jsonFileDumper = $this->createMock(JsonFileDumper::class);
    }
}
