<?php
/** @var string $basePath */
$base = $basePath ?? '';
?>
<section class="max-w-md mx-auto px-4 py-6 sm:py-12">
    <div class="bg-white dark:bg-stone-900 rounded-2xl shadow-sm border border-stone-200 dark:border-stone-800 p-5 sm:p-8" x-data="loginForm()">
        <div class="mb-6">
            <h1 class="text-xl sm:text-2xl font-bold text-stone-900 dark:text-stone-100">Sign In</h1>
            <p class="mt-1 text-sm text-stone-600 dark:text-stone-400">Access your produce ordering account or supplier portal.</p>
        </div>

        <template x-if="error">
            <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-950/60 border border-red-200 dark:border-red-900/80 p-3.5 text-sm text-red-800 dark:text-red-300">
                <p x-text="error"></p>
                <template x-if="showResend">
                    <button
                        type="button"
                        @click="resend()"
                        :disabled="resending"
                        class="mt-2 inline-flex items-center text-xs font-semibold text-emerald-800 dark:text-emerald-300 underline hover:text-emerald-600 dark:hover:text-emerald-200 cursor-pointer"
                    >
                        <span x-text="resending ? 'Sending verification link...' : 'Resend verification email'"></span>
                    </button>
                </template>
            </div>
        </template>

        <template x-if="resendMessage">
            <div class="mb-4 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/80 p-3.5 text-sm text-emerald-900 dark:text-emerald-200">
                <p x-text="resendMessage"></p>
                <template x-if="debugVerifyUrl">
                    <a :href="debugVerifyUrl" class="mt-2 inline-block text-xs font-semibold underline text-emerald-700 dark:text-emerald-300 break-all">
                        Open local verification link
                    </a>
                </template>
            </div>
        </template>

        <form @submit.prevent="submit()" class="space-y-4">
            <div>
                <label for="login-email" class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Email Address</label>
                <input
                    id="login-email"
                    type="email"
                    x-model="email"
                    required
                    autocomplete="email"
                    class="w-full rounded-lg border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-950 px-3.5 py-2.5 text-base sm:text-sm text-stone-900 dark:text-stone-100 placeholder:text-stone-400 dark:placeholder:text-stone-500 focus:border-emerald-600 dark:focus:border-emerald-500 focus:ring-2 focus:ring-emerald-600/20 outline-none"
                    placeholder="you@company.co.za"
                >
                <p x-show="fieldErrors.email" x-text="fieldErrors.email" class="mt-1 text-xs text-red-600 dark:text-red-400"></p>
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <label for="login-password" class="block text-sm font-medium text-stone-700 dark:text-stone-300">Password</label>
                    <a href="<?= htmlspecialchars($base . '/forgot-password', ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-semibold text-emerald-700 dark:text-emerald-400 hover:text-emerald-600 dark:hover:text-emerald-300">Forgot password?</a>
                </div>
                <input
                    id="login-password"
                    type="password"
                    x-model="password"
                    required
                    autocomplete="current-password"
                    class="w-full rounded-lg border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-950 px-3.5 py-2.5 text-base sm:text-sm text-stone-900 dark:text-stone-100 placeholder:text-stone-400 dark:placeholder:text-stone-500 focus:border-emerald-600 dark:focus:border-emerald-500 focus:ring-2 focus:ring-emerald-600/20 outline-none"
                    placeholder="Enter your password"
                >
                <p x-show="fieldErrors.password" x-text="fieldErrors.password" class="mt-1 text-xs text-red-600 dark:text-red-400"></p>
            </div>

            <button
                type="submit"
                :disabled="submitting"
                class="w-full rounded-lg bg-emerald-700 hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500 disabled:opacity-60 text-white font-semibold py-2.5 px-4 text-sm transition-colors cursor-pointer"
            >
                <span x-text="submitting ? 'Signing in...' : 'Sign In'"></span>
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-stone-600 dark:text-stone-400">
            New to the platform?
            <a href="<?= htmlspecialchars($base . '/register', ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-700 dark:text-emerald-400 hover:text-emerald-600 dark:hover:text-emerald-300">Create an account</a>
        </p>
    </div>
</section>
