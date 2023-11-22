<?php

declare(strict_types=1);

namespace Scarbous\TolgeeTranslationProvider\Test\Unit\Http;

use PHPUnit\Framework\TestCase;
use Mockery;
use Scarbous\TolgeeTranslationProvider\Http\Exception\TolgeeException;
use Scarbous\TolgeeTranslationProvider\Http\TolgeeClient;
use Scarbous\TolgeeTranslationProvider\Test\Fixtures\HttpClientFixture;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TolgeeClientTest extends TestCase
{
    const BASE_URI = 'https://tolgee.dev/v2/projects/1/';

    protected $httpClient;

    public function setUp(): void
    {
        $this->httpClient = Mockery::mock(HttpClientInterface::class);
    }

    static function createInstanceDataProvider(): iterable
    {
        yield [
            'your-server',
            8080,
            true,
            'https://your-server:8080/v2/projects/123/'
        ];
        yield [
            'your-server',
            8080,
            false,
            'http://your-server:8080/v2/projects/123/'
        ];
        yield [
            'your-server',
            null,
            false,
            'http://your-server/v2/projects/123/'
        ];
        yield [
            'your-server',
            null,
            true,
            'https://your-server/v2/projects/123/'
        ];
        yield [
            'your-server',
            443,
            false,
            'http://your-server:443/v2/projects/123/'
        ];
        yield [
            'your-server',
            8080,
            true,
            'https://your-server:8080/v2/projects/123/'
        ];
    }

    /**
     * @dataProvider createInstanceDataProvider
     */
    function testCreateInstance(
        string $server,
        ?int $port,
        bool $secure,
        string $expected
    ): void {


        $this->httpClient->shouldReceive('withOptions')
            ->with(Mockery::on(function (array $options) use ($expected) {
                self::assertEquals($expected, $options['base_uri']);
                self::assertEquals('your-api-key', $options['headers']['X-Api-Key']);
                return true;
            }))
            ->andReturn($this->httpClient);

        $client =  new TolgeeClient(
            $this->httpClient,
            'your-api-key',
            123,
            $server,
            $port,
            $secure
        );

        self::assertInstanceOf(TolgeeClient::class, $client);
    }

    public function testImportArgumentException()
    {
        $client = $this->createTolgeeClient();
        $this->expectException(\InvalidArgumentException::class);
        $client->import(['foo']);
    }

    public function testImport()
    {
        $client = $this->createTolgeeClient(new \Symfony\Component\HttpClient\MockHttpClient(
            function ($method, $url, $options) {

                self::assertArrayHasKey('headers', $options);
                self::assertArrayHasKey('body', $options);

                $foundMultiPart = array_reduce($options['headers'], function ($carry, $item) use (&$foundMultiPart) {
                    return $carry || strpos($item, 'multipart/form-data; boundary=') !== false;
                }, false);
                self::assertTrue($foundMultiPart);

                $path = str_replace(self::BASE_URI, '', $url);
                $data = HttpClientFixture::getData('TolgeeApi/ImportTest', $path, $method);
                return new \Symfony\Component\HttpClient\Response\MockResponse($data);
            },
            self::BASE_URI
        ));

        $response = $client->import([
            new DataPart(
                body: '{"foo":"bar en"}',
                filename: 'en.json',
                contentType: 'application/json',
            ),
            new DataPart(
                body: '{"foo":"bar de"}',
                filename: 'de.json',
                contentType: 'application/json',
            )
        ]);

        self::assertEquals([
            0 => 1000040001,
            1 => 1000040002
        ], $response);
    }

    public function testImportError()
    {
        $client = $this->createTolgeeClient(new \Symfony\Component\HttpClient\MockHttpClient(
            function ($method, $url, $options) {

                self::assertArrayHasKey('headers', $options);
                self::assertArrayHasKey('body', $options);

                $foundMultiPart = array_reduce($options['headers'], function ($carry, $item) use (&$foundMultiPart) {
                    return $carry || strpos($item, 'multipart/form-data; boundary=') !== false;
                }, false);
                self::assertTrue($foundMultiPart);

                $path = str_replace(self::BASE_URI, '', $url);
                $data = HttpClientFixture::getData('TolgeeApi/ImportTestError', $path, $method);
                return new \Symfony\Component\HttpClient\Response\MockResponse($data);
            },
            self::BASE_URI
        ));

        $this->expectException(TolgeeException::class);

        $client->import([
            new DataPart(
                body: '',
                filename: 'de.json',
                contentType: 'application/json',
            )
        ]);
    }

    public function testGetLanguages()
    {
        $client = $this->createTolgeeClient(new \Symfony\Component\HttpClient\MockHttpClient(
            function ($method, $url, $options) {
                self::assertArrayHasKey('query', $options);
                $query = $options['query'];
                self::assertArrayHasKey('page', $query);
                $page = $query['page'];
                $path = str_replace(self::BASE_URI, '', $url);
                $path = explode('?', $path)[0];
                $data = HttpClientFixture::getPagedData('TolgeeApi/GetLanguagesTest', $path, $method, $page);
                return new \Symfony\Component\HttpClient\Response\MockResponse($data);
            },
            self::BASE_URI
        ));
        $data = iterator_to_array($client->getLanguages());

        self::assertEquals([
            1000025006 => "de",
            1000000001 => "en",
            1000006002 => "it",
            1000025007 => "sk"
        ], $data);
    }

    private function createTolgeeClient($client = null): TolgeeClient
    {
        $this->httpClient->shouldReceive('withOptions')
            ->andReturn($client ?? $this->httpClient);
        return new TolgeeClient($this->httpClient, 'your-api-key', 123, 'your-server', 8080, true);
    }
}
