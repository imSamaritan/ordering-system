<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CartCategorySectionsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCartDrawerRendersCollapsibleCategorySectionsAndExpandCollapseAllControls(): void
    {
        $layout = file_get_contents($this->root . '/templates/layouts/base.php');
        $this->assertIsString($layout);

        // Expand All / Collapse All bar above the cart items list
        $this->assertStringContainsString('data-cart-section-controls="true"', $layout);
        $this->assertStringContainsString('data-cart-expand-all="true"', $layout);
        $this->assertStringContainsString('@click="expandAllCategories()"', $layout);
        $this->assertStringContainsString(':disabled="allSectionsExpanded"', $layout);
        $this->assertStringContainsString('data-cart-collapse-all="true"', $layout);
        $this->assertStringContainsString('@click="collapseAllCategories()"', $layout);
        $this->assertStringContainsString(':disabled="allSectionsCollapsed"', $layout);

        // Grouped sections with clickable, accessible collapsible headers and rotating chevron icon
        $this->assertStringContainsString('x-for="group in groupedItems" :key="group.slug"', $layout);
        $this->assertStringContainsString('data-cart-category-section="true"', $layout);
        $this->assertStringContainsString('data-cart-category-header="true"', $layout);
        $this->assertStringContainsString('@click="toggleCategory(group.slug)"', $layout);
        $this->assertStringContainsString(':aria-expanded="(!isCategoryCollapsed(group.slug)).toString()"', $layout);
        $this->assertStringContainsString(':aria-controls="\'cart-section-\' + group.slug"', $layout);
        $this->assertStringContainsString('data-cart-category-chevron="true"', $layout);
        $this->assertStringContainsString(":class=\"isCategoryCollapsed(group.slug) ? '-rotate-90' : 'rotate-0'\"", $layout);
        $this->assertStringContainsString('x-text="group.name"', $layout);
        $this->assertStringContainsString('x-text="group.itemCount"', $layout);
        $this->assertStringContainsString('x-text="formatPrice(group.subtotal)"', $layout);

        // Collapsible items container defaults open (collapsedCategories starts empty)
        $this->assertStringContainsString('x-show="!isCategoryCollapsed(group.slug)"', $layout);
        $this->assertStringContainsString('x-for="item in group.items" :key="item.productId"', $layout);

        // Clear Cart resets collapse state via clearCart()
        $this->assertStringContainsString('@click="clearCart()"', $layout);
    }

    public function testCartDrawerComponentDefinesGroupingAndCollapseHelpers(): void
    {
        $cartDrawerTs = file_get_contents($this->root . '/resources/ts/shared/cartDrawer.ts');
        $this->assertIsString($cartDrawerTs);

        $this->assertStringContainsString('export interface CartCategoryGroup', $cartDrawerTs);
        $this->assertStringContainsString('collapsedCategories: Record<string, boolean>;', $cartDrawerTs);
        $this->assertStringContainsString('get groupedItems(): CartCategoryGroup[]', $cartDrawerTs);
        $this->assertStringContainsString('get allSectionsCollapsed(): boolean', $cartDrawerTs);
        $this->assertStringContainsString('get allSectionsExpanded(): boolean', $cartDrawerTs);
        $this->assertStringContainsString('isCategoryCollapsed(slug: string): boolean', $cartDrawerTs);
        $this->assertStringContainsString('toggleCategory(slug: string): void', $cartDrawerTs);
        $this->assertStringContainsString('collapseAllCategories(): void', $cartDrawerTs);
        $this->assertStringContainsString('expandAllCategories(): void', $cartDrawerTs);
        $this->assertStringContainsString('clearCart(): void', $cartDrawerTs);
        $this->assertStringContainsString("'uncategorized'", $cartDrawerTs);
        $this->assertStringContainsString("'Other Items'", $cartDrawerTs);
    }

    public function testCartStorePersistsAndBackfillsCategoryMetadata(): void
    {
        $cartStoreTs = file_get_contents($this->root . '/resources/ts/shared/cartStore.ts');
        $this->assertIsString($cartStoreTs);

        $this->assertStringContainsString('categoryId: product.category_id,', $cartStoreTs);
        $this->assertStringContainsString('categorySlug: product.category_slug,', $cartStoreTs);
        $this->assertStringContainsString('categoryName: product.category_name,', $cartStoreTs);
        $this->assertStringContainsString('syncProductMetadata(products: Product[]): void', $cartStoreTs);

        $storefrontTs = file_get_contents($this->root . '/resources/ts/pages/storefront.ts');
        $this->assertIsString($storefrontTs);
        $this->assertStringContainsString('cart.syncProductMetadata(this.products);', $storefrontTs);

        $customerDashboardTs = file_get_contents($this->root . '/resources/ts/pages/customerDashboard.ts');
        $this->assertIsString($customerDashboardTs);
        $this->assertStringContainsString('categoryId: item.category_id,', $customerDashboardTs);
        $this->assertStringContainsString('categorySlug: item.category_slug,', $customerDashboardTs);
        $this->assertStringContainsString('categoryName: item.category_name,', $customerDashboardTs);
    }

    public function testCompiledSharedBundleIncludesCartCategoryGroupingHelpers(): void
    {
        $sharedJs = file_get_contents($this->root . '/public/assets/js/shared/index.js');
        $this->assertIsString($sharedJs);

        $this->assertStringContainsString('groupedItems', $sharedJs);
        $this->assertStringContainsString('toggleCategory', $sharedJs);
        $this->assertStringContainsString('collapseAllCategories', $sharedJs);
        $this->assertStringContainsString('expandAllCategories', $sharedJs);
        $this->assertStringContainsString('syncProductMetadata', $sharedJs);
    }
}
