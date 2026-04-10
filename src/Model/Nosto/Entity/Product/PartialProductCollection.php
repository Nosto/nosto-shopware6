<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Product;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<PartialProduct>
 */
class PartialProductCollection extends Collection
{
    /**
     * @param array-key $key
     */
    public function get($key): ?PartialProduct
    {
        /** @var PartialProduct|null $product */
        $product = parent::get($key);

        return $product;
    }

    /**
     * @return array<string>
     */
    public function getIds(): array
    {
        return $this->fmap(static fn (PartialProduct $product): ?string => $product->getId());
    }

    protected function getExpectedClass(): ?string
    {
        return PartialProduct::class;
    }
}
