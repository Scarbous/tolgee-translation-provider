<?php

namespace Scarbous\TolgeeTranslationProvider\Test\Symfony;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\Test\ProviderTestCase;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Scarbous\TolgeeTranslationProvider\TolgeeProvider;
use Symfony\Component\Translation\Loader\JsonFileLoader;

class TolgeeProviderTest extends ProviderTestCase
{
    /**
     * @return LoaderInterface|MockObject
     */
    protected function getLoader(): LoaderInterface
    {
        return $this->loader ?? $this->loader = $this->createMock(JsonFileLoader::class);
    }

    public static function createProvider($client, LoaderInterface $loader, LoggerInterface $logger, string $defaultLocale, string $endpoint): ProviderInterface
    {
        return new TolgeeProvider($client, $loader, $logger, $defaultLocale, $endpoint);
    }

    public static  function toStringProvider(): iterable
    {
        $loader = new JsonFileLoader();
        yield 'app.tolgee.io' => [
            self::createProvider(
                self::getHttpClient(),
                $loader,
                new NullLogger(),
                'en',
                'app.tolgee.io'
            ),
            'tolgee://app.tolgee.io',
        ];

        yield 'local.dev' => [
            self::createProvider(
                self::getHttpClient(),
                $loader,
                new NullLogger(),
                'en',
                'local.dev'
            ),
            'tolgee://local.dev',
        ];

        yield 'example.com:99' => [
            self::createProvider(
                self::getHttpClient(),
                $loader,
                new NullLogger(),
                'en',
                'example.com:99'
            ),
            'tolgee://example.com:99',
        ];
    }

    public static function getResponsesForOneLocaleAndOneDomain(): \Generator
    {
        $arrayLoader = new ArrayLoader();

        $expectedTranslatorBagEn = new TranslatorBag();
        $expectedTranslatorBagEn->addCatalogue($arrayLoader->load([
            'index.hello' => 'Hello',
            'index.greetings' => 'Welcome, {firstname}!',
        ], 'en'));

        yield [
            'en', 'messages', [
                "index" => [
                    "hello" => "Hello",
                    "greetings" => "Welcome, {firstname}!"
                ]
            ],
            $expectedTranslatorBagEn,
        ];
    }

    /**
     * @dataProvider getResponsesForOneLocaleAndOneDomain
     */
    public function testReadForOneLocaleAndOneDomain(string $locale, string $domain, array $responseContent, TranslatorBag $expectedTranslatorBag)
    {
        $response = function (string $method, string $url, array $options = []) use ($locale, $domain, $responseContent): ResponseInterface {
            $this->assertSame('GET', $method);
            $this->assertSame('https://app.tolgee.io/v2/projects/1337/export?filterNamespace=' . $domain . '&languages=' . $locale . '&format=JSON&zip=0', $url);

            return new MockResponse(json_encode($responseContent));
        };

        $jsonFile = tempnam(sys_get_temp_dir(), "tolgee.$domain.$locale.json");
        file_put_contents($jsonFile, json_encode($responseContent));

        $loader = $this->getLoader();
        $loader->expects($this->once())
            ->method('load')
            ->willReturn((new JsonFileLoader())->load($jsonFile, $locale, $domain));

        $client = self::getHttpClient();

        $provider = self::createProvider($client, $loader, $this->getLogger(), $this->getDefaultLocale(), 'api.lokalise.com');
        $translatorBag = $provider->read([$domain], [$locale]);
        unset($jsonFile);

        $this->assertEquals($expectedTranslatorBag->getCatalogue($locale)->all($domain), $translatorBag->getCatalogue($locale)->all($domain));
    }


