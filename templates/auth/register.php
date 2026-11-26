<?php
/** @var string $basePath */
$base = $basePath ?? '';
?>
<section class="max-w-lg mx-auto px-4 py-6 sm:py-12">
    <div class="bg-white rounded-2xl shadow-sm border border-stone-200 p-5 sm:p-8" x-data="registerForm()">
        <div class="mb-6">
            <h1 class="text-xl sm:text-2xl font-bold text-stone-900">Create Account</h1>
            <p class="mt-1 text-sm text-stone-600">Register as an individual customer or a wholesale company buyer.</p>
        </div>

        <template x-if="successMessage">
            <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-5 text-sm text-emerald-900 space-y-3">
                <p class="font-semibold" x-text="successMessage"></p>
                <template x-if="debugVerifyUrl">
                    <div class="pt-2 border-t border-emerald-200">
                        <p class="text-xs text-emerald-700 mb-1">Local dev verification link:</p>
                        <a :href="debugVerifyUrl" class="text-xs font-semibold underline text-emerald-800 break-all" x-text="debugVerifyUrl"></a>
                    </div>
                </template>
                <div>
                    <a href="<?= htmlspecialchars($base . '/login', ENT_QUOTES, 'UTF-8') ?>" class="inline-block rounded-lg bg-emerald-700 text-white px-4 py-2 text-xs font-semibold hover:bg-emerald-800">
                        Go to Sign In
                    </a>
                </div>
            </div>
        </template>

        <template x-if="!successMessage">
            <form @submit.prevent="submit()" class="space-y-4">
                <template x-if="error">
                    <div class="rounded-lg bg-red-50 border border-red-200 p-3.5 text-sm text-red-800" x-text="error"></div>
                </template>

                <div>
                    <label class="block text-sm font-medium text-stone-700 mb-1.5">Account Type</label>
                    <div class="grid grid-cols-2 gap-2.5 sm:gap-3">
                        <button
                            type="button"
                            @click="role = 'customer'"
                            :class="role === 'customer' ? 'border-emerald-600 bg-emerald-50 text-emerald-900' : 'border-stone-300 bg-white text-stone-700'"
                            class="rounded-lg border px-2.5 sm:px-4 py-2.5 text-xs sm:text-sm font-medium transition-colors cursor-pointer text-center"
                        >
                            Customer
                        </button>
                        <button
                            type="button"
                            @click="role = 'company'"
                            :class="role === 'company' ? 'border-emerald-600 bg-emerald-50 text-emerald-900' : 'border-stone-300 bg-white text-stone-700'"
                            class="rounded-lg border px-2.5 sm:px-4 py-2.5 text-xs sm:text-sm font-medium transition-colors cursor-pointer text-center"
                        >
                            Company / Business
                        </button>
                    </div>
                </div>

                <div x-show="role === 'company'" x-transition>
                    <label for="reg-company" class="block text-sm font-medium text-stone-700 mb-1">Company Name</label>
                    <input
                        id="reg-company"
                        type="text"
                        x-model="company_name"
                        :required="role === 'company'"
                        class="w-full rounded-lg border border-stone-300 px-3.5 py-2.5 text-base sm:text-sm focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 outline-none"
                        placeholder="e.g. Green Valley Bistro (Pty) Ltd"
                    >
                    <p x-show="fieldErrors.company_name" x-text="fieldErrors.company_name" class="mt-1 text-xs text-red-600"></p>
                </div>

                <div>
                    <label for="reg-contact" class="block text-sm font-medium text-stone-700 mb-1">Contact Name</label>
                    <input
                        id="reg-contact"
                        type="text"
                        x-model="contact_name"
                        required
                        class="w-full rounded-lg border border-stone-300 px-3.5 py-2.5 text-base sm:text-sm focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 outline-none"
                        placeholder="Full name"
                    >
                    <p x-show="fieldErrors.contact_name" x-text="fieldErrors.contact_name" class="mt-1 text-xs text-red-600"></p>
                </div>

                <div>
                    <label for="reg-email" class="block text-sm font-medium text-stone-700 mb-1">Email Address</label>
                    <input
                        id="reg-email"
                        type="email"
                        x-model="email"
                        required
                        autocomplete="email"
                        class="w-full rounded-lg border border-stone-300 px-3.5 py-2.5 text-base sm:text-sm focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 outline-none"
                        placeholder="you@company.co.za"
                    >
                    <p x-show="fieldErrors.email" x-text="fieldErrors.email" class="mt-1 text-xs text-red-600"></p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="reg-phone" class="block text-sm font-medium text-stone-700 mb-1">Phone (optional)</label>
                        <input
                            id="reg-phone"
                            type="tel"
                            x-model="phone"
                            class="w-full rounded-lg border border-stone-300 px-3.5 py-2.5 text-base sm:text-sm focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 outline-none"
                            placeholder="+27 82 000 0000"
                        >
                        <p x-show="fieldErrors.phone" x-text="fieldErrors.phone" class="mt-1 text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label for="reg-location" class="block text-sm font-medium text-stone-700 mb-1">Delivery Location (optional)</label>
                        <input
                            id="reg-location"
                            type="text"
                            x-model="location"
                            class="w-full rounded-lg border border-stone-300 px-3.5 py-2.5 text-base sm:text-sm focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 outline-none"
                            placeholder="City / Suburb"
                        >
                        <p x-show="fieldErrors.location" x-text="fieldErrors.location" class="mt-1 text-xs text-red-600"></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="reg-password" class="block text-sm font-medium text-stone-700 mb-1">Password</label>
                        <input
                            id="reg-password"
                            type="password"
                            x-model="password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            class="w-full rounded-lg border border-stone-300 px-3.5 py-2.5 text-base sm:text-sm focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 outline-none"
                            placeholder="At least 8 characters"
                        >
                        <p x-show="fieldErrors.password" x-text="fieldErrors.password" class="mt-1 text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label for="reg-confirm" class="block text-sm font-medium text-stone-700 mb-1">Confirm Password</label>
                        <input
                            id="reg-confirm"
                            type="password"
                            x-model="confirm_password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            class="w-full rounded-lg border border-stone-300 px-3.5 py-2.5 text-base sm:text-sm focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 outline-none"
                            placeholder="Repeat password"
                        >
                        <p x-show="fieldErrors.confirm_password" x-text="fieldErrors.confirm_password" class="mt-1 text-xs text-red-600"></p>
                    </div>
                </div>

                <button
                    type="submit"
                    :disabled="submitting"
                    class="w-full rounded-lg bg-emerald-700 hover:bg-emerald-800 disabled:opacity-60 text-white font-semibold py-2.5 px-4 text-sm transition-colors cursor-pointer"
                >
                    <span x-text="submitting ? 'Creating account...' : 'Create Account'"></span>
                </button>
            </form>
        </template>

        <p class="mt-6 text-center text-sm text-stone-600">
            Already have an account?
            <a href="<?= htmlspecialchars($base . '/login', ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-700 hover:text-emerald-600">Sign in</a>
        </p>
    </div>
</section>
