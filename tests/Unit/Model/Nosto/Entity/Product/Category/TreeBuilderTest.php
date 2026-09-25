<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model\Nosto\Entity\Product\Category;

use Nosto\NostoIntegration\Model\Nosto\Entity\Product\Category\TreeBuilder;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class TreeBuilderTest extends TestCase
{
    public function testScopeToSalesChannelKeepsOnlyCategoriesOfTheChannelTrees(): void
    {
        $categories = new CategoryCollection([
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
            $this->createCategory('it-root', null, null, [
                'it-root' => 'IT',
            ]),
            $this->createCategory('it-shoes', 'it-root', '|it-root|', [
                'it-root' => 'IT',
                'it-shoes' => 'Scarpe',
            ]),
        ]);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setNavigationCategoryId('fr-root');
        $salesChannel->setFooterCategoryId('fr-footer');

        $scoped = (new TreeBuilder())->scopeToSalesChannel($categories, $salesChannel);

        self::assertSame(['fr-root', 'fr-shoes', 'fr-footer', 'fr-legal'], array_values($scoped->getIds()));
    }

    public function testCategoryPathsOfOtherChannelsAreNotSentWhenScoped(): void
    {
        $categories = new CategoryCollection([
            $this->createCategory('fr-root', null, null, [
                'fr-root' => 'FR',
            ]),
            $this->createCategory('fr-shoes', 'fr-root', '|fr-root|', [
                'fr-root' => 'FR',
                'fr-shoes' => 'Chaussures',
            ]),
            $this->createCategory('it-root', null, null, [
                'it-root' => 'IT',
            ]),
            $this->createCategory('it-shoes', 'it-root', '|it-root|', [
                'it-root' => 'IT',
                'it-shoes' => 'Scarpe',
            ]),
        ]);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setNavigationCategoryId('it-root');

        $treeBuilder = new TreeBuilder();

        self::assertSame(
            ['/Scarpe'],
            $treeBuilder->fromCategoriesRo($treeBuilder->scopeToSalesChannel($categories, $salesChannel)),
        );
    }

    public function testEveryEntryPointIsStrippedWhenProductIsInNavigationAndFooterTrees(): void
    {
        $treeBuilder = new TreeBuilder();
        $salesChannel = $this->createFrenchSalesChannel();
        $categories = $treeBuilder->scopeToSalesChannel($this->createNavigationAndFooterCategories(), $salesChannel);
        $entryPointIds = $treeBuilder->getSalesChannelEntryPointIds($salesChannel);

        self::assertSame(
            ['/Chaussures', '/Mentions'],
            $treeBuilder->fromCategoriesRo($categories, $entryPointIds),
        );
        self::assertSame(
            ['/Chaussures (ID = fr-shoes)', '/Mentions (ID = fr-legal)'],
            $treeBuilder->fromCategoriesRoWithId($categories, $entryPointIds),
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

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setNavigationCategoryId('fr-root');

        $treeBuilder = new TreeBuilder();
        $scoped = $treeBuilder->scopeToSalesChannel($categories, $salesChannel);

        self::assertSame(
            ['/Chaussures'],
            $treeBuilder->fromCategoriesRo($scoped, $treeBuilder->getSalesChannelEntryPointIds($salesChannel)),
        );
    }

    public function testPathsAreUnchangedWithoutEntryPoints(): void
    {
        self::assertSame(
            ['/Chaussures', '/Footer FR', '/Footer FR/Mentions'],
            (new TreeBuilder())->fromCategoriesRo($this->createNavigationAndFooterCategories()),
        );
    }

    private function createFrenchSalesChannel(): SalesChannelEntity
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setNavigationCategoryId('fr-root');
        $salesChannel->setFooterCategoryId('fr-footer');

        return $salesChannel;
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
