<?php

namespace Od\NostoIntegration\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NostoExtension extends AbstractExtension
{

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('od_nosto_page_type', [$this, 'getPageType'])
        ];
    }

    /**
     * @param string $activeRoute
     * @param $pageCmsType
     * @return string
     */
    public function getPageType(string $activeRoute, $pageCmsType): string
    {

        $pageType = 'notfound';

        if (empty($activeRoute)) {
            return $pageType;
        }

        switch ($activeRoute) {
            case 'frontend.home.page':
                $pageType = 'front';
                break;
            case 'frontend.navigation.page':
                if ($pageCmsType == 'product_list') {
                    $pageType = 'category';
                } else {
                    $pageType = 'other';
                }
                break;
            case 'frontend.detail.page':
                $pageType = 'product';
                break;
            case 'frontend.checkout.register.page':
            case 'frontend.checkout.confirm.page':
                $pageType = 'checkout';
                break;
            case 'frontend.checkout.finish.page':
                $pageType = 'order';
                break;
            case 'frontend.search.page':
                $pageType = 'search';
                break;
            default:
                $pageType = 'other';
        }

        return $pageType;

    }
}
