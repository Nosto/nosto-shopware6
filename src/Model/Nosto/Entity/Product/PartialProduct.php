<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Product;

use DateTimeImmutable;
use DateTimeInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;

class PartialProduct
{
    public function __construct(
        private readonly PartialEntity $entity,
    ) {
    }

    public function getEntity(): PartialEntity
    {
        return $this->entity;
    }

    public function get(string $field): mixed
    {
        return $this->entity->get($field);
    }

    public function set(string $field, mixed $value): void
    {
        if (method_exists($this->entity, 'set')) {
            $this->entity->set($field, $value);
        }
    }

    public function getTranslation(string $field): mixed
    {
        $translated = $this->entity->get('translated');
        if (is_array($translated) && array_key_exists($field, $translated)) {
            return $translated[$field];
        }

        return null;
    }

    public function getId(): ?string
    {
        $value = $this->entity->get('id');
        return $value ? (string) $value : null;
    }

    public function getParentId(): ?string
    {
        $value = $this->entity->get('parentId');
        return $value ? (string) $value : null;
    }

    public function getProductNumber(): ?string
    {
        $value = $this->entity->get('productNumber');
        return $value ? (string) $value : null;
    }

    public function getManufacturerNumber(): ?string
    {
        $value = $this->entity->get('manufacturerNumber');
        return $value ? (string) $value : null;
    }

    public function getEan(): ?string
    {
        $value = $this->entity->get('ean');
        return $value ? (string) $value : null;
    }

    public function getReleaseDate(): ?DateTimeInterface
    {
        $value = $this->entity->get('releaseDate');
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            try {
                return new DateTimeImmutable($value);
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }

    /**
     * @return string[]|null
     */
    public function getCustomSearchKeywords(): ?array
    {
        $value = $this->entity->get('customSearchKeywords');
        return is_array($value) ? $value : null;
    }

    public function getVisibilities(): ?EntityCollection
    {
        $value = $this->entity->get('visibilities');
        return $value instanceof EntityCollection ? $value : null;
    }

    public function getVariantListingConfig(): mixed
    {
        return $this->entity->get('variantListingConfig');
    }

    public function getStock(): int
    {
        return (int) ($this->entity->get('stock') ?? 0);
    }

    public function getAvailableStock(): int
    {
        return (int) ($this->entity->get('availableStock') ?? 0);
    }

    public function getActive(): bool
    {
        return (bool) ($this->entity->get('active') ?? false);
    }

    public function getIsCloseout(): bool
    {
        return (bool) ($this->entity->get('isCloseout') ?? false);
    }

    public function getDisplayGroup(): ?string
    {
        $value = $this->entity->get('displayGroup');
        return $value !== null ? (string) $value : null;
    }

    public function getShippingFree(): bool
    {
        return (bool) ($this->entity->get('shippingFree') ?? false);
    }

    public function getCover(): ?object
    {
        $value = $this->entity->get('cover');
        return is_object($value) ? $value : null;
    }

    /**
     * @return iterable<int, mixed>|null
     */
    public function getMedia(): ?iterable
    {
        $value = $this->entity->get('media');
        return is_iterable($value) ? $value : null;
    }

    public function getManufacturer(): ?object
    {
        $value = $this->entity->get('manufacturer');
        return is_object($value) ? $value : null;
    }

    /**
     * @return iterable<int, mixed>|null
     */
    public function getOptions(): ?iterable
    {
        $value = $this->entity->get('options');
        return is_iterable($value) ? $value : null;
    }

    /**
     * @return iterable<int, mixed>|null
     */
    public function getProperties(): ?iterable
    {
        $value = $this->entity->get('properties');
        return is_iterable($value) ? $value : null;
    }

    public function getPrice(): PriceCollection|Price|null
    {
        $value = $this->entity->get('price');
        if ($value instanceof PriceCollection || $value instanceof Price) {
            return $value;
        }

        return null;
    }

    public function getCurrencyPrice(string $currencyId): ?Price
    {
        $prices = $this->getPrice();
        if ($prices instanceof PriceCollection) {
            return $prices->getCurrencyPrice($currencyId);
        }
        if ($prices instanceof Price) {
            return $prices->getCurrencyId() === $currencyId ? $prices : null;
        }

        return null;
    }

    public function getChildren(): ?PartialProductCollection
    {
        $value = $this->entity->get('children');
        if ($value instanceof PartialProductCollection) {
            return $value;
        }
        if ($value instanceof EntityCollection) {
            return PartialProductConverter::toPartialProductCollection($value);
        }

        return null;
    }

    public function setChildren(EntityCollection|PartialProductCollection|null $children): void
    {
        if ($children instanceof EntityCollection) {
            $children = PartialProductConverter::toPartialProductCollection($children);
        }

        $this->set('children', $children);
    }

    public function getCategoriesRo(): ?EntityCollection
    {
        $value = $this->entity->get('categoriesRo');
        return $value instanceof EntityCollection ? $value : null;
    }

    public function __call(string $name, array $arguments): mixed
    {
        if (str_starts_with($name, 'get') && strlen($name) > 3) {
            $field = lcfirst(substr($name, 3));
            return $this->entity->get($field);
        }
        if (str_starts_with($name, 'set') && strlen($name) > 3) {
            $field = lcfirst(substr($name, 3));
            $this->set($field, $arguments[0] ?? null);
            return null;
        }

        throw new \BadMethodCallException(sprintf('Undefined method %s::%s', self::class, $name));
    }

    public function getChildCount(): ?int
    {
        $value = $this->entity->get('childCount');
        if ($value === null) {
            return $this->getChildren()?->count();
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            return (int) $value;
        }

        return null;
    }
}
