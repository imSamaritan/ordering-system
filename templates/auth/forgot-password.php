<?php
/** @var string $basePath */
$base = $basePath ?? '';
?>
<section class="max-w-md mx-auto px-4 py-6 sm:py-12">
    <div class="bg-white dark:bg-stone-900 rounded-2xl shadow-sm border border-stone-200 dark:border-stone-800 p-5 sm:p-8" x-data="forgotPasswordForm()">
        <div class="mb-6">
            <h1 class="text-xl sm:text-2xl font-bold text-stone-900 dark:text-stone-100">Forgot Password</h1>
            <p class="mt-1 text-sm text-stone-600 dark:text-stone-400">Enter your email address and we will send you a password reset link valid for 7 minutes.</p>
        </div>

        <template x-if="error">
            <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-950/60 border border-red-200 dark:border-red-900/80 p-3.5 text-sm text-red-800 dark:text-red-300">
                <p x-text="error"></p>
            </div>
        </template>

        <template x-if="successMessage">
            <div class="mb-4 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/80 p-3.5 text-sm text-emerald-900 dark:text-emerald-200">
                <p x-text="successMessage"></p>
                <template x-if="debugResetUrl">
                    <a :href="debugResetUrl" class="mt-2 inline-block text-xs font-semibold underline text-emerald-700 dark:text-emerald-300 break-all">
                        Open local password reset link
                    </a>
                </template>
            </div>
        </template>

        <form @submit.prevent="submit()" class="space-y-4">
            <div>
                <label for="forgot-email" class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Email Address</label>
                <input
                    id="forgot-email"
                    type="email"
                    x-model="email"
                    required
                    autocomplete="email"
                    class="w-full rounded-lg border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-950 px-3.5 py-2.5 text-base sm:text-sm text-stone-900 dark:text-stone-100 placeholder:text-stone-400 dark:placeholder:text-stone-500 focus:border-emerald-600 dark:focus:border-emerald-500 focus:ring-2 focus:ring-emerald-600/20 outline-none"
                    placeholder="you@company.co.za"
                >
            </div>

            <button
                type="submit"
                :disabled="submitting"
                class="w-full rounded-lg bg-emerald-700 hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500 disabled:opacity-60 text-white font-semibold py-2.5 px-4 text-sm transition-colors cursor-pointer"
            >
                <span x-text="submitting ? 'Sending reset link...' : 'Send Reset Link'"></span>
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-stone-600 dark:text-stone-400">
            Remembered your password?
            <a href="<?= htmlspecialchars($base . '/login', ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-700 dark:text-emerald-400 hover:text-emerald-600 dark:hover:text-emerald-300">Back to Sign In</a>
        </p>
    </div>
</section>
