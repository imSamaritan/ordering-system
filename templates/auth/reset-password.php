<?php
/**
 * @var string $basePath
 * @var string $token
 * @var bool $tokenValid
 */
$base = $basePath ?? '';
$tokenValue = (string) ($token ?? '');
$isValid = !empty($tokenValid);
?>
<section class="max-w-md mx-auto px-4 py-6 sm:py-12">
    <?php if (!$isValid): ?>
        <div class="bg-white dark:bg-stone-900 rounded-2xl shadow-sm border border-stone-200 dark:border-stone-800 p-5 sm:p-8 text-center">
            <div class="w-12 h-12 rounded-full bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 mx-auto flex items-center justify-center text-xl font-bold mb-4">!</div>
            <h1 class="text-xl font-bold text-stone-900 dark:text-stone-100">Reset Link Invalid or Expired</h1>
            <p class="mt-2 text-sm text-stone-600 dark:text-stone-400">
                This password reset link is invalid or has expired. Reset links expire after 7 minutes and can only be used once.
            </p>
            <div class="mt-6 flex flex-col gap-3">
                <a
                    href="<?= htmlspecialchars($base . '/forgot-password', ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-block w-full rounded-lg bg-emerald-700 hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500 text-white font-semibold px-6 py-2.5 text-sm transition-colors"
                >
                    Request a New Reset Link
                </a>
                <a
                    href="<?= htmlspecialchars($base . '/login', ENT_QUOTES, 'UTF-8') ?>"
                    class="text-sm font-semibold text-emerald-700 dark:text-emerald-400 hover:text-emerald-600 dark:hover:text-emerald-300"
                >
                    Back to Sign In
                </a>
            </div>
        </div>
    <?php else: ?>
        <div
            class="bg-white dark:bg-stone-900 rounded-2xl shadow-sm border border-stone-200 dark:border-stone-800 p-5 sm:p-8"
            x-data="resetPasswordForm(<?= htmlspecialchars(json_encode($tokenValue, JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8') ?>)"
        >
            <template x-if="successMessage">
                <div class="text-center">
                    <div class="w-12 h-12 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 mx-auto flex items-center justify-center text-xl font-bold mb-4">&#10003;</div>
                    <h1 class="text-xl font-bold text-stone-900 dark:text-stone-100">Password Updated</h1>
                    <p class="mt-2 text-sm text-stone-600 dark:text-stone-400" x-text="successMessage"></p>
                    <a
                        href="<?= htmlspecialchars($base . '/login', ENT_QUOTES, 'UTF-8') ?>"
                        class="mt-6 inline-block w-full sm:w-auto rounded-lg bg-emerald-700 hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500 text-white font-semibold px-6 py-2.5 text-sm transition-colors"
                    >
                        Sign In Now
                    </a>
                </div>
            </template>

            <div x-show="!successMessage">
                <div class="mb-6">
                    <h1 class="text-xl sm:text-2xl font-bold text-stone-900 dark:text-stone-100">Set New Password</h1>
                    <p class="mt-1 text-sm text-stone-600 dark:text-stone-400">Choose a strong password of at least 8 characters for your account.</p>
                </div>

                <template x-if="error">
                    <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-950/60 border border-red-200 dark:border-red-900/80 p-3.5 text-sm text-red-800 dark:text-red-300">
                        <p x-text="error"></p>
                        <a
                            href="<?= htmlspecialchars($base . '/forgot-password', ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 inline-block text-xs font-semibold underline text-emerald-800 dark:text-emerald-300 hover:text-emerald-600 dark:hover:text-emerald-200"
                        >
                            Request a new reset link
                        </a>
                    </div>
                </template>

                <form @submit.prevent="submit()" class="space-y-4">
                    <div>
                        <label for="reset-password" class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">New Password</label>
                        <input
                            id="reset-password"
                            type="password"
                            x-model="password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            class="w-full rounded-lg border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-950 px-3.5 py-2.5 text-base sm:text-sm text-stone-900 dark:text-stone-100 placeholder:text-stone-400 dark:placeholder:text-stone-500 focus:border-emerald-600 dark:focus:border-emerald-500 focus:ring-2 focus:ring-emerald-600/20 outline-none"
                            placeholder="Minimum 8 characters"
                        >
                        <p x-show="fieldErrors.password" x-text="fieldErrors.password" class="mt-1 text-xs text-red-600 dark:text-red-400"></p>
                    </div>

                    <div>
                        <label for="reset-confirm-password" class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Confirm New Password</label>
                        <input
                            id="reset-confirm-password"
                            type="password"
                            x-model="confirm_password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            class="w-full rounded-lg border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-950 px-3.5 py-2.5 text-base sm:text-sm text-stone-900 dark:text-stone-100 placeholder:text-stone-400 dark:placeholder:text-stone-500 focus:border-emerald-600 dark:focus:border-emerald-500 focus:ring-2 focus:ring-emerald-600/20 outline-none"
                            placeholder="Re-enter your new password"
                        >
                        <p x-show="fieldErrors.confirm_password" x-text="fieldErrors.confirm_password" class="mt-1 text-xs text-red-600 dark:text-red-400"></p>
                    </div>

                    <button
                        type="submit"
                        :disabled="submitting"
                        class="w-full rounded-lg bg-emerald-700 hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500 disabled:opacity-60 text-white font-semibold py-2.5 px-4 text-sm transition-colors cursor-pointer"
                    >
                        <span x-text="submitting ? 'Updating password...' : 'Update Password'"></span>
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-stone-600 dark:text-stone-400">
                    <a href="<?= htmlspecialchars($base . '/login', ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-700 dark:text-emerald-400 hover:text-emerald-600 dark:hover:text-emerald-300">Back to Sign In</a>
                </p>
            </div>
        </div>
    <?php endif; ?>
</section>
