<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Decorator\Storefront\Theme\Twig;

use Shopware\Storefront\Theme\Twig\ThemeInheritanceBuilderInterface;

class ThemeInheritanceBuilderDecorator implements ThemeInheritanceBuilderInterface
{
    private const PLUGIN_TECH_NAME = 'NostoIntegration';

    private ThemeInheritanceBuilderInterface $inner;

    public function __construct(ThemeInheritanceBuilderInterface $inner)
    {
        $this->inner = $inner;
    }

    public function build(array $bundles, array $themes): array
    {
        $result = $this->inner->build($bundles, $themes);

        if (isset($result[self::PLUGIN_TECH_NAME])) {
            unset($result[self::PLUGIN_TECH_NAME]);
            $result = \array_merge([
                self::PLUGIN_TECH_NAME => 1,
            ], $result);
        }

        return $result;
    }
}
