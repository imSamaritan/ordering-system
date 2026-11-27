<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-8" x-data="storefrontPage()">
    <!-- Hero & Search Bar -->
    <div class="rounded-2xl bg-gradient-to-br from-emerald-900 via-emerald-800 to-emerald-950 dark:from-emerald-950 dark:via-emerald-900/90 dark:to-stone-950 dark:border dark:border-emerald-800/50 text-white p-5 sm:p-8 shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 sm:gap-6">
            <div>
                <span class="inline-flex items-center rounded-full bg-emerald-700/70 dark:bg-emerald-800/70 px-3 py-1 text-xs font-semibold text-emerald-100">
                    Fresh Wholesale &amp; Retail Produce
                </span>
            </div>

            <div class="w-full lg:w-96">
                <label for="catalog-search" class="sr-only">Search produce</label>
                <div class="relative">
                    <svg class="w-4 h-4 text-emerald-200 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 105.5 5.5a7.5 7.5 0 0011.15 11.15z" />
                    </svg>
                    <input
                        id="catalog-search"
                        type="search"
                        x-model="search"
                        placeholder="Search apples, herbs, SKU..."
                        class="w-full rounded-xl bg-white/10 border border-white/20 pl-10 pr-4 py-2.5 text-base sm:text-sm text-white placeholder:text-emerald-200/80 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:bg-white/15"
                    >
                </div>
            </div>
        </div>
    </div>

    <!-- Order Confirmation Banner -->
    <template x-if="lastOrder">
        <div class="mt-5 sm:mt-6 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/80 p-4 sm:p-5 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Order Confirmed</p>
                <h2 class="text-base sm:text-lg font-bold text-emerald-950 dark:text-emerald-100 mt-0.5 break-words">
                    Order <span class="font-mono" x-text="lastOrder.order_number"></span> placed successfully
                </h2>
                <p class="text-sm text-emerald-800 dark:text-emerald-200 mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span>Total: <strong x-text="formatPrice(lastOrder.total_amount)"></strong></span>
                    <span aria-hidden="true">&middot;</span>
                    <span>Status: <span class="uppercase font-semibold text-xs px-2 py-0.5 rounded bg-emerald-200/70 dark:bg-emerald-900/80 text-emerald-900 dark:text-emerald-200" x-text="lastOrder.status"></span></span>
                </p>
            </div>
            <button
                type="button"
                @click="dismissConfirmation()"
                class="self-start sm:self-center shrink-0 text-xs font-semibold text-emerald-800 dark:text-emerald-200 hover:text-emerald-950 dark:hover:text-white px-3.5 py-2 rounded-lg border border-emerald-300 dark:border-emerald-700 bg-white dark:bg-emerald-900/50 cursor-pointer"
            >
                Dismiss
            </button>
        </div>
    </template>

    <!-- Category Filter Tabs + Cart Summary Strip -->
    <div class="mt-5 sm:mt-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3 sm:gap-4">
        <div
            class="flex items-center gap-2 overflow-x-auto pb-1 -mx-4 px-4 sm:mx-0 sm:px-0 sm:pb-0 sm:flex-wrap no-scrollbar"
            role="tablist"
            aria-label="Produce categories"
        >
            <button
                type="button"
                @click="selectCategory('all')"
                :class="selectedCategory === 'all'
                    ? 'bg-emerald-900 text-white border-emerald-900 dark:bg-emerald-600 dark:border-emerald-500 shadow-xs'
                    : 'bg-white text-stone-700 border-stone-200 hover:border-emerald-400 hover:text-emerald-900 dark:bg-stone-900 dark:text-stone-300 dark:border-stone-800 dark:hover:border-emerald-600 dark:hover:text-emerald-300'"
                class="shrink-0 whitespace-nowrap px-3.5 py-2 sm:py-1.5 rounded-full text-xs font-semibold border transition-colors cursor-pointer"
            >
                All Produce (<span x-text="totalActiveCount"></span>)
            </button>

            <template x-for="cat in categories" :key="cat.id">
                <button
                    type="button"
                    @click="selectCategory(cat.slug)"
                    :class="selectedCategory === cat.slug
                        ? 'bg-emerald-900 text-white border-emerald-900 dark:bg-emerald-600 dark:border-emerald-500 shadow-xs'
                        : 'bg-white text-stone-700 border-stone-200 hover:border-emerald-400 hover:text-emerald-900 dark:bg-stone-900 dark:text-stone-300 dark:border-stone-800 dark:hover:border-emerald-600 dark:hover:text-emerald-300'"
                    class="shrink-0 whitespace-nowrap px-3.5 py-2 sm:py-1.5 rounded-full text-xs font-semibold border transition-colors cursor-pointer"
                >
                    <span x-text="cat.name"></span>
                    <span class="opacity-75" x-text="' (' + (cat.product_count ?? 0) + ')'"></span>
                </button>
            </template>
        </div>

        <div class="flex items-center justify-between md:justify-end gap-3 text-xs text-stone-600 dark:text-stone-400 shrink-0">
            <span>
                Showing <strong class="text-stone-900 dark:text-stone-100" x-text="filteredProducts.length"></strong> items
            </span>
            <button
                type="button"
                x-show="!$store.auth.user || $store.auth.user.role !== 'supplier'"
                @click="$store.cart.open()"
                class="inline-flex items-center gap-2 rounded-lg bg-emerald-700 hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500 text-white px-3.5 py-2 text-xs font-semibold transition-colors cursor-pointer"
            >
                <span>View Cart</span>
                <span class="font-mono" x-text="formatPrice($store.cart.subtotal)"></span>
            </button>
        </div>
    </div>

    <!-- Error / Loading States -->
    <template x-if="loadError">
        <div class="mt-6 rounded-xl bg-red-50 dark:bg-red-950/60 border border-red-200 dark:border-red-900/80 p-4 text-sm text-red-800 dark:text-red-300 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <span x-text="loadError"></span>
            <button type="button" @click="loadCatalog()" class="font-semibold underline cursor-pointer shrink-0">Retry</button>
        </div>
    </template>

    <template x-if="loading">
        <div class="mt-5 sm:mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-5">
            <template x-for="n in 8" :key="n">
                <div class="rounded-2xl bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-4 animate-pulse">
                    <div class="aspect-[4/3] rounded-xl bg-stone-200 dark:bg-stone-800"></div>
                    <div class="mt-3 h-4 bg-stone-200 dark:bg-stone-800 rounded w-3/4"></div>
                    <div class="mt-2 h-3 bg-stone-100 dark:bg-stone-800/70 rounded w-1/2"></div>
                    <div class="mt-4 h-10 bg-stone-200 dark:bg-stone-800 rounded-lg"></div>
                </div>
            </template>
        </div>
    </template>

    <!-- Empty Search State -->
    <template x-if="!loading && !loadError && filteredProducts.length === 0">
        <div class="mt-6 sm:mt-8 rounded-2xl bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 p-8 sm:p-10 text-center">
            <p class="text-stone-700 dark:text-stone-300 font-semibold">No produce items match your filter.</p>
            <button
                type="button"
                @click="search = ''; selectedCategory = 'all'"
                class="mt-3 inline-flex items-center rounded-lg bg-emerald-700 hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500 text-white px-4 py-2.5 text-xs font-semibold cursor-pointer"
            >
                Reset Filters
            </button>
        </div>
    </template>

    <!-- Product Grid -->
    <div x-show="!loading && filteredProducts.length > 0" class="mt-5 sm:mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-5">
        <template x-for="product in filteredProducts" :key="product.id">
            <article class="rounded-2xl bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 hover:border-emerald-300 dark:hover:border-emerald-700 shadow-xs hover:shadow-md transition-all flex flex-col overflow-hidden">
                <!-- Product Image with Graceful Placeholder -->
                <div class="relative aspect-[4/3] bg-stone-100 dark:bg-stone-800/80 overflow-hidden flex items-center justify-center">
                    <template x-if="product.image_path && !brokenImages[product.id]">
                        <img
                            :src="imageUrl(product)"
                            :alt="product.description"
                            loading="lazy"
                            @error="markImageBroken(product.id)"
                            class="w-full h-full object-cover"
                        >
                    </template>
                    <template x-if="!product.image_path || brokenImages[product.id]">
                        <div class="flex flex-col items-center justify-center text-stone-400 dark:text-stone-500 p-4 text-center">
                            <svg class="w-10 h-10 mb-1.5 text-emerald-700/40 dark:text-emerald-400/40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c-4.5 2-7 6.5-7 11a7 7 0 0014 0c0-4.5-2.5-9-7-11z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18" />
                            </svg>
                            <span class="text-[11px] font-medium text-stone-500 dark:text-stone-400" x-text="product.category_name"></span>
                        </div>
                    </template>

                    <span
                        class="absolute top-2.5 left-2.5 rounded-full bg-white/90 dark:bg-stone-900/90 backdrop-blur-xs px-2.5 py-0.5 text-[11px] font-semibold text-emerald-900 dark:text-emerald-300 border border-stone-200/80 dark:border-stone-700/80"
                        x-text="product.category_name"
                    ></span>
                    <span
                        x-show="product.stock_quantity <= 0"
                        class="absolute top-2.5 right-2.5 rounded-full bg-red-600 text-white px-2.5 py-0.5 text-[11px] font-semibold"
                    >
                        Out of Stock
                    </span>
                </div>

                <!-- Card Body -->
                <div class="p-4 flex-1 flex flex-col justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-mono text-stone-400 dark:text-stone-500" x-text="product.sku"></p>
                        <h3 class="mt-0.5 font-semibold text-stone-900 dark:text-stone-100 leading-snug" x-text="product.description"></h3>
                        <div class="mt-2 flex flex-wrap items-baseline justify-between gap-x-2 gap-y-1">
                            <p class="text-lg font-bold text-emerald-900 dark:text-emerald-400">
                                <span x-text="formatPrice(product.unit_price)"></span>
                                <span class="text-xs font-normal text-stone-500 dark:text-stone-400" x-text="'/ ' + product.unit_name"></span>
                            </p>
                            <span class="text-[11px] text-stone-500 dark:text-stone-400" x-text="product.allow_decimal ? 'Step: 0.1 kg' : 'Step: 1 ' + product.unit_name"></span>
                        </div>
                    </div>

                    <!-- Quantity / Add to Cart Controls -->
                    <div class="pt-2 border-t border-stone-100 dark:border-stone-800">
                        <template x-if="$store.cart.quantityOf(product.id) === 0">
                            <button
                                type="button"
                                :disabled="product.stock_quantity <= 0"
                                @click="addOrIncrement(product)"
                                class="w-full min-h-10 sm:min-h-9 rounded-xl bg-emerald-700 hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500 disabled:bg-stone-300 dark:disabled:bg-stone-700 dark:disabled:text-stone-400 disabled:cursor-not-allowed text-white py-2 px-3 text-xs font-semibold transition-colors cursor-pointer"
                            >
                                <span x-text="product.stock_quantity <= 0 ? 'Out of Stock' : (product.allow_decimal ? 'Add 0.1 kg' : 'Add 1 ' + product.unit_name)"></span>
                            </button>
                        </template>

                        <template x-if="$store.cart.quantityOf(product.id) > 0">
                            <div class="flex items-center justify-between gap-2 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/80 rounded-xl p-1">
                                <button
                                    type="button"
                                    @click="decrementProduct(product.id)"
                                    class="w-10 h-10 sm:w-8 sm:h-8 rounded-lg bg-white dark:bg-stone-900 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 text-emerald-900 dark:text-emerald-200 font-bold text-base sm:text-sm border border-emerald-200 dark:border-emerald-800 transition-colors cursor-pointer"
                                    aria-label="Decrease quantity"
                                >
                                    &minus;
                                </button>
                                <span class="text-xs font-bold text-emerald-950 dark:text-emerald-100 font-mono">
                                    <span x-text="$store.cart.formatQuantity($store.cart.quantityOf(product.id), Boolean(product.allow_decimal))"></span>
                                    <span class="font-sans font-medium text-emerald-700 dark:text-emerald-300" x-text="' ' + product.unit_name"></span>
                                </span>
                                <button
                                    type="button"
                                    @click="addOrIncrement(product)"
                                    class="w-10 h-10 sm:w-8 sm:h-8 rounded-lg bg-emerald-700 hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500 text-white font-bold text-base sm:text-sm transition-colors cursor-pointer"
                                    aria-label="Increase quantity"
                                >
                                    +
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </article>
        </template>
    </div>
</section>
