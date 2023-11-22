<?php

declare(strict_types=1);

namespace Scarbous\TolgeeTranslationProvider\Http;

use Generator;
use Scarbous\TolgeeTranslationProvider\Http\Dto\TranslationFilter;
use Scarbous\TolgeeTranslationProvider\Http\Exception\TolgeeException;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\RedirectionException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\Intl\Locales;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;


class TolgeeClient implements TolgeeClientInterface
{
    public const ALLOWED_FILTER_STATES = [
            "UNTRANSLATED", "TRANSLATED", "REVIEWED"
        ],
        ALLOWED_FORCE_MODES = [
            "KEEP", "OVERRIDE", "MERGE"
        ];

    /** @var HttpClientInterface */
    private $client;


    public function __construct(
        HttpClientInterface $client,
        string              $apiKey,
        int                 $projectId,
        string              $server,
        ?int             $port = null,
        bool                $secure = true
    ) {
        $this->client = $client->withOptions([
            'base_uri' => sprintf(
                '%s://%s%s/v2/projects/%d/',
                $secure ? 'https' : 'http',
                $server,
                ($port ? ":$port" : ''),
                $projectId
            ),
            'headers' => [
                'X-Api-Key' => $apiKey,
            ],
        ]);
    }

    public function import(array $files): array
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

    public function getLanguages(): \Generator
    {
        foreach ($this->pagedRequest(function (int $page) {
            return $this->client->request(
                method: 'GET',
                url: 'languages',
                options: [
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

    public function addLanguage(string $keyName): int
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

    public function importSelectNamespace(string $namespace, array $fileIds): void
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
                ],
            );
            try {
                $this->checkResponseStatusCode($response);
            } catch (\Exception $e) {
                $data = $response->toArray(false);
                dd($data);
                throw $e;
            }
        }
    }

    public function importApply(string $forceMode = 'KEEP'): void
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

    public function importDelete(): void
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

    public function deleteKeys(array $keys): bool
    {
        return false;
    }


    public function getTranslations(?TranslationFilter $filter = null): \Generator
    {
        foreach ($this->pagedRequest(function (int $page) use ($filter) {
            return $this->client->request(
                method: 'GET',
                url: 'translations',
                options: [
                    'query' => array_merge([
                        'page' => $page,
                        'size' => self::MAX_PAGE_SIZE
                    ], $filter ? $filter->getQueryParams() : [])
                ]
            );
        }) as $response) {
            try {
                $data = $response->toArray();
            } catch (\Exception $e) {
                dd($response->toArray(false));
            }
            foreach ($data['_embedded']['keys'] ?? [] as $key) {
                yield $key['keyId'] => $key['keyName'];
            }
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


    public function exportFileCallback(
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

    public function getAllNamespaces(): array
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

    public function getAllLanguages(): array
    {
        $response = $this->client->request('GET', 'languages');

        try {
            $data = $response->toArray();
        } catch (\Exception $e) {
            throw $e; #@todo add custom exception
        }

        $data = $data['_embedded']['languages'] ?? [];
        return array_combine(
            array_column($data, 'id'),
            array_column($data, 'tag')
        );
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
