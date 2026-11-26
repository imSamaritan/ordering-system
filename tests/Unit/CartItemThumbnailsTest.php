<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CartItemThumbnailsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCartDrawerRendersLeftAlignedProductThumbnailWithFallback(): void
    {
        $layout = file_get_contents($this->root . '/templates/layouts/base.php');
        $this->assertIsString($layout);

        // Fixed-size rounded square thumbnail container with light/dark theme styling and shrink-0
        $this->assertStringContainsString('data-cart-item-thumb="true"', $layout);
        $this->assertStringContainsString(
            'class="w-12 h-12 rounded-lg bg-stone-100 dark:bg-stone-800 border border-stone-200 dark:border-stone-700 overflow-hidden shrink-0 flex items-center justify-center"',
            $layout
        );

        // Image renders with object-cover, base-path resolved URL, and broken image handler
        $this->assertStringContainsString('x-if="productImageUrl(item.imagePath) && !brokenImages[item.productId]"', $layout);
        $this->assertStringContainsString(':src="productImageUrl(item.imagePath)"', $layout);
        $this->assertStringContainsString(':alt="item.description"', $layout);
        $this->assertStringContainsString('@error="markImageBroken(item.productId)"', $layout);
        $this->assertStringContainsString('class="w-full h-full object-cover"', $layout);

        // Graceful IMG placeholder fallback when missing or broken
        $this->assertStringContainsString('x-if="!productImageUrl(item.imagePath) || brokenImages[item.productId]"', $layout);
        $this->assertStringContainsString('<span class="text-[10px] font-semibold text-stone-400 dark:text-stone-500">IMG</span>', $layout);

        // Thumbnail appears to the left of the item description inside the cart item row
        $thumbPos = strpos($layout, 'data-cart-item-thumb="true"');
        $descPos = strpos($layout, 'x-text="item.description"');
        $this->assertIsInt($thumbPos);
        $this->assertIsInt($descPos);
        $this->assertLessThan($descPos, $thumbPos, 'Product thumbnail must appear to the left of the cart item description.');
    }

    public function testCartDrawerResolvesRelativeAndExternalImageUrlsAndTracksBrokenImages(): void
    {
        $cartDrawerTs = file_get_contents($this->root . '/resources/ts/shared/cartDrawer.ts');
        $this->assertIsString($cartDrawerTs);

        $this->assertStringContainsString('brokenImages: Record<number, boolean>;', $cartDrawerTs);
        $this->assertStringContainsString('productImageUrl(imagePath: string | null | undefined): string', $cartDrawerTs);
        $this->assertStringContainsString("trimmed.startsWith('http://') || trimmed.startsWith('https://')", $cartDrawerTs);
        $this->assertStringContainsString('${getBasePath()}/${trimmed.replace(/^\\/+/, \'\')}', $cartDrawerTs);
        $this->assertStringContainsString('markImageBroken(productId: number): void', $cartDrawerTs);
    }

    public function testCartStoreRefreshesImagePathOnRepeatedIncrementCalls(): void
    {
        $cartStoreTs = file_get_contents($this->root . '/resources/ts/shared/cartStore.ts');
        $this->assertIsString($cartStoreTs);

        $this->assertStringContainsString('existing.imagePath = product.image_path;', $cartStoreTs);
    }

    public function testCompiledSharedBundleIncludesCartThumbnailHelpers(): void
    {
        $sharedJs = file_get_contents($this->root . '/public/assets/js/shared/index.js');
        $this->assertIsString($sharedJs);

        $this->assertStringContainsString('productImageUrl', $sharedJs);
        $this->assertStringContainsString('markImageBroken', $sharedJs);
    }
}
