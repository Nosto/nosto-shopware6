<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Utils\Loader;

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class FlexibleXmlFileLoader extends XmlFileLoader
{
    protected function setDefinition(string $id, Definition $definition)
    {
        if ($id === 'nosto.product_listing_loader' &&
            \version_compare(
                $this->container->getParameter('kernel.shopware_version'),
                '6.4.17.0',
                '>='
            )
        ) {
            $definition->addArgument(
                new Reference('Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilterFactory', 1)
            );
        }
        parent::setDefinition($id, $definition);
    }
}
