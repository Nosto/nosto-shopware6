<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Response\GraphQL\Filter\Values;

use Nosto\NostoIntegration\Search\Response\GraphQL\Filter\TranslatedName;
use Shopware\Core\Framework\Struct\Struct;

class FilterValue extends Struct
{
    private readonly TranslatedName $translated;

    public function __construct(
        private readonly string $id,
        private readonly string $name,
    ) {
        $this->translated = new TranslatedName($name);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTranslated(): TranslatedName
    {
        return $this->translated;
    }
}
