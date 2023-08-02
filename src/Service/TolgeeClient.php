<?php

namespace Scarbous\TolgeeTranslationProvider\Service;


use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Response\StreamWrapper;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TolgeeClient
{
    const ALLOWED_FILTER_STATES = [
        "UNTRANSLATED", "TRANSLATED", "REVIEWED"
    ];

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var HttpClientInterface */
    private $client;

    /**
     * @var string|null
     */
    private $filterState;


    function __construct(
        HttpClientInterface $client,
        LoggerInterface     $logger,
        string              $apiKey,
        int                 $projectId,
        string              $server,
        ?string             $port = NULL,
        bool                $secure = true,
        ?string             $filterState = NULL
    )
    {
        $this->logger = $logger;
        $this->client = $client->withOptions([
            'base_uri' => $secure ? 'https' : 'http' . '://' . $server . ($port ? ":.$port" : '') . "/v2/projects/$projectId/",
            'headers' => [
                'X-Api-Key' => $apiKey,
            ],
        ]);
        $this->filterState = $filterState;
    }


    function getTranslations(string $domain, string $locale): ?string
    {
        $query = [
            'filterState' => $this->filterState,
            'format' => 'JSON',
            'zip' => false
        ];

        if ($this->filterState) {
            $query['filterState'] = $this->filterState;
        }

        $response = $this->client->request('GET', 'export', [
            'buffer' => false,
            'query' => array_merge([
                'filterNamespace' => $domain,
                'languages' => $locale
            ], $query)
        ]);
        $fileHandler = fopen('freebsd-12.0-amd64-mini-memstick.iso', 'w');

        foreach ($this->client->stream($response) as $chunk) {
            fwrite($fileHandler, $chunk->getContent());

        }


        if (200 !== $response->getStatusCode()) {
            $this->logger->warning(sprintf('Locale "%s" for domain "%s" does not exist in Loco.', $locale, $domain));
            return NULL;
        }

        return $response->getContent(false);
    }


}
