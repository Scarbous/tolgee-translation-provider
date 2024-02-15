<?php

declare(strict_types=1);

namespace Netlogix\SymfonyTolgeeTranslationProvider;

use Psr\Log\LoggerInterface;
use Netlogix\SymfonyTolgeeTranslationProvider\Exception\TolgeeException;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\RedirectionException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\Intl\Locales;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Translation\Loader\JsonFileLoader;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TolgeeProvider implements ProviderInterface
{
    public const ALLOWED_FILTER_STATES = [
            "UNTRANSLATED", "TRANSLATED", "REVIEWED"
        ],
        ALLOWED_FORCE_MODES = [
            "KEEP", "OVERRIDE", "MERGE"
        ];

    private const MAX_PAGE_SIZE = 100;

    private $client;
    private $loader;
    private $logger;
    private $defaultLocale;
    private $endpoint;
    /**
     * @var string|null
     */
    private $filterState;

    public function __construct(
        HttpClientInterface $client,
        JsonFileLoader      $loader,
        LoggerInterface     $logger,
        string              $defaultLocale,
        string              $endpoint,
        ?string             $filterState = NULL
    ) {
        $this->client = $client;
        $this->loader = $loader;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
        $this->endpoint = $endpoint;
        $this->filterState = $filterState;
    }

    public function __toString(): string
    {
        return sprintf('tolgee://%s', $this->endpoint);
    }

    public function read(array $domains, array $locales): TranslatorBag
    {
        $locales = $locales ?: $this->getLanguages();
        $domains = $domains ?: $this->getAllNamespaces();
        $translatorBag = new TranslatorBag();

        $query = [
            'format' => 'JSON',
            'zip' => false
        ];

        if ($this->filterState) {
            $query['filterState'] = $this->filterState;
        }

        foreach ($locales as $locale) {
            foreach ($domains as $domain) {
                if ($domain === null) {
                    $this->logger->warning(sprintf(
                        'Unnamed domains are not allowed',
                        $locale
                    ));
                    continue;
                }
                $this->exportFileCallback(
                    function (?string $jsonFile) use ($translatorBag, $locale, $domain) {
                        if ($jsonFile) {
                            $tolgeeCatalogue = $this->loader->load($jsonFile, $locale, $domain);
                            $translatorBag->addCatalogue($tolgeeCatalogue);
                        }
                    },
                    $domain,
                    $locale,
                    $this->filterState
                );
            }
        }

        return $translatorBag;
    }

    public function delete(TranslatorBagInterface $translatorBag): void
    {
        $catalogue = $translatorBag->getCatalogue($this->defaultLocale);

        $keysToDelete = [];

        $delete = function (array &$keys, bool $force = false) {
            if ($force || count($keys) > 1) {
                $this->deleteKeys($keys);
                $keys = [];
            }
        };

        foreach ($catalogue->getDomains() as $domain) {
            foreach ($this->getTranslationKeys($domain, array_keys($catalogue->all($domain))) as $key) {
                $keysToDelete[] = $key;
                $delete($keysToDelete);
            }
        }

        $delete($keysToDelete, true);

        #throw new \LogicException('Deleting translations is not supported by the Tolgee provider.');
    }

    public function write(TranslatorBagInterface $translatorBag): void
    {
        $files = [];

        $languages = iterator_to_array($this->getLanguages());

        # remove files from import
        $this->importDelete();

        $importMap = [];

        foreach ($translatorBag->getCatalogues() as $cataloge) {
            $locale = $cataloge->getLocale();
            if (!in_array($locale, $languages)) {
                $this->addLanguage($locale);
            }
            foreach ($cataloge->getDomains() as $domain) {
                $importMap[$domain][$locale] = $cataloge;
            }
        }

        foreach ($importMap as $domain => $locales) {
            $files = [];
            foreach ($locales as $locale => $cataloge) {
                $translations = $cataloge->all($domain);
                if (!count($translations)) {
                    continue;
                }
                $files[] = $this->createDataPartFile(
                    $locale,
                    json_encode($cataloge->all($domain))
                );
            }
            if (!count($files)) {
                continue;
            }
            $fileIds = $this->import($files);
            $this->importSelectNamespace($domain, $fileIds);
            $this->importApply('OVERRIDE');
        }
    }

    private function createDataPartFile($name, $content): DataPart
    {
        return new DataPart(
            $content,
            sprintf('%s.json', $name),
            'application/json'
        );
    }

    private function import(array $files): array
    {
        array_walk($files, function ($file) {
            if (!is_a($file, DataPart::class)) {
                throw new \InvalidArgumentException(sprintf(
                    'Expected instance of %s',
                    DataPart::class
                ));
            }
        });

        $formData = new FormDataPart(array_map(function ($f) {
            return ['files' => $f];
        }, $files));

        $response = $this->client->request('POST', 'import', [
            'headers' => $formData->getPreparedHeaders()->toArray(),
            'body' => $formData->bodyToIterable(),
        ]);

        try {
            $data = $response->toArray();
        } catch (\Exception $e) {
            throw new TolgeeException(
                'Unable to import translations',
                1700642547,
                $response,
                $e
            );
        }

        if (!empty($data['errors'])) {
            throw new TolgeeException(
                'Unable to import translations',
                1700642575,
                $response
            );
        }

        return array_column($data['result']['_embedded']['languages'] ?? [], 'importFileId');
    }

    private function getLanguages(): \Generator
    {
        foreach ($this->pagedRequest(function (int $page) {
            return $this->client->request(
                'GET',
                'languages',
                [
                    'query' => [
                        'page' => $page,
                        'size' => self::MAX_PAGE_SIZE
                    ]
                ]
            );
        }) as $response) {
            try {
                $data = $response->toArray();
            } catch (\Exception $e) {
                throw new TolgeeException(
                    'Unable to get languages',
                    1700643444,
                    $response,
                    $e
                );
            }
            foreach ($data['_embedded']['languages'] ?? [] as $key) {
                yield $key['id'] => $key['tag'];
            }
        }

        yield from [];
    }

    private function addLanguage(string $keyName): int
    {
        try {
            $name = Locales::getName($keyName);
            $originalName = Locales::getName($keyName, $keyName);
        } catch (\Exception $e) {
            $name = $keyName;
            $originalName = $keyName;
        }
        try {
            $flagEmoji = $this->country2flag($keyName);
        } catch (\Exception $e) {
            $flagEmoji = null;
        }
        $body = json_encode([
            'name' => $name,
            'tag' => $keyName,
            'originalName' => $originalName,
            'flagEmoji' => $flagEmoji
        ]);
        $response = $this->client->request('POST', 'languages', [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => $body
        ]);
        try {
            $data = $response->toArray();
        } catch (\Exception $e) {
            throw $e; #@todo add custom exception
        }
        return $data['id'];
    }

    private function importSelectNamespace(string $namespace, array $fileIds): void
    {
        foreach ($fileIds as $fileId) {
            $response = $this->client->request(
                'PUT',
                sprintf('import/result/files/%d/select-namespace', $fileId),
                [
                    'headers' => [
                        'Content-Type' => 'application/json'
                    ],
                    'body' => json_encode([
                        'namespace' => $namespace
                    ])
                ]
            );
            try {
                $this->checkResponseStatusCode($response);
            } catch (\Exception $e) {
                $data = $response->toArray(false);
                throw $e;
            }
        }
    }

    private function importApply(string $forceMode = 'KEEP'): void
    {
        if (!in_array($forceMode, self::ALLOWED_FORCE_MODES)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid force mode "%s". Allowed modes are: %s',
                $forceMode,
                join(', ', self::ALLOWED_FORCE_MODES)
            ));
        }

        $response = $this->client->request('PUT', 'import/apply', [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'query' => [
                'forceMode' => $forceMode
            ]
        ]);

        $this->checkResponseStatusCode($response);
    }

    private function importDelete(): void
    {
        $response = $this->client->request('DELETE', 'import');
        try {
            $this->checkResponseStatusCode($response);
        } catch (\Exception $e) {
            $data = $response->toArray(false);
            if ($data['code'] ?? '' === 'resource_not_found') {
                return;
            }
            throw $e;
        }
    }

    private function deleteKeys(array $keys): void
    {
        $response = $this->client->request(
            'DELETE',
            'keys',
            [
                'json' => [
                    'ids' => $keys
                ]
            ]
        );
        $this->checkResponseStatusCode($response);
    }

    private function getTranslationKeys(string $namespace, array $keys = []): \Generator
    {
        foreach (array_chunk($keys, 50) as $chunk) {
            // @todo: use query string in request options
            $queryString = http_build_query([
                'filterNamespace' => $namespace,
                'filterKeyName' => $chunk,
            ], '', '&', \PHP_QUERY_RFC3986);
            $response = $this->client->request(
                'GET',
                'translations/select-all?' . $queryString,
                [
                    # 'query' => [
                    #     'filterNamespace' => $namespace,
                    #     'filterKeyName' => $chunk,
                    # ]
                ]
            );
            $data = $response->toArray();
            yield from $data['ids'];
        }
        yield from [];
    }

    /**
     * @return Generator<ResponseInterface>
     */
    private function pagedRequest(callable $request): \Generator
    {
        $page = 0;

        do {
            /** @var ResponseInterface $response */
            $response = $request($page);
            try {
                $data = $response->toArray();
            } catch (\Exception $e) {
                throw $e; #@todo add custom exception
            }
            $pages = $data['page']['totalPages'];
            $currentPage = $data['page']['number'] + 1;
            yield $response;
            $page++;
        } while ($pages > $currentPage);
    }


    private function exportFileCallback(
        callable $callback,
        string $domain,
        string $locale,
        ?string $filterState = null
    ): void {

        $query = [
            'filterNamespace' => $domain,
            'languages' => $locale,
            'format' => 'JSON',
            'zip' => false
        ];

        if ($filterState) {
            $query['filterState'] = $filterState;
        }

        $response = $this->client->request('GET', 'export', [
            'buffer' => true,
            'query' => $query
        ]);

        if (400 === $response->getStatusCode()) {

            $data = $response->toArray(false);
            if ($data['code'] ?? '' === 'no_exported_result') {
                $callback(null);
                return;
            }
        }

        $this->checkResponseStatusCode($response);

        $jsonFile = tempnam(sys_get_temp_dir(), "tolgee.$domain.$locale.json");
        $jsonFileHandler = fopen($jsonFile, 'w');

        foreach ($this->client->stream($response) as $chunk) {
            fwrite($jsonFileHandler, $chunk->getContent());
        }

        fclose($jsonFileHandler);

        $callback($jsonFile);

        unlink($jsonFile);
    }

    private function getAllNamespaces(): array
    {
        $response = $this->client->request('GET', 'used-namespaces');

        try {
            $data = $response->toArray();
        } catch (\Exception $e) {
            throw $e; #@todo add custom exception
        }

        return array_map(function ($n) {
            return $n['name'];
        }, $data['_embedded']['namespaces'] ?? []);
    }

    private function checkResponseStatusCode(ResponseInterface $response): void
    {
        $code = $response->getStatusCode();

        if (500 <= $code) {
            throw new ServerException($response);
        }

        if (400 <= $code) {
            throw new ClientException($response);
        }

        if (300 <= $code) {
            throw new RedirectionException($response);
        }
    }

    private function country2flag(string $iso): string
    {
        return implode(array_map('mb_chr', array_map(function ($char) {
            return ord($char) + 127397;
        }, str_split(strtoupper($iso)))));
    }
}
