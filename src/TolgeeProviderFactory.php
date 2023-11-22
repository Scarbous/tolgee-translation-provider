<?php

declare(strict_types=1);

namespace Scarbous\TolgeeTranslationProvider;

use Psr\Log\LoggerInterface;
use Scarbous\TolgeeTranslationProvider\Http\TolgeeClient as HttpTolgeeClient;
use Scarbous\TolgeeTranslationProvider\Http\TolgeeClientInterface;
use Scarbous\TolgeeTranslationProvider\Service\TolgeeClient;
use Symfony\Component\Translation\Exception\IncompleteDsnException;
use Symfony\Component\Translation\Dumper\JsonFileDumper;
use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Loader\JsonFileLoader;
use Symfony\Component\Translation\Provider\AbstractProviderFactory;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TolgeeProviderFactory extends AbstractProviderFactory
{
    /** @var JsonFileLoader */
    private $loader;

    /** @var HttpClientInterface */
    private $client;

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $defaultLocale;

    /** @var JsonFileDumper */
    private $jsonFileDumper;

    public function __construct(
        HttpClientInterface $client,
        LoggerInterface     $logger,
        string              $defaultLocale,
        JsonFileLoader      $loader,
        JsonFileDumper      $jsonFileDumper
    ) {
        $this->client = $client;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
        $this->loader = $loader;
        $this->jsonFileDumper = $jsonFileDumper;
    }

    /**
     * @return TolgeeProvider
     */
    public function create(Dsn $dsn): ProviderInterface
    {
        if ($this->supports($dsn) === false) {
            throw new UnsupportedSchemeException($dsn, 'tolgee', $this->getSupportedSchemes());
        }

        $projectId = (int)$this->getUser($dsn);

        $tolgeeClient = new HttpTolgeeClient(
            $this->client,
            $this->getPassword($dsn),
            $projectId,
            $dsn->getHost(),
            $dsn->getPort(),
            $dsn->getScheme() === 'tolgees',
        );

        if (
            ($filterState = $dsn->getPath() ? trim($dsn->getPath(), '/') : NULL)
            && !in_array($filterState, TolgeeProvider::ALLOWED_FILTER_STATES)
        ) {
            throw new IncompleteDsnException('Filter state is not valid. Allowed values are: ' . implode(', ', TolgeeClient::ALLOWED_FILTER_STATES));
        }

        return new TolgeeProvider(
            $tolgeeClient,
            $this->loader,
            $this->logger,
            $this->defaultLocale,
            $dsn->getHost() . ($dsn->getPort() ? ':' . $dsn->getPort() : ''),
            $dsn->getPath() ? trim($dsn->getPath(), '/') : NULL
        );
    }

    protected function getSupportedSchemes(): array
    {
        return ['tolgee', 'tolgees'];
    }
}
