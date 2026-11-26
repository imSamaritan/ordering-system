<?php
/**
 * @var string $content
 * @var string $appName
 * @var string $basePath
 * @var string $currency
 * @var string $currencySymbol
 * @var string|null $title
 * @var string|null $pageScript
 */
$pageTitle = isset($title) && $title !== '' ? "{$title} | {$appName}" : $appName;
$assetBase = ($basePath ?? '') . '/assets';
$base = $basePath ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="app-base-path" content="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="app-currency" content="<?= htmlspecialchars($currency ?? 'ZAR', ENT_QUOTES, 'UTF-8') ?>">
    <meta name="app-currency-symbol" content="<?= htmlspecialchars($currencySymbol ?? 'R', ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase . '/css/app.css', ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased flex flex-col overflow-x-hidden">
    <header
        class="sticky top-0 z-40 bg-emerald-900 text-white shadow-sm"
        x-data="{ mobileMenuOpen: false }"
        @keydown.escape.window="mobileMenuOpen = false"
    >
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-3">
            <a href="<?= htmlspecialchars($base . '/', ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-base sm:text-lg tracking-tight truncate">
                <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>
            </a>

            <!-- Desktop Navigation -->
            <nav class="hidden md:flex items-center gap-5 text-sm font-medium shrink-0" aria-label="Main navigation">
                <a href="<?= htmlspecialchars($base . '/', ENT_QUOTES, 'UTF-8') ?>" class="hover:text-emerald-200 transition-colors">Storefront</a>

                <button
                    type="button"
                    @click="$store.cart.toggle()"
                    class="relative inline-flex items-center gap-1.5 rounded-lg bg-emerald-800/80 hover:bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white transition-colors cursor-pointer"
                    aria-label="Open shopping cart"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span>Cart</span>
                    <span
                        x-show="$store.cart.totalItems > 0"
                        x-text="$store.cart.totalItems"
                        class="inline-flex items-center justify-center min-w-5 h-5 px-1 rounded-full bg-amber-400 text-emerald-950 text-[11px] font-bold"
                    ></span>
                </button>

                <template x-if="$store.auth.user && ($store.auth.user.role === 'customer' || $store.auth.user.role === 'company')">
                    <a href="<?= htmlspecialchars($base . '/dashboard', ENT_QUOTES, 'UTF-8') ?>" class="hover:text-emerald-200 transition-colors">Customer Portal</a>
                </template>

                <template x-if="$store.auth.user && $store.auth.user.role === 'supplier'">
                    <div class="flex items-center gap-4">
                        <a href="<?= htmlspecialchars($base . '/supplier', ENT_QUOTES, 'UTF-8') ?>" class="hover:text-emerald-200 transition-colors">Supplier Portal</a>
                        <a href="<?= htmlspecialchars($base . '/supplier/inventory', ENT_QUOTES, 'UTF-8') ?>" class="hover:text-emerald-200 transition-colors">Inventory</a>
                    </div>
                </template>

                <template x-if="!$store.auth.user">
                    <div class="flex items-center gap-3">
                        <a href="<?= htmlspecialchars($base . '/login', ENT_QUOTES, 'UTF-8') ?>" class="hover:text-emerald-200 transition-colors">Sign In</a>
                        <a href="<?= htmlspecialchars($base . '/register', ENT_QUOTES, 'UTF-8') ?>" class="bg-emerald-600 hover:bg-emerald-500 text-white px-3.5 py-1.5 rounded-lg transition-colors">Register</a>
                    </div>
                </template>

                <template x-if="$store.auth.user">
                    <div class="flex items-center gap-3 pl-3 border-l border-emerald-700">
                        <span class="text-emerald-100 text-xs hidden lg:inline" x-text="$store.auth.user.contact_name + ' (' + $store.auth.user.role + ')'"></span>
                        <button
                            type="button"
                            @click="$store.auth.logout().then(() => { $store.toast.push('Signed out.', 'info'); window.location.href = '<?= htmlspecialchars($base . '/login', ENT_QUOTES, 'UTF-8') ?>'; })"
                            class="bg-emerald-800 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors cursor-pointer"
                        >
                            Sign Out
                        </button>
                    </div>
                </template>
            </nav>

            <!-- Mobile Header Actions (Cart + Hamburger) -->
            <div class="flex md:hidden items-center gap-2 shrink-0">
                <button
                    type="button"
                    @click="mobileMenuOpen = false; $store.cart.toggle()"
                    class="relative inline-flex items-center gap-1.5 rounded-lg bg-emerald-800/90 hover:bg-emerald-700 px-3 min-h-10 text-xs font-semibold text-white transition-colors cursor-pointer"
                    aria-label="Open shopping cart"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span>Cart</span>
                    <span
                        x-show="$store.cart.totalItems > 0"
                        x-text="$store.cart.totalItems"
                        class="inline-flex items-center justify-center min-w-5 h-5 px-1 rounded-full bg-amber-400 text-emerald-950 text-[11px] font-bold"
                    ></span>
                </button>

                <button
                    type="button"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    :aria-expanded="mobileMenuOpen.toString()"
                    aria-controls="mobile-nav-menu"
                    aria-label="Toggle navigation menu"
                    class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-800/80 hover:bg-emerald-700 text-white transition-colors cursor-pointer"
                >
                    <svg x-show="!mobileMenuOpen" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg x-show="mobileMenuOpen" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Collapsible Navigation Menu -->
        <nav
            id="mobile-nav-menu"
            x-show="mobileMenuOpen"
            x-cloak
            x-transition
            @click.away="mobileMenuOpen = false"
            class="md:hidden border-t border-emerald-800 bg-emerald-950/95 backdrop-blur-xs px-4 py-3 space-y-1.5 text-sm font-medium"
            aria-label="Mobile navigation"
        >
            <a
                href="<?= htmlspecialchars($base . '/', ENT_QUOTES, 'UTF-8') ?>"
                @click="mobileMenuOpen = false"
                class="block rounded-lg px-3 py-2.5 text-emerald-50 hover:bg-emerald-800/80 transition-colors"
            >
                Storefront
            </a>

            <template x-if="$store.auth.user && ($store.auth.user.role === 'customer' || $store.auth.user.role === 'company')">
                <a
                    href="<?= htmlspecialchars($base . '/dashboard', ENT_QUOTES, 'UTF-8') ?>"
                    @click="mobileMenuOpen = false"
                    class="block rounded-lg px-3 py-2.5 text-emerald-50 hover:bg-emerald-800/80 transition-colors"
                >
                    Customer Portal
                </a>
            </template>

            <template x-if="$store.auth.user && $store.auth.user.role === 'supplier'">
                <div class="space-y-1.5">
                    <a
                        href="<?= htmlspecialchars($base . '/supplier', ENT_QUOTES, 'UTF-8') ?>"
                        @click="mobileMenuOpen = false"
                        class="block rounded-lg px-3 py-2.5 text-emerald-50 hover:bg-emerald-800/80 transition-colors"
                    >
                        Supplier Portal
                    </a>
                    <a
                        href="<?= htmlspecialchars($base . '/supplier/inventory', ENT_QUOTES, 'UTF-8') ?>"
                        @click="mobileMenuOpen = false"
                        class="block rounded-lg px-3 py-2.5 text-emerald-50 hover:bg-emerald-800/80 transition-colors"
                    >
                        Inventory &amp; Pricing
                    </a>
                </div>
            </template>

            <template x-if="!$store.auth.user">
                <div class="pt-2 mt-2 border-t border-emerald-800 grid grid-cols-2 gap-2">
                    <a
                        href="<?= htmlspecialchars($base . '/login', ENT_QUOTES, 'UTF-8') ?>"
                        @click="mobileMenuOpen = false"
                        class="text-center rounded-lg border border-emerald-700 bg-emerald-900 hover:bg-emerald-800 px-3 py-2.5 text-sm font-semibold text-white transition-colors"
                    >
                        Sign In
                    </a>
                    <a
                        href="<?= htmlspecialchars($base . '/register', ENT_QUOTES, 'UTF-8') ?>"
                        @click="mobileMenuOpen = false"
                        class="text-center rounded-lg bg-emerald-600 hover:bg-emerald-500 px-3 py-2.5 text-sm font-semibold text-white transition-colors"
                    >
                        Register
                    </a>
                </div>
            </template>

            <template x-if="$store.auth.user">
                <div class="pt-2.5 mt-2 border-t border-emerald-800 flex items-center justify-between gap-3 px-1">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-white truncate" x-text="$store.auth.user.contact_name"></p>
                        <p class="text-[11px] text-emerald-300 capitalize" x-text="$store.auth.user.role"></p>
                    </div>
                    <button
                        type="button"
                        @click="mobileMenuOpen = false; $store.auth.logout().then(() => { $store.toast.push('Signed out.', 'info'); window.location.href = '<?= htmlspecialchars($base . '/login', ENT_QUOTES, 'UTF-8') ?>'; })"
                        class="shrink-0 bg-emerald-800 hover:bg-emerald-700 text-white px-3.5 py-2 rounded-lg text-xs font-semibold transition-colors cursor-pointer"
                    >
                        Sign Out
                    </button>
                </div>
            </template>
        </nav>
    </header>

    <main class="flex-1">
        <?= $content ?>
    </main>

    <!-- Global Toast Stack -->
    <div x-data class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-4 z-50 flex flex-col gap-2 sm:max-w-sm sm:w-full pointer-events-none">
        <template x-for="toast in $store.toast.messages" :key="toast.id">
            <div
                class="pointer-events-auto rounded-lg shadow-lg px-4 py-3 text-sm font-medium flex items-center justify-between gap-3 border"
                :class="{
                    'bg-emerald-50 text-emerald-900 border-emerald-200': toast.type === 'success',
                    'bg-red-50 text-red-900 border-red-200': toast.type === 'error',
                    'bg-stone-900 text-white border-stone-700': toast.type === 'info'
                }"
            >
                <span class="min-w-0 break-words" x-text="toast.text"></span>
                <button type="button" @click="$store.toast.dismiss(toast.id)" class="opacity-70 hover:opacity-100 text-base leading-none px-1 cursor-pointer shrink-0" aria-label="Dismiss notification">&times;</button>
            </div>
        </template>
    </div>

    <footer class="border-t border-stone-200 bg-white py-5 sm:py-6 px-4 text-center text-xs text-stone-500">
        &copy; <?= date('Y') ?> <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?> &middot; Currency: <?= htmlspecialchars($currency ?? 'ZAR', ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($currencySymbol ?? 'R', ENT_QUOTES, 'UTF-8') ?>)
    </footer>

    <script type="module" src="<?= htmlspecialchars($assetBase . '/js/shared/index.js', ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php if (!empty($pageScript)): ?>
        <script type="module" src="<?= htmlspecialchars($assetBase . '/js/' . ltrim($pageScript, '/'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endif; ?>
</body>
</html>
