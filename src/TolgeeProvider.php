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
use Symfony\Component\Translation\Dumper\JsonFileDumper;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Component\Translation\Loader\JsonFileLoader;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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

                $response = $this->client->request('GET', 'export', [
                    'buffer' => false,
                    'query' => array_merge([
                        'filterNamespace' => $domain,
                        'languages' => $locale
                    ], $query)
                ]);

                if (200 !== $response->getStatusCode()) {
                    $this->logger->warning(sprintf('Locale "%s" for domain "%s" does not exist in Loco.', $locale, $domain));
                    continue;
                }

                $jsonFile = tempnam(sys_get_temp_dir(), "tolgee.$domain.$locale.json");
                $jsonFileHandler = fopen($jsonFile, 'w');

                foreach ($this->client->stream($response) as $chunk) {
                    fwrite($jsonFileHandler, $chunk->getContent());
                }
                fclose($jsonFileHandler);
                $tolgeeCatalogue = $this->loader->load($jsonFile, $locale, $domain);
                
                unlink($jsonFile);

                $translatorBag->addCatalogue($tolgeeCatalogue);
            }
        }
        return $translatorBag;
    }

    public function delete($locale, $domain = NULL): void
    {
        throw new \LogicException('Deleting translations is not supported by the Tolgee provider.');
    }

    public function write(TranslatorBagInterface $translatorBag): void
    {
        throw new \LogicException('Write translations is not supported by the Tolgee provider.');
    }

    private function getAllNamespaces(): array
    {
        $response = $this->client->request('GET', 'used-namespaces');
        if (200 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to fetch namespaces from tolgee: "%s".', $response->getContent(false)), $response);
        }
        $data = $response->getContent(false);
        $data = json_decode($data, true);
        return array_map(function ($n) {
            return $n['name'];
        }, $data['_embedded']['namespaces']);
    }

    private function getAllLanguages(): array
    {
        $response = $this->client->request('GET', 'languages');
        if (200 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to fetch languages from tolgee: "%s".', $response->getContent(false)), $response);
        }
        $data = $response->getContent(false);
        $data = json_decode($data, true);
        return array_map(function($lang){
            return $lang['tag'];
        },$data['_embedded']['languages']);
    }
}
