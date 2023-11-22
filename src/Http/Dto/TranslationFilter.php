<?php

declare(strict_types=1);

namespace Scarbous\TolgeeTranslationProvider\Http\Dto;

class TranslationFilter
{

    /**
     * @var string[]|null
     */
    private $filterNmespaces = null;

    /**
     * @var string[]|null
     */
    private $filterKeyNames = null;

    public function __construct(
        ?array $filterNmespaces = null,
        ?array $filterKeyNames = null
    ) {
        $this->filterNmespaces = $filterNmespaces;
        $this->filterKeyNames = $filterKeyNames;
    }

    public function hasFilterNamespaces(): bool
    {
        return $this->filterNmespaces !== null && count($this->filterNmespaces) > 0;
    }

    /**
     * @return string[]|null
     */
    public function getFilterNmespaces(): ?array
    {
        return $this->filterNmespaces;
    }

    /**
     * @param string[]|null $filterNmespace
     */
    public function setFilterNmespaces(?array $filterNmespaces): self
    {
        $this->filterNmespaces = $filterNmespaces;

        return $this;
    }

    public function addFilterNmespace(string $filterNmespace): self
    {
        $this->filterNmespaces[] = $filterNmespace;
        $this->filterNmespaces = array_unique($this->filterNmespaces);
        return $this;
    }

    public function hasFilterKeyNames(): bool
    {
        return $this->filterKeyNames !== null && count($this->filterKeyNames) > 0;
    }

    /**
     * @return string[]|null
     */
    public function getFilterKeyNames(): ?array
    {
        return $this->filterKeyNames;
    }

    /**
     * @param string[]|null $filterKeyName
     */
    public function setFilterKeyNames(?array $filterKeyNames): self
    {
        $this->filterKeyNames = $filterKeyNames;

        return $this;
    }

    public function addFilterKeyName(string $filterKeyName): self
    {
        $this->filterKeyNames[] = $filterKeyName;
        $this->filterKeyNames = array_unique($this->filterKeyNames);

        return $this;
    }

    public function getQueryParams(): array
    {
        $query = [];
        if ($this->hasFilterNamespaces()) {
            $query['filterNamespace'][] =  $this->filterNmespaces;
        }
        if ($this->hasFilterKeyNames()) {
            $query['filterKeyName'][] = $this->filterKeyNames;
        }
        return $query;
    }
}
