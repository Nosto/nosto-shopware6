<?php

namespace Nosto\NostoIntegration\Decorator\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Generator;
use Nosto\NostoIntegration\Decorator\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingConfig as OriginalVariantListingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\VariantListingConfigFieldSerializer as OriginalVariantListingConfigFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Util\Json;

class VariantListingConfigFieldSerializer extends OriginalVariantListingConfigFieldSerializer
{
    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): Generator
    {
        foreach (parent::encode($field, $existence, $data, $parameters) as $key => $value) {
            $decodedValue = json_decode($value, true);

            $decodedValue['displayCheapestVariant'] = isset($decodedValue['displayCheapestVariant'])
                ? (int) $decodedValue['displayCheapestVariant']
                : null;
            $decodedValue['displayMainVariant'] = isset($decodedValue['displayMainVariant'])
                ? (int) $decodedValue['displayMainVariant']
                : null;

            yield $key => !empty($value) ? Json::encode($decodedValue) : null;
        }
    }

    public function decode(Field $field, mixed $value): ?VariantListingConfig
    {
        /** @var  $variantListingConfig */
        $variantListingConfig = parent::decode($field, $value);

        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        }

        return new VariantListingConfig(
            $variantListingConfig->getDisplayParent(),
            $variantListingConfig->getMainVariantId(),
            $variantListingConfig->getConfiguratorGroupConfig(),
            isset($value['displayCheapestVariant']) ? (bool) $value['displayCheapestVariant'] : null,
            isset($value['displayMainVariant']) ? (bool) $value['displayMainVariant'] : null,
        );
    }
}