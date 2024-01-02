<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Response\GraphQL\Filter;

class RatingFilter extends Filter
{
    public function __construct(
        string $id,
        string $name,
        string $field,
        private float $maxPoints,
    ) {
        parent::__construct($id, $name, $field);
    }

    public function setMaxPoints(float $maxPoints): RatingFilter
    {
        $this->maxPoints = $maxPoints;

        return $this;
    }

    public function getMaxPoints(): float
    {
        return $this->maxPoints;
    }
}
