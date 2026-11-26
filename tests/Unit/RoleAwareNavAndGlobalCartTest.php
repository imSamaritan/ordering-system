<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RoleAwareNavAndGlobalCartTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testBaseLayoutHidesStorefrontAndCartForSuppliersOnDesktopAndMobile(): void
    {
        $layout = file_get_contents($this->root . '/templates/layouts/base.php');
        $this->assertIsString($layout);

        // Desktop and mobile Storefront & Cart controls are gated out for supplier accounts
        $this->assertStringContainsString('data-nav-storefront="desktop"', $layout);
        $this->assertStringContainsString('data-nav-cart="desktop"', $layout);
        $this->assertStringContainsString('data-nav-cart="mobile"', $layout);
        $this->assertStringContainsString('data-nav-storefront="mobile"', $layout);

        $guardPattern = '/<template\s+x-if="!\$store\.auth\.user\s*\|\|\s*\$store\.auth\.user\.role\s*!==\s*\'supplier\'">/';
        $matches = [];
        preg_match_all($guardPattern, $layout, $matches);
        $this->assertGreaterThanOrEqual(4, count($matches[0]), 'Desktop and mobile Storefront and Cart links must be hidden for supplier role.');
    }

    public function testGlobalCartDrawerLivesInBaseLayoutAndNotDuplicatedInStorefront(): void
    {
        $layout = file_get_contents($this->root . '/templates/layouts/base.php');
        $home = file_get_contents($this->root . '/templates/home.php');
        $this->assertIsString($layout);
        $this->assertIsString($home);

        // Global drawer in base layout so customer & company dashboards can open it as an overlay
        $this->assertStringContainsString('x-data="cartDrawer()"', $layout);
        $this->assertStringContainsString('data-global-cart-drawer="true"', $layout);
        $this->assertStringContainsString(
            'x-show="$store.cart.isOpen && (!$store.auth.user || $store.auth.user.role !== \'supplier\')"',
            $layout
        );
        $this->assertStringContainsString(
            '($store.auth.user.role === \'customer\' || $store.auth.user.role === \'company\')',
            $layout
        );

        // Storefront template should no longer embed a duplicate cart dialog
        $this->assertStringNotContainsString('aria-label="Shopping Cart"', $home);
    }

    public function testCompiledSharedBundleRegistersGlobalCartDrawerAndOrderPlacedEvent(): void
    {
        $sharedJs = file_get_contents($this->root . '/public/assets/js/shared/index.js');
        $customerJs = file_get_contents($this->root . '/public/assets/js/pages/customerDashboard.js');
        $storefrontJs = file_get_contents($this->root . '/public/assets/js/pages/storefront.js');

        $this->assertIsString($sharedJs);
        $this->assertIsString($customerJs);
        $this->assertIsString($storefrontJs);

        $this->assertStringContainsString('cartDrawer', $sharedJs);
        $this->assertStringContainsString('order:placed', $sharedJs);
        $this->assertStringContainsString('order:placed', $customerJs);
        $this->assertStringContainsString('order:placed', $storefrontJs);
    }
}
