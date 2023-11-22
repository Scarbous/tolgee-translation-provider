<?php

declare(strict_types=1);

namespace Scarbous\TolgeeTranslationProvider\Http;

use Scarbous\TolgeeTranslationProvider\Http\Dto\TranslationFilter;
use Symfony\Component\Mime\Part\DataPart;

interface TolgeeClientInterface
{
    public const MAX_PAGE_SIZE = 1000;

    /**
     * @param DataPart[] $files
     * @return array
     */
    public function import(array $files): array;

    public function importSelectNamespace(string $namespace, array $fileIds): void;

    public function importApply(string $forceMode = 'KEEP'): void;

    public function importDelete(): void;

    public function deleteKeys(array $keys): bool;

    public function getTranslations(?TranslationFilter $filter = null): \Generator;

    public function addLanguage(string $keyName): int;

    public function getLanguages(): \Generator;

    public function getAllNamespaces(): array;

    /**
     * @param callable $callback get the translationFilePath as argument
     */
    public function exportFileCallback(
        callable $callback,
        string $domain,
        string $locale,
        ?string $filterState = null
    ): void;
}
