<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Scarbous\TolgeeTranslationProvider;

use Psr\Log\LoggerInterface;
use Scarbous\TolgeeTranslationProvider\Http\TolgeeClientInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Translation\Dumper\JsonFileDumper;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Component\Translation\Loader\JsonFileLoader;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\TranslatorBagInterface;

class TolgeeProvider implements ProviderInterface
{
    const ALLOWED_FILTER_STATES = [
        "UNTRANSLATED", "TRANSLATED", "REVIEWED"
    ];

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
        TolgeeClientInterface $client,
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
        $locales = $locales ?: $this->client->getAllLanguages();
        $domains = $domains ?: $this->client->getAllNamespaces();
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
                $this->client->exportFileCallback(
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

    public function delete($locale, $domain = NULL): void
    {
        throw new \LogicException('Deleting translations is not supported by the Tolgee provider.');
    }

    private function createDataPartFile($name, $content): DataPart
    {

        return new DataPart(
            body: $content,
            filename: sprintf('%s.json', $name),
            contentType: 'application/json',
        );
    }

    public function write(TranslatorBagInterface $translatorBag): void
    {
        $files = [];

        $languages = iterator_to_array($this->client->getLanguages());

        # remove files from import
        $this->client->importDelete();

        $importMap = [];

        foreach ($translatorBag->getCatalogues() as $cataloge) {
            $locale = $cataloge->getLocale();
            if (!in_array($locale, $languages)) {
                $this->client->addLanguage($locale);
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
            $fileIds = $this->client->import($files);
            $this->client->importSelectNamespace($domain, $fileIds);
            $this->client->importApply('OVERRIDE');
        }
    }
}
