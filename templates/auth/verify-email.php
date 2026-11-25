<?php
/**
 * @var string $basePath
 * @var bool $verified
 * @var string $verificationMessage
 */
$base = $basePath ?? '';
$isVerified = !empty($verified);
$message = (string) ($verificationMessage ?? '');
?>
<section class="max-w-md mx-auto px-4 py-14">
    <div class="bg-white rounded-2xl shadow-sm border border-stone-200 p-8 text-center">
        <?php if ($isVerified): ?>
            <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-700 mx-auto flex items-center justify-center text-xl font-bold mb-4">&#10003;</div>
            <h1 class="text-xl font-bold text-stone-900">Email Verified</h1>
            <p class="mt-2 text-sm text-stone-600"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
            <a href="<?= htmlspecialchars($base . '/login', ENT_QUOTES, 'UTF-8') ?>" class="mt-6 inline-block rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white font-semibold px-6 py-2.5 text-sm transition-colors">
                Sign In Now
            </a>
        <?php else: ?>
            <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-700 mx-auto flex items-center justify-center text-xl font-bold mb-4">!</div>
            <h1 class="text-xl font-bold text-stone-900">Verification Issue</h1>
            <p class="mt-2 text-sm text-stone-600"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>

            <div class="mt-6 pt-6 border-t border-stone-200 text-left" x-data="verifyResendForm()">
                <p class="text-xs font-medium text-stone-700 mb-2">Need a fresh verification link?</p>
                <form @submit.prevent="submit()" class="flex gap-2">
                    <input
                        type="email"
                        x-model="email"
                        required
                        placeholder="you@company.co.za"
                        class="flex-1 rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-emerald-600 outline-none"
                    >
                    <button
                        type="submit"
                        :disabled="submitting"
                        class="rounded-lg bg-emerald-700 hover:bg-emerald-800 disabled:opacity-60 text-white px-4 py-2 text-xs font-semibold cursor-pointer"
                    >
                        <span x-text="submitting ? 'Sending...' : 'Resend'"></span>
                    </button>
                </form>
                <p x-show="error" x-text="error" class="mt-2 text-xs text-red-600"></p>
                <div x-show="message" class="mt-2 text-xs text-emerald-700">
                    <p x-text="message"></p>
                    <a x-show="debugVerifyUrl" :href="debugVerifyUrl" class="underline font-semibold break-all mt-1 inline-block">Open local verification link</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
