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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="app-base-path" content="<?= htmlspecialchars($basePath ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <meta name="app-currency" content="<?= htmlspecialchars($currency ?? 'ZAR', ENT_QUOTES, 'UTF-8') ?>">
    <meta name="app-currency-symbol" content="<?= htmlspecialchars($currencySymbol ?? 'R', ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase . '/css/app.css', ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased flex flex-col">
    <header class="bg-emerald-900 text-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="<?= htmlspecialchars(($basePath ?? '') . '/', ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-lg tracking-tight">
                <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>
            </a>
            <nav class="flex items-center gap-6 text-sm font-medium">
                <a href="<?= htmlspecialchars(($basePath ?? '') . '/', ENT_QUOTES, 'UTF-8') ?>" class="hover:text-emerald-200 transition-colors">Storefront</a>
                <a href="<?= htmlspecialchars(($basePath ?? '') . '/dashboard', ENT_QUOTES, 'UTF-8') ?>" class="hover:text-emerald-200 transition-colors">Customer Portal</a>
                <a href="<?= htmlspecialchars(($basePath ?? '') . '/supplier', ENT_QUOTES, 'UTF-8') ?>" class="hover:text-emerald-200 transition-colors">Supplier Portal</a>
            </nav>
        </div>
    </header>

    <main class="flex-1">
        <?= $content ?>
    </main>

    <footer class="border-t border-stone-200 bg-white py-6 text-center text-xs text-stone-500">
        &copy; <?= date('Y') ?> <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?> &middot; Currency: <?= htmlspecialchars($currency ?? 'ZAR', ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($currencySymbol ?? 'R', ENT_QUOTES, 'UTF-8') ?>)
    </footer>

    <script type="module" src="<?= htmlspecialchars($assetBase . '/js/shared/index.js', ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php if (!empty($pageScript)): ?>
        <script type="module" src="<?= htmlspecialchars($assetBase . '/js/' . ltrim($pageScript, '/'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endif; ?>
</body>
</html>
