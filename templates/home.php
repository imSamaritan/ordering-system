<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-8" x-data="storefrontPage()">
    <!-- Hero & Search Bar -->
    <div class="rounded-2xl bg-gradient-to-br from-emerald-900 via-emerald-800 to-emerald-950 text-white p-5 sm:p-8 shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5 sm:gap-6">
            <div>
                <span class="inline-flex items-center rounded-full bg-emerald-700/70 px-3 py-1 text-xs font-semibold text-emerald-100">
                    Fresh Wholesale &amp; Retail Produce
                </span>
                <h1 class="mt-3 text-xl sm:text-3xl font-bold tracking-tight">
                    Fresh Produce Storefront
                </h1>
                <p class="mt-1.5 text-sm text-emerald-100/90 max-w-2xl">
                    Order fruit, vegetables, herbs, mushrooms, and eggs with precision <strong class="text-white">0.1 kg</strong> decimal increments or whole <strong class="text-white">Unit / Punnet</strong> quantities.
                </p>
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
        <div class="mt-5 sm:mt-6 rounded-2xl bg-emerald-50 border border-emerald-200 p-4 sm:p-5 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Order Confirmed</p>
                <h2 class="text-base sm:text-lg font-bold text-emerald-950 mt-0.5 break-words">
                    Order <span class="font-mono" x-text="lastOrder.order_number"></span> placed successfully
                </h2>
                <p class="text-sm text-emerald-800 mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span>Total: <strong x-text="formatPrice(lastOrder.total_amount)"></strong></span>
                    <span aria-hidden="true">&middot;</span>
                    <span>Status: <span class="uppercase font-semibold text-xs px-2 py-0.5 rounded bg-emerald-200/70 text-emerald-900" x-text="lastOrder.status"></span></span>
                </p>
            </div>
            <button
                type="button"
                @click="dismissConfirmation()"
                class="self-start sm:self-center shrink-0 text-xs font-semibold text-emerald-800 hover:text-emerald-950 px-3.5 py-2 rounded-lg border border-emerald-300 bg-white cursor-pointer"
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
                    ? 'bg-emerald-900 text-white border-emerald-900 shadow-xs'
                    : 'bg-white text-stone-700 border-stone-200 hover:border-emerald-400 hover:text-emerald-900'"
                class="shrink-0 whitespace-nowrap px-3.5 py-2 sm:py-1.5 rounded-full text-xs font-semibold border transition-colors cursor-pointer"
            >
                All Produce (<span x-text="totalActiveCount"></span>)
            </button>

            <template x-for="cat in categories" :key="cat.id">
                <button
                    type="button"
                    @click="selectCategory(cat.slug)"
                    :class="selectedCategory === cat.slug
                        ? 'bg-emerald-900 text-white border-emerald-900 shadow-xs'
                        : 'bg-white text-stone-700 border-stone-200 hover:border-emerald-400 hover:text-emerald-900'"
                    class="shrink-0 whitespace-nowrap px-3.5 py-2 sm:py-1.5 rounded-full text-xs font-semibold border transition-colors cursor-pointer"
                >
                    <span x-text="cat.name"></span>
                    <span class="opacity-75" x-text="' (' + (cat.product_count ?? 0) + ')'"></span>
                </button>
            </template>
        </div>

        <div class="flex items-center justify-between md:justify-end gap-3 text-xs text-stone-600 shrink-0">
            <span>
                Showing <strong class="text-stone-900" x-text="filteredProducts.length"></strong> items
            </span>
            <button
                type="button"
                @click="$store.cart.open()"
                class="inline-flex items-center gap-2 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white px-3.5 py-2 text-xs font-semibold transition-colors cursor-pointer"
            >
                <span>View Cart</span>
                <span class="font-mono" x-text="formatPrice($store.cart.subtotal)"></span>
            </button>
        </div>
    </div>

    <!-- Error / Loading States -->
    <template x-if="loadError">
        <div class="mt-6 rounded-xl bg-red-50 border border-red-200 p-4 text-sm text-red-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <span x-text="loadError"></span>
            <button type="button" @click="loadCatalog()" class="font-semibold underline cursor-pointer shrink-0">Retry</button>
        </div>
    </template>

    <template x-if="loading">
        <div class="mt-5 sm:mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-5">
            <template x-for="n in 8" :key="n">
                <div class="rounded-2xl bg-white border border-stone-200 p-4 animate-pulse">
                    <div class="aspect-[4/3] rounded-xl bg-stone-200"></div>
                    <div class="mt-3 h-4 bg-stone-200 rounded w-3/4"></div>
                    <div class="mt-2 h-3 bg-stone-100 rounded w-1/2"></div>
                    <div class="mt-4 h-10 bg-stone-200 rounded-lg"></div>
                </div>
            </template>
        </div>
    </template>

    <!-- Empty Search State -->
    <template x-if="!loading && !loadError && filteredProducts.length === 0">
        <div class="mt-6 sm:mt-8 rounded-2xl bg-white border border-stone-200 p-8 sm:p-10 text-center">
            <p class="text-stone-700 font-semibold">No produce items match your filter.</p>
            <button
                type="button"
                @click="search = ''; selectedCategory = 'all'"
                class="mt-3 inline-flex items-center rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white px-4 py-2.5 text-xs font-semibold cursor-pointer"
            >
                Reset Filters
            </button>
        </div>
    </template>

    <!-- Product Grid -->
    <div x-show="!loading && filteredProducts.length > 0" class="mt-5 sm:mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-5">
        <template x-for="product in filteredProducts" :key="product.id">
            <article class="rounded-2xl bg-white border border-stone-200 hover:border-emerald-300 shadow-xs hover:shadow-md transition-all flex flex-col overflow-hidden">
                <!-- Product Image with Graceful Placeholder -->
                <div class="relative aspect-[4/3] bg-stone-100 overflow-hidden flex items-center justify-center">
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
                        <div class="flex flex-col items-center justify-center text-stone-400 p-4 text-center">
                            <svg class="w-10 h-10 mb-1.5 text-emerald-700/40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c-4.5 2-7 6.5-7 11a7 7 0 0014 0c0-4.5-2.5-9-7-11z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18" />
                            </svg>
                            <span class="text-[11px] font-medium text-stone-500" x-text="product.category_name"></span>
                        </div>
                    </template>

                    <span
                        class="absolute top-2.5 left-2.5 rounded-full bg-white/90 backdrop-blur-xs px-2.5 py-0.5 text-[11px] font-semibold text-emerald-900 border border-stone-200/80"
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
                        <p class="text-[11px] font-mono text-stone-400" x-text="product.sku"></p>
                        <h3 class="mt-0.5 font-semibold text-stone-900 leading-snug" x-text="product.description"></h3>
                        <div class="mt-2 flex flex-wrap items-baseline justify-between gap-x-2 gap-y-1">
                            <p class="text-lg font-bold text-emerald-900">
                                <span x-text="formatPrice(product.unit_price)"></span>
                                <span class="text-xs font-normal text-stone-500" x-text="'/ ' + product.unit_name"></span>
                            </p>
                            <span class="text-[11px] text-stone-500" x-text="product.allow_decimal ? 'Step: 0.1 kg' : 'Step: 1 ' + product.unit_name"></span>
                        </div>
                    </div>

                    <!-- Quantity / Add to Cart Controls -->
                    <div class="pt-2 border-t border-stone-100">
                        <template x-if="$store.cart.quantityOf(product.id) === 0">
                            <button
                                type="button"
                                :disabled="product.stock_quantity <= 0"
                                @click="addOrIncrement(product)"
                                class="w-full min-h-10 sm:min-h-9 rounded-xl bg-emerald-700 hover:bg-emerald-800 disabled:bg-stone-300 disabled:cursor-not-allowed text-white py-2 px-3 text-xs font-semibold transition-colors cursor-pointer"
                            >
                                <span x-text="product.stock_quantity <= 0 ? 'Out of Stock' : (product.allow_decimal ? 'Add 0.1 kg' : 'Add 1 ' + product.unit_name)"></span>
                            </button>
                        </template>

                        <template x-if="$store.cart.quantityOf(product.id) > 0">
                            <div class="flex items-center justify-between gap-2 bg-emerald-50 border border-emerald-200 rounded-xl p-1">
                                <button
                                    type="button"
                                    @click="decrementProduct(product.id)"
                                    class="w-10 h-10 sm:w-8 sm:h-8 rounded-lg bg-white hover:bg-emerald-100 text-emerald-900 font-bold text-base sm:text-sm border border-emerald-200 transition-colors cursor-pointer"
                                    aria-label="Decrease quantity"
                                >
                                    &minus;
                                </button>
                                <span class="text-xs font-bold text-emerald-950 font-mono">
                                    <span x-text="$store.cart.formatQuantity($store.cart.quantityOf(product.id), Boolean(product.allow_decimal))"></span>
                                    <span class="font-sans font-medium text-emerald-700" x-text="' ' + product.unit_name"></span>
                                </span>
                                <button
                                    type="button"
                                    @click="addOrIncrement(product)"
                                    class="w-10 h-10 sm:w-8 sm:h-8 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-base sm:text-sm transition-colors cursor-pointer"
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

    <!-- Slide-Over Cart & Checkout Drawer -->
    <div
        x-show="$store.cart.isOpen"
        x-cloak
        @keydown.escape.window="$store.cart.close()"
        class="fixed inset-0 z-50 flex justify-end"
        role="dialog"
        aria-modal="true"
        aria-label="Shopping Cart"
    >
        <!-- Backdrop -->
        <div
            class="fixed inset-0 bg-stone-950/50 backdrop-blur-[1px]"
            @click="$store.cart.close()"
        ></div>

        <!-- Drawer Panel -->
        <div class="relative z-10 w-full max-w-md bg-white h-full max-h-[100dvh] shadow-2xl flex flex-col">
            <div class="px-4 sm:px-5 py-3.5 sm:py-4 border-b border-stone-200 flex items-center justify-between bg-emerald-900 text-white shrink-0">
                <div>
                    <h2 class="font-bold text-base">Your Order Cart</h2>
                    <p class="text-xs text-emerald-200" x-text="$store.cart.totalItems + ' line item(s)'"></p>
                </div>
                <button
                    type="button"
                    @click="$store.cart.close()"
                    class="w-10 h-10 inline-flex items-center justify-center rounded-lg text-xl leading-none text-emerald-100 hover:bg-emerald-800 cursor-pointer"
                    aria-label="Close cart"
                >
                    &times;
                </button>
            </div>

            <!-- Cart Items List -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-3">
                <template x-if="$store.cart.items.length === 0">
                    <div class="text-center py-12 text-stone-500 text-sm">
                        Your cart is currently empty. Add fresh produce items to begin an order.
                    </div>
                </template>

                <template x-for="item in $store.cart.items" :key="item.productId">
                    <div class="rounded-xl border border-stone-200 p-3.5 bg-stone-50/70">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-stone-900 break-words" x-text="item.description"></h3>
                                <p class="text-xs text-stone-500">
                                    <span x-text="formatPrice(item.unitPrice)"></span> / <span x-text="item.unitName"></span>
                                </p>
                            </div>
                            <button
                                type="button"
                                @click="$store.cart.remove(item.productId)"
                                class="shrink-0 text-xs text-stone-500 hover:text-red-600 px-2 py-1 rounded cursor-pointer"
                                title="Remove item"
                            >
                                Remove
                            </button>
                        </div>

                        <div class="mt-3 flex items-center justify-between gap-2">
                            <div class="inline-flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="$store.cart.decrement(item.productId)"
                                    class="w-9 h-9 sm:w-7 sm:h-7 rounded-md bg-white border border-stone-300 hover:bg-stone-100 text-stone-800 font-bold text-sm sm:text-xs cursor-pointer"
                                    aria-label="Decrease cart item quantity"
                                >
                                    &minus;
                                </button>
                                <span class="text-xs font-mono font-semibold text-stone-900 min-w-16 text-center">
                                    <span x-text="$store.cart.formatQuantity(item.quantityTenths / 10, item.allowDecimal)"></span>
                                    <span x-text="' ' + item.unitName"></span>
                                </span>
                                <button
                                    type="button"
                                    @click="incrementCartItem(item.productId)"
                                    class="w-9 h-9 sm:w-7 sm:h-7 rounded-md bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-sm sm:text-xs cursor-pointer"
                                    aria-label="Increase cart item quantity"
                                >
                                    +
                                </button>
                            </div>
                            <span class="text-sm font-bold text-emerald-900 font-mono" x-text="formatPrice($store.cart.lineTotal(item))"></span>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Checkout Footer -->
            <div class="border-t border-stone-200 p-4 sm:p-5 pb-[max(1rem,env(safe-area-inset-bottom))] bg-stone-50 space-y-3 shrink-0">
                <div class="flex items-center justify-between text-base font-bold text-stone-900">
                    <span>Order Subtotal</span>
                    <span class="font-mono text-emerald-900" x-text="formatPrice($store.cart.subtotal)"></span>
                </div>

                <div x-show="$store.cart.items.length > 0">
                    <label for="order-notes" class="block text-xs font-medium text-stone-700 mb-1">Delivery / Packing Notes (optional)</label>
                    <textarea
                        id="order-notes"
                        rows="2"
                        maxlength="1000"
                        x-model="orderNotes"
                        placeholder="e.g. Deliver before 09:00 at receiving gate"
                        class="w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-base sm:text-xs text-stone-900 focus:outline-none focus:ring-2 focus:ring-emerald-600"
                    ></textarea>
                </div>

                <template x-if="checkoutError">
                    <p class="text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2" x-text="checkoutError"></p>
                </template>

                <template x-if="!$store.auth.user && $store.cart.items.length > 0">
                    <a
                        :href="loginUrl()"
                        class="block w-full text-center rounded-xl bg-amber-500 hover:bg-amber-600 text-stone-950 font-semibold py-3 sm:py-2.5 text-sm transition-colors"
                    >
                        Sign In to Place Order
                    </a>
                </template>

                <template x-if="$store.auth.user && $store.cart.items.length > 0">
                    <button
                        type="button"
                        :disabled="submittingOrder"
                        @click="submitOrder()"
                        class="w-full rounded-xl bg-emerald-700 hover:bg-emerald-800 disabled:opacity-60 text-white font-semibold py-3 sm:py-2.5 text-sm transition-colors cursor-pointer"
                    >
                        <span x-text="submittingOrder ? 'Placing Order...' : 'Place Order'"></span>
                    </button>
                </template>

                <button
                    type="button"
                    x-show="$store.cart.items.length > 0"
                    @click="$store.cart.clear()"
                    class="w-full text-xs text-stone-500 hover:text-stone-800 py-1.5 cursor-pointer"
                >
                    Clear Cart
                </button>
            </div>
        </div>
    </div>
</section>
