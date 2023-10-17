<?php

namespace Nosto\NostoIntegration\Search\Request;

class SearchNavigationRequest
{
    private string $query = '';

    private array $sort = [];

    private array $attributes = [];

    public function setSort(string $field, string $order): void
    {
        $this->sort = [
            "field" => $field,
            "order" => $order
        ];
    }

    public function addValueAttribute(string $filterName, string $value): void
    {
        $this->attributes[] = [
            'field' => $filterName,
            'value' => $value
        ];
    }

    public function addRangeAttribute(string $filterName, ?string $min = null, ?string $max = null): void
    {
        $range = [];

        if (!is_null($min)) {
            $range['gt'] = $min;
        }
        if (!is_null($min)) {
            $range['lt'] = $max;
        }

        $this->attributes[] = [
            'field' => $filterName,
            'range' => $range
        ];
    }

    public function getQuery(): array
    {
        return [
            'query' => $this->query,
            'products' => [
                "sort" => $this->sort
            ],
            'filter' => $this->attributes
        ];
    }
}