<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Response\GraphQL\Filter;

class RangeSliderFilter extends Filter
{
    private string $minKey;

    private string $maxKey;

    private ?float $min;

    private ?float $max;

    public function __construct(string $id, string $name, string $field, float $min, float $max)
    {
        parent::__construct($id, $name, $field);
        $this->min = $min;
        $this->max = $max;
        $this->minKey = sprintf('min-%s', $id);
        $this->maxKey = sprintf('max-%s', $id);
    }

    public function getMinKey(): string
    {
        return $this->minKey;
    }

    public function getMaxKey(): string
    {
        return $this->maxKey;
    }

    public function setMin(float $min): self
    {
        $this->min = $min;

        return $this;
    }

    public function getMin(): ?float
    {
        return $this->min;
    }

    public function setMax(float $max): self
    {
        $this->max = $max;

        return $this;
    }

    public function getMax(): ?float
    {
        return $this->max;
    }
}
