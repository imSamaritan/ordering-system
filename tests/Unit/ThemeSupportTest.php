<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ThemeSupportTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testBaseLayoutDefaultsSafelyToLightModeAndIncludesThemeControls(): void
    {
        $layout = file_get_contents($this->root . '/templates/layouts/base.php');
        $this->assertIsString($layout);

        // Safe light mode SSR defaults
        $this->assertStringContainsString('data-theme="light"', $layout);
        $this->assertStringContainsString('data-theme-preference="light"', $layout);
        $this->assertStringContainsString('bg-stone-50 text-stone-900 dark:bg-stone-950 dark:text-stone-100', $layout);

        // Zero-FOUC inline boot script
        $this->assertStringContainsString("fresh_produce_theme_preference", $layout);
        $this->assertStringContainsString("prefers-color-scheme: dark", $layout);

        // Desktop & mobile theme switchers + extensible mode list
        $this->assertStringContainsString('data-theme-toggle="desktop"', $layout);
        $this->assertStringContainsString('data-theme-toggle="mobile"', $layout);
        $this->assertStringContainsString('x-for="mode in $store.theme.modes"', $layout);
    }

    public function testAllTemplatesContainDarkModeVariantsWhilePreservingLightClasses(): void
    {
        $templates = [
            'templates/layouts/base.php',
            'templates/home.php',
            'templates/auth/login.php',
            'templates/auth/register.php',
            'templates/auth/verify-email.php',
            'templates/customer/dashboard.php',
            'templates/supplier/dashboard.php',
        ];

        foreach ($templates as $relPath) {
            $content = file_get_contents($this->root . '/' . $relPath);
            $this->assertIsString($content, "Failed reading {$relPath}");
            $this->assertStringContainsString('dark:', $content, "{$relPath} should define dark mode variants.");
            $this->assertStringContainsString('bg-white', $content, "{$relPath} should preserve light mode surface classes.");
        }
    }

    public function testCompiledAssetsIncludeCustomDarkVariantAndExtensibleThemeStore(): void
    {
        $css = file_get_contents($this->root . '/public/assets/css/app.css');
        $this->assertIsString($css);
        $this->assertMatchesRegularExpression('/\[data-theme="?dark"?\]/', $css);
        $this->assertMatchesRegularExpression('/\[data-theme="?light"?\]/', $css);
        $this->assertStringContainsString('color-scheme:dark', str_replace(' ', '', $css));
        $this->assertStringContainsString('color-scheme:light', str_replace(' ', '', $css));

        $js = file_get_contents($this->root . '/public/assets/js/shared/index.js');
        $this->assertIsString($js);
        $this->assertStringContainsString('fresh_produce_theme_preference', $js);
        $this->assertStringContainsString('theme:changed', $js);
    }
}