    public static function getResponsesForManyLocalesAndManyDomains(): \Generator
    {
        $arrayLoader = new ArrayLoader();

        $expectedTranslatorBag = new TranslatorBag();
        $expectedTranslatorBag->addCatalogue($arrayLoader->load([
            'index.hello' => 'Hello',
            'index.greetings' => 'Welcome, {firstname}!',
        ], 'en'));
        $expectedTranslatorBag->addCatalogue($arrayLoader->load([
            'index.hello' => 'Bonjour',
            'index.greetings' => 'Bienvenue, {firstname} !',
        ], 'fr'));
        $expectedTranslatorBag->addCatalogue($arrayLoader->load([
            'firstname.error' => 'Firstname must contains only letters.',
            'lastname.error' => 'Lastname must contains only letters.',
        ], 'en', 'validators'));
        $expectedTranslatorBag->addCatalogue($arrayLoader->load([
            'firstname.error' => 'Le prénom ne peut contenir que des lettres.',
            'lastname.error' => 'Le nom de famille ne peut contenir que des lettres.',
        ], 'fr', 'validators'));

        yield [
            ['en', 'fr'],
            ['messages', 'validators'],
            [
                'messages' => [
                    'en' => [
                        "index" => [
                            "hello" => "Hello",
                            "greetings" => "Welcome, {firstname}!"
                        ],
                    ],
                    'fr' => [
                        "index" => [
                            "hello" => "Bonjour",
                            "greetings" => "Bienvenue, {firstname} !"
                        ]
                    ]
                ],
                'validators' => [
                    'en' => [
                        'firstname' => ['error' => 'Firstname must contains only letters.'],
                        'lastname' => ['error' => 'Lastname must contains only letters.']
                    ],
                    'fr' => [
                        'firstname' => ['error' => 'Le prénom ne peut contenir que des lettres.'],
                        'lastname' => ['error' => 'Le nom de famille ne peut contenir que des lettres.']
                    ]
                ]
            ],
            $expectedTranslatorBag,
        ];
    }

    /**
     * @dataProvider getResponsesForManyLocalesAndManyDomains
     */
    public function testReadForManyLocalesAndManyDomains(array $locales, array $domains, array $responseContents, TranslatorBag $expectedTranslatorBag)
    {
        $response = function (string $method, string $url, array $options = []) use ($locales, $domains, $responseContents): ResponseInterface {

            $this->assertSame('GET', $method);

            $query = [];
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            self::assertArrayHasKey('filterNamespace', $query);
            self::assertArrayHasKey('languages', $query);
            $domain = $query['filterNamespace'];
            $locale = $query['languages'];
            self::assertContains($domain, $domains);
            self::assertContains($locale, $locales);

            $this->assertSame('https://app.tolgee.io/v2/projects/1337/export?filterNamespace=' . $domain . '&languages=' . $locale . '&format=JSON&zip=0', $url);

            return new MockResponse(json_encode($responseContents[$domain][$locale]));
        };

        $loader = $this->getLoader();
        $loader->expects($this->atLeastOnce())
            ->method('load')
            ->willReturnCallback(function ($resource, $locale, $domain) use ($responseContents) {
                self::assertEquals(json_encode($responseContents[$domain][$locale]), file_get_contents($resource));
                return (new ArrayLoader())->load($responseContents[$domain][$locale], $locale, $domain);
            });
        $client = self::getHttpClient($response);
        $provider = self::createProvider($client, $loader, $this->getLogger(), $this->getDefaultLocale(), 'api.lokalise.com');

        $translatorBag = $provider->read($domains, $locales);

        foreach ($domains as $domain) {
            foreach ($locales as $locale) {
                $this->assertEquals($expectedTranslatorBag->getCatalogue($locale)->all($domain), $translatorBag->getCatalogue($locale)->all($domain));
            }
        }
    }

    private static function getHttpClient($response = null): MockHttpClient
    {
        return (new MockHttpClient($response))->withOptions([
            'base_uri' => 'https://app.tolgee.io/v2/projects/1337/',
            'headers' => ['X-Api-Key' => 'API_KEY'],
        ]);
    }
}
