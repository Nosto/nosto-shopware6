<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model\Nosto\Entity\Product\Category;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\Category\TreeBuilder;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class TreeBuilderTest extends TestCase
{
    public function testScopeToSalesChannelKeepsOnlyCategoriesOfTheChannelTrees(): void
    {
        $scoped = $this->createTreeBuilder(true)->scopeToSalesChannel(
            $this->createFrenchAndItalianCategories(),
            $this->createContext('fr-root', 'fr-footer'),
        );

        self::assertSame(['fr-root', 'fr-shoes', 'fr-footer', 'fr-legal'], array_values($scoped->getIds()));
    }

    public function testScopeToSalesChannelKeepsAllCategoriesWhenScopingIsDisabled(): void
    {
        $categories = $this->createFrenchAndItalianCategories();

        $scoped = $this->createTreeBuilder(false)->scopeToSalesChannel(
            $categories,
            $this->createContext('fr-root', 'fr-footer'),
        );

        self::assertSame($categories->getIds(), $scoped->getIds());
    }

    public function testCategoryPathsOfOtherChannelsAreNotSentWhenScoped(): void
    {
        $treeBuilder = $this->createTreeBuilder(true);
        $context = $this->createContext('it-root');

        self::assertSame(
            ['/Scarpe'],
            $treeBuilder->fromCategoriesRo(
                $treeBuilder->scopeToSalesChannel($this->createFrenchAndItalianCategories(), $context),
                $context,
            ),
        );
    }

    public function testEveryEntryPointIsStrippedWhenProductIsInNavigationAndFooterTrees(): void
    {
        $treeBuilder = $this->createTreeBuilder(true);
        $context = $this->createContext('fr-root', 'fr-footer');
        $categories = $treeBuilder->scopeToSalesChannel($this->createNavigationAndFooterCategories(), $context);

        self::assertSame(
            ['/Chaussures', '/Mentions'],
            $treeBuilder->fromCategoriesRo($categories, $context),
        );
        self::assertSame(
            ['/Chaussures (ID = fr-shoes)', '/Mentions (ID = fr-legal)'],
            $treeBuilder->fromCategoriesRoWithId($categories, $context),
        );
    }

    public function testNonRootEntryPointIsStrippedWithItsAncestors(): void
    {
        $categories = new CategoryCollection([
            $this->createCategory('shop-root', null, null, [
                'shop-root' => 'Root',
            ]),
            $this->createCategory('fr-root', 'shop-root', '|shop-root|', [
                'shop-root' => 'Root',
                'fr-root' => 'FR',
            ]),
            $this->createCategory('fr-shoes', 'fr-root', '|shop-root|fr-root|', [
                'shop-root' => 'Root',
                'fr-root' => 'FR',
                'fr-shoes' => 'Chaussures',
            ]),
        ]);

        $treeBuilder = $this->createTreeBuilder(true);
        $context = $this->createContext('fr-root');

        self::assertSame(
            ['/Chaussures'],
            $treeBuilder->fromCategoriesRo($treeBuilder->scopeToSalesChannel($categories, $context), $context),
        );
    }

    public function testPathsAreUnchangedWhenScopingIsDisabled(): void
    {
        $treeBuilder = $this->createTreeBuilder(false);
        $expected = ['/Chaussures', '/Footer FR', '/Footer FR/Mentions'];

        self::assertSame(
            $expected,
            $treeBuilder->fromCategoriesRo(
                $this->createNavigationAndFooterCategories(),
                $this->createContext('fr-root', 'fr-footer'),
            ),
        );
        self::assertSame($expected, $treeBuilder->fromCategoriesRo($this->createNavigationAndFooterCategories()));
    }

    private function createTreeBuilder(bool $scopingEnabled): TreeBuilder
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isEnabledScopeCategoriesToSalesChannel')->willReturn($scopingEnabled);

        return new TreeBuilder($configProvider);
    }

    private function createContext(string $navigationCategoryId, ?string $footerCategoryId = null): SalesChannelContext
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setNavigationCategoryId($navigationCategoryId);
        if ($footerCategoryId !== null) {
            $salesChannel->setFooterCategoryId($footerCategoryId);
        }

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');
        $context->method('getLanguageId')->willReturn('language-id');
        $context->method('getSalesChannel')->willReturn($salesChannel);

        return $context;
    }

    private function createFrenchAndItalianCategories(): CategoryCollection
    {
        $categories = $this->createNavigationAndFooterCategories();
        $categories->add($this->createCategory('it-root', null, null, [
            'it-root' => 'IT',
        ]));
        $categories->add($this->createCategory('it-shoes', 'it-root', '|it-root|', [
            'it-root' => 'IT',
            'it-shoes' => 'Scarpe',
        ]));

        return $categories;
    }

    private function createNavigationAndFooterCategories(): CategoryCollection
    {
        return new CategoryCollection([
            $this->createCategory('fr-root', null, null, [
                'fr-root' => 'FR',
            ]),
            $this->createCategory('fr-shoes', 'fr-root', '|fr-root|', [
                'fr-root' => 'FR',
                'fr-shoes' => 'Chaussures',
            ]),
            $this->createCategory('fr-footer', null, null, [
                'fr-footer' => 'Footer FR',
            ]),
            $this->createCategory('fr-legal', 'fr-footer', '|fr-footer|', [
                'fr-footer' => 'Footer FR',
                'fr-legal' => 'Mentions',
            ]),
        ]);
    }

    /**
     * @param array<string, string> $breadcrumb
     */
    private function createCategory(string $id, ?string $parentId, ?string $path, array $breadcrumb): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setId($id);
        $category->setParentId($parentId);
        $category->setPath($path);
        $category->setBreadcrumb($breadcrumb);
        $category->setTranslated([
            'breadcrumb' => $breadcrumb,
        ]);

        return $category;
    }
}
