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
<html lang="en" data-theme="light" data-theme-preference="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="app-base-path" content="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="app-currency" content="<?= htmlspecialchars($currency ?? 'ZAR', ENT_QUOTES, 'UTF-8') ?>">
    <meta name="app-currency-symbol" content="<?= htmlspecialchars($currencySymbol ?? 'R', ENT_QUOTES, 'UTF-8') ?>">
    <script>
        (function () {
            try {
                var key = 'fresh_produce_theme_preference';
                var pref = window.localStorage.getItem(key) || 'light';
                var isSystem = pref === 'system';
                var prefersDark = typeof window.matchMedia === 'function' && window.matchMedia('(prefers-color-scheme: dark)').matches;
                var resolved = isSystem ? (prefersDark ? 'dark' : 'light') : pref;
                var isDark = resolved === 'dark';
                var root = document.documentElement;
                root.dataset.theme = resolved;
                root.dataset.themePreference = pref;
                root.classList.toggle('dark', isDark);
                root.style.colorScheme = isDark ? 'dark' : 'light';
            } catch (e) {
                document.documentElement.dataset.theme = 'light';
                document.documentElement.dataset.themePreference = 'light';
                document.documentElement.style.colorScheme = 'light';
            }
        })();
    </script>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase . '/css/app.css', ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 dark:bg-stone-950 dark:text-stone-100 antialiased flex flex-col overflow-x-hidden transition-colors duration-200">
    <header
        class="sticky top-0 z-40 bg-emerald-900 dark:bg-emerald-950 dark:border-b dark:border-emerald-900/80 text-white shadow-sm transition-colors duration-200"
        x-data="{ mobileMenuOpen: false }"
        @keydown.escape.window="mobileMenuOpen = false"
    >
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-3">
            <a href="<?= htmlspecialchars($base . '/', ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-base sm:text-lg tracking-tight truncate">
                Fresh Produce
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

                <button
                    type="button"
                    @click="$store.theme.cycle()"
                    :title="'Theme: ' + $store.theme.currentDefinition().label + ' (click to switch to ' + $store.theme.nextDefinition().label + ')'"
                    :aria-label="'Switch theme mode. Current: ' + $store.theme.currentDefinition().label"
                    data-theme-toggle="desktop"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-800/70 hover:bg-emerald-700 dark:bg-emerald-900/80 dark:hover:bg-emerald-800 px-2.5 py-1.5 text-xs font-semibold text-emerald-50 transition-colors cursor-pointer"
                >
                    <svg x-show="$store.theme.currentDefinition().icon === 'sun'" class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                    </svg>
                    <svg x-show="$store.theme.currentDefinition().icon === 'moon'" x-cloak class="w-4 h-4 text-emerald-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                    </svg>
                    <svg x-show="$store.theme.currentDefinition().icon === 'monitor'" x-cloak class="w-4 h-4 text-sky-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
                    </svg>
                    <svg x-show="$store.theme.currentDefinition().icon === 'palette'" x-cloak class="w-4 h-4 text-amber-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 005.304 0l6.401-6.402M6.75 21A3.75 3.75 0 013 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 003.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008z" />
                    </svg>
                    <span x-text="$store.theme.currentDefinition().shortLabel">Light</span>
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

            <!-- Mobile Header Actions (Theme + Cart + Hamburger) -->
            <div class="flex md:hidden items-center gap-2 shrink-0">
                <button
                    type="button"
                    @click="$store.theme.cycle()"
                    :title="'Theme: ' + $store.theme.currentDefinition().label"
                    :aria-label="'Switch theme mode. Current: ' + $store.theme.currentDefinition().label"
                    data-theme-toggle="mobile"
                    class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-800/80 hover:bg-emerald-700 dark:bg-emerald-900/80 dark:hover:bg-emerald-800 text-white transition-colors cursor-pointer"
                >
                    <svg x-show="$store.theme.currentDefinition().icon === 'sun'" class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                    </svg>
                    <svg x-show="$store.theme.currentDefinition().icon === 'moon'" x-cloak class="w-4 h-4 text-emerald-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                    </svg>
                    <svg x-show="$store.theme.currentDefinition().icon === 'monitor'" x-cloak class="w-4 h-4 text-sky-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
                    </svg>
                    <svg x-show="$store.theme.currentDefinition().icon === 'palette'" x-cloak class="w-4 h-4 text-amber-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 005.304 0l6.401-6.402M6.75 21A3.75 3.75 0 013 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 003.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008z" />
                    </svg>
                </button>

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

            <div class="pt-2 mt-2 border-t border-emerald-800 px-1">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-300/90 mb-1.5">Appearance</p>
                <div class="grid grid-cols-3 gap-1.5" role="group" aria-label="Theme mode selection">
                    <template x-for="mode in $store.theme.modes" :key="mode.id">
                        <button
                            type="button"
                            @click="$store.theme.setPreference(mode.id)"
                            :aria-pressed="($store.theme.preference === mode.id).toString()"
                            :class="$store.theme.preference === mode.id
                                ? 'bg-emerald-600 text-white border-emerald-400 shadow-xs'
                                : 'bg-emerald-900/70 text-emerald-100 border-emerald-800 hover:bg-emerald-800'"
                            class="rounded-lg border px-2.5 py-2 text-xs font-semibold transition-colors cursor-pointer text-center"
                            x-text="mode.shortLabel"
                        ></button>
                    </template>
                </div>
            </div>

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
                    'bg-emerald-50 text-emerald-900 border-emerald-200 dark:bg-emerald-950/95 dark:text-emerald-100 dark:border-emerald-800': toast.type === 'success',
                    'bg-red-50 text-red-900 border-red-200 dark:bg-red-950/95 dark:text-red-100 dark:border-red-800': toast.type === 'error',
                    'bg-stone-900 text-white border-stone-700 dark:bg-stone-800 dark:text-stone-100 dark:border-stone-700': toast.type === 'info'
                }"
            >
                <span class="min-w-0 break-words" x-text="toast.text"></span>
                <button type="button" @click="$store.toast.dismiss(toast.id)" class="opacity-70 hover:opacity-100 text-base leading-none px-1 cursor-pointer shrink-0" aria-label="Dismiss notification">&times;</button>
            </div>
        </template>
    </div>

    <footer class="border-t border-stone-200 dark:border-stone-800 bg-white dark:bg-stone-900 py-5 sm:py-6 px-4 text-center text-xs text-stone-500 dark:text-stone-400 transition-colors duration-200">
        &copy; <?= date('Y') ?> <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?> &middot; Currency: <?= htmlspecialchars($currency ?? 'ZAR', ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($currencySymbol ?? 'R', ENT_QUOTES, 'UTF-8') ?>)
    </footer>

    <script type="module" src="<?= htmlspecialchars($assetBase . '/js/shared/index.js', ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php if (!empty($pageScript)): ?>
        <script type="module" src="<?= htmlspecialchars($assetBase . '/js/' . ltrim($pageScript, '/'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endif; ?>
</body>
</html>
