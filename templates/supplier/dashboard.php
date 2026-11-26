<?php
/**
 * @var string $basePath
 * @var string|null $initialTab
 */
$tab = ($initialTab ?? 'companies') === 'inventory' ? 'inventory' : 'companies';
?>
<div x-data="supplierDashboardPage('<?= $tab ?>')" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <!-- Page Header & Tab Switcher -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-stone-900 tracking-tight">Supplier Operations Portal</h1>
            <p class="text-sm text-stone-600 mt-1">
                Manage buyer company accounts, process order lifecycle statuses, and maintain your fresh produce catalog &amp; ZAR pricing.
            </p>
        </div>

        <div class="inline-flex rounded-xl border border-stone-200 bg-white p-1 shadow-xs self-start">
            <button
                type="button"
                @click="switchTab('companies')"
                class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors cursor-pointer"
                :class="activeTab === 'companies' ? 'bg-emerald-800 text-white shadow-xs' : 'text-stone-600 hover:text-stone-900'"
            >
                Companies &amp; Orders
            </button>
            <button
                type="button"
                @click="switchTab('inventory')"
                class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors cursor-pointer"
                :class="activeTab === 'inventory' ? 'bg-emerald-800 text-white shadow-xs' : 'text-stone-600 hover:text-stone-900'"
            >
                Inventory &amp; Pricing
            </button>
        </div>
    </div>

    <!-- ===================== TAB 1: COMPANIES & ORDERS ===================== -->
    <div x-show="activeTab === 'companies'" class="space-y-6">
        <!-- Supplier KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-stone-500">Buyer Accounts</div>
                <div class="mt-2 text-2xl font-bold text-stone-900" x-text="companiesSummary.total_companies"></div>
                <div class="mt-1 text-xs text-stone-500">Registered customers &amp; companies</div>
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-5 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-amber-800">Pending Review</div>
                <div class="mt-2 text-2xl font-bold text-amber-950" x-text="companiesSummary.pending_orders"></div>
                <div class="mt-1 text-xs text-amber-800">
                    In pipeline: <span x-text="companiesSummary.in_progress_orders"></span>
                </div>
            </div>

            <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-stone-500">Total Orders</div>
                <div class="mt-2 text-2xl font-bold text-stone-900" x-text="companiesSummary.total_orders"></div>
                <div class="mt-1 text-xs text-stone-500">
                    <span x-text="companiesSummary.delivered_orders"></span> delivered
                </div>
            </div>

            <div class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-5 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-emerald-800">Active Revenue</div>
                <div class="mt-2 text-2xl font-bold text-emerald-950" x-text="formatPrice(companiesSummary.total_revenue)"></div>
                <div class="mt-1 text-xs text-emerald-700">Excluding declined orders</div>
            </div>
        </div>

        <!-- Company Search Bar -->
        <form @submit.prevent="loadCompanies()" class="flex flex-col sm:flex-row gap-2.5">
            <input
                type="text"
                x-model="companySearch"
                placeholder="Search companies by name, contact, email, phone, or location..."
                class="flex-1 rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-sm text-stone-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none"
            >
            <button
                type="submit"
                class="rounded-xl bg-stone-900 hover:bg-stone-800 text-white px-5 py-2.5 text-sm font-semibold transition-colors cursor-pointer"
            >
                Search Accounts
            </button>
            <button
                type="button"
                x-show="companySearch !== ''"
                @click="companySearch = ''; loadCompanies()"
                class="rounded-xl border border-stone-300 bg-white hover:bg-stone-50 text-stone-700 px-4 py-2.5 text-sm font-medium transition-colors cursor-pointer"
            >
                Clear
            </button>
        </form>

        <template x-if="companiesError !== ''">
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="companiesError"></div>
        </template>

        <template x-if="companiesLoading">
            <div class="rounded-xl border border-stone-200 bg-white p-10 text-center text-sm text-stone-500">
                Loading company accounts...
            </div>
        </template>

        <template x-if="!companiesLoading && companies.length === 0 && companiesError === ''">
            <div class="rounded-xl border border-stone-200 bg-white p-10 text-center text-sm text-stone-500">
                No customer or company accounts matched your search.
            </div>
        </template>

        <!-- Company Cards Grid -->
        <div x-show="!companiesLoading && companies.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <template x-for="company in companies" :key="company.id">
                <div
                    class="rounded-xl border bg-white p-5 shadow-xs flex flex-col justify-between gap-4 transition-all"
                    :class="selectedCompany && selectedCompany.id === company.id ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-stone-200 hover:border-stone-300'"
                >
                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h2 class="text-base font-bold text-stone-900 leading-snug" x-text="company.display_name"></h2>
                                <p class="text-xs text-stone-500 mt-0.5" x-show="company.company_name" x-text="'Contact: ' + company.contact_name"></p>
                            </div>
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold uppercase tracking-wide border"
                                :class="company.role === 'company' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-stone-100 text-stone-700 border-stone-200'"
                                x-text="company.role"
                            ></span>
                        </div>

                        <dl class="space-y-1.5 text-xs text-stone-600">
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-stone-400">Email</dt>
                                <dd class="font-medium text-stone-800 truncate" x-text="company.email"></dd>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-stone-400">Phone</dt>
                                <dd class="font-medium text-stone-800" x-text="company.phone || 'Not provided'"></dd>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-stone-400">Location</dt>
                                <dd class="font-medium text-stone-800 truncate" x-text="company.location || 'Not provided'"></dd>
                            </div>
                        </dl>

                        <div class="grid grid-cols-3 gap-2 pt-2 border-t border-stone-100 text-center">
                            <div class="rounded-lg bg-stone-50 py-1.5 px-2">
                                <div class="text-sm font-bold text-stone-900" x-text="company.total_orders"></div>
                                <div class="text-[11px] text-stone-500">Orders</div>
                            </div>
                            <div class="rounded-lg bg-amber-50 py-1.5 px-2">
                                <div class="text-sm font-bold text-amber-800" x-text="company.pending_orders"></div>
                                <div class="text-[11px] text-amber-700">Pending</div>
                            </div>
                            <div class="rounded-lg bg-emerald-50 py-1.5 px-2">
                                <div class="text-sm font-bold text-emerald-800" x-text="formatPrice(company.total_spend)"></div>
                                <div class="text-[11px] text-emerald-700">Spend</div>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="openCompanyDetails(company)"
                        class="w-full rounded-lg bg-emerald-800 hover:bg-emerald-700 text-white px-4 py-2 text-xs font-semibold transition-colors cursor-pointer"
                    >
                        View More Details
                    </button>
                </div>
            </template>
        </div>

        <!-- Selected Company Orders Panel -->
        <template x-if="selectedCompany">
            <div class="rounded-2xl border border-emerald-200 bg-white shadow-sm overflow-hidden">
                <div class="px-5 py-4 bg-emerald-900 text-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold" x-text="selectedCompany.display_name + ' — Order History'"></h3>
                        <p class="text-xs text-emerald-100 mt-0.5">
                            <span x-text="selectedCompany.email"></span>
                            <span x-show="selectedCompany.phone" x-text="' · ' + selectedCompany.phone"></span>
                            <span x-show="selectedCompany.location" x-text="' · ' + selectedCompany.location"></span>
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="closeCompanyDetails()"
                        class="self-start sm:self-auto rounded-lg bg-emerald-800 hover:bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white cursor-pointer"
                    >
                        Close Panel
                    </button>
                </div>

                <!-- Company Orders Filter Bar -->
                <div class="px-5 py-3.5 border-b border-stone-200 bg-stone-50 flex flex-wrap items-center gap-3">
                    <select
                        x-model="companyOrderStatusFilter"
                        @change="loadCompanyOrders()"
                        class="rounded-lg border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-stone-800 outline-none focus:border-emerald-500"
                    >
                        <option value="all">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="received">Received</option>
                        <option value="in_progress">In Progress</option>
                        <option value="delivered">Delivered</option>
                        <option value="declined">Declined</option>
                    </select>

                    <form @submit.prevent="loadCompanyOrders()" class="flex items-center gap-2 flex-1 min-w-[200px]">
                        <input
                            type="text"
                            x-model="companyOrderNumberFilter"
                            placeholder="Filter by order #..."
                            class="flex-1 rounded-lg border border-stone-300 bg-white px-3 py-1.5 text-xs text-stone-800 outline-none focus:border-emerald-500"
                        >
                        <button
                            type="submit"
                            class="rounded-lg bg-stone-900 hover:bg-stone-800 text-white px-3 py-1.5 text-xs font-semibold cursor-pointer"
                        >
                            Apply
                        </button>
                    </form>
                </div>

                <template x-if="companyOrdersError !== ''">
                    <div class="p-4 text-sm text-red-700 bg-red-50" x-text="companyOrdersError"></div>
                </template>

                <template x-if="companyOrdersLoading">
                    <div class="p-8 text-center text-sm text-stone-500">Loading company orders...</div>
                </template>

                <template x-if="!companyOrdersLoading && companyOrders.length === 0 && companyOrdersError === ''">
                    <div class="p-8 text-center text-sm text-stone-500">
                        No orders found for this account with the selected filter.
                    </div>
                </template>

                <template x-if="!companyOrdersLoading && companyOrders.length > 0">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-stone-200 text-sm">
                            <thead class="bg-stone-50 text-xs uppercase tracking-wider text-stone-500">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold">Order #</th>
                                    <th class="px-4 py-3 text-left font-semibold">Ordered Date</th>
                                    <th class="px-4 py-3 text-left font-semibold">Status</th>
                                    <th class="px-4 py-3 text-right font-semibold">Items</th>
                                    <th class="px-4 py-3 text-right font-semibold">Total</th>
                                    <th class="px-4 py-3 text-right font-semibold">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                <template x-for="order in companyOrders" :key="order.id">
                                    <tr class="hover:bg-stone-50/80">
                                        <td class="px-4 py-3 font-mono font-semibold text-stone-900" x-text="order.order_number"></td>
                                        <td class="px-4 py-3 text-stone-600" x-text="order.ordered_at"></td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border"
                                                :class="statusBadgeClass(order.status)"
                                                x-text="statusLabel(order.status)"
                                            ></span>
                                        </td>
                                        <td class="px-4 py-3 text-right text-stone-600" x-text="order.item_count ?? 0"></td>
                                        <td class="px-4 py-3 text-right font-semibold text-stone-900" x-text="formatPrice(order.total_amount)"></td>
                                        <td class="px-4 py-3 text-right">
                                            <button
                                                type="button"
                                                @click="openOrderModal(order.id)"
                                                class="rounded-lg border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-900 px-3 py-1 text-xs font-semibold transition-colors cursor-pointer"
                                            >
                                                Manage Order
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </template>
    </div>

    <!-- ===================== TAB 2: INVENTORY & PRICING ===================== -->
    <div x-show="activeTab === 'inventory'" x-cloak class="space-y-6">
        <!-- Inventory Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-stone-500">Catalog Items</div>
                <div class="mt-2 text-2xl font-bold text-stone-900" x-text="inventorySummary.total"></div>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-5 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-emerald-800">Active on Storefront</div>
                <div class="mt-2 text-2xl font-bold text-emerald-950" x-text="inventorySummary.active"></div>
            </div>
            <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-stone-500">Inactive / Hidden</div>
                <div class="mt-2 text-2xl font-bold text-stone-700" x-text="inventorySummary.inactive"></div>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-5 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-amber-800">Low Stock (&le; 10)</div>
                <div class="mt-2 text-2xl font-bold text-amber-950" x-text="inventorySummary.low_stock"></div>
            </div>
        </div>

        <!-- Inventory Filters & Add Button -->
        <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-xs flex flex-col lg:flex-row gap-3 lg:items-center justify-between">
            <form @submit.prevent="loadInventory()" class="flex flex-wrap items-center gap-2.5 flex-1">
                <input
                    type="text"
                    x-model="productSearch"
                    placeholder="Search description, SKU, or category..."
                    class="flex-1 min-w-[200px] rounded-lg border border-stone-300 px-3 py-2 text-sm text-stone-900 outline-none focus:border-emerald-500"
                >

                <select
                    x-model="productCategoryFilter"
                    @change="loadInventory()"
                    class="rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-stone-800 outline-none focus:border-emerald-500"
                >
                    <option value="all">All Categories</option>
                    <template x-for="cat in categories" :key="cat.id">
                        <option :value="cat.slug" x-text="cat.name"></option>
                    </template>
                </select>

                <select
                    x-model="productStatusFilter"
                    @change="loadInventory()"
                    class="rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-stone-800 outline-none focus:border-emerald-500"
                >
                    <option value="all">All Visibility</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Inactive Only</option>
                </select>

                <button
                    type="submit"
                    class="rounded-lg bg-stone-900 hover:bg-stone-800 text-white px-4 py-2 text-sm font-semibold cursor-pointer"
                >
                    Filter
                </button>
            </form>

            <button
                type="button"
                @click="openCreateProductModal()"
                class="rounded-lg bg-emerald-700 hover:bg-emerald-600 text-white px-4 py-2 text-sm font-semibold shadow-xs transition-colors cursor-pointer shrink-0"
            >
                + Add Product
            </button>
        </div>

        <template x-if="inventoryError !== ''">
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="inventoryError"></div>
        </template>

        <!-- Inventory Table -->
        <div class="rounded-xl border border-stone-200 bg-white shadow-xs overflow-hidden">
            <template x-if="inventoryLoading">
                <div class="p-10 text-center text-sm text-stone-500">Loading product catalog...</div>
            </template>

            <template x-if="!inventoryLoading && products.length === 0">
                <div class="p-10 text-center text-sm text-stone-500">No products match the selected filters.</div>
            </template>

            <template x-if="!inventoryLoading && products.length > 0">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead class="bg-stone-50 text-xs uppercase tracking-wider text-stone-500">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Product</th>
                                <th class="px-4 py-3 text-left font-semibold">Category</th>
                                <th class="px-4 py-3 text-left font-semibold">Unit</th>
                                <th class="px-4 py-3 text-right font-semibold">Price (ZAR)</th>
                                <th class="px-4 py-3 text-right font-semibold">Stock</th>
                                <th class="px-4 py-3 text-center font-semibold">Visibility</th>
                                <th class="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            <template x-for="product in products" :key="product.id">
                                <tr class="hover:bg-stone-50/70" :class="!product.is_active ? 'opacity-65 bg-stone-50/40' : ''">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-lg bg-stone-100 border border-stone-200 overflow-hidden shrink-0 flex items-center justify-center">
                                                <template x-if="product.image_path">
                                                    <img :src="productImageUrl(product.image_path)" :alt="product.description" class="w-full h-full object-cover">
                                                </template>
                                                <template x-if="!product.image_path">
                                                    <span class="text-[10px] font-semibold text-stone-400">IMG</span>
                                                </template>
                                            </div>
                                            <div>
                                                <div class="font-semibold text-stone-900" x-text="product.description"></div>
                                                <div class="text-xs font-mono text-stone-500" x-text="product.sku"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-stone-600" x-text="product.category_name"></td>
                                    <td class="px-4 py-3 text-stone-600" x-text="product.unit_name"></td>
                                    <td class="px-4 py-3 text-right font-semibold text-stone-900" x-text="formatPrice(product.unit_price)"></td>
                                    <td class="px-4 py-3 text-right">
                                        <span
                                            class="font-semibold"
                                            :class="product.stock_quantity <= 10 ? 'text-amber-700' : 'text-stone-800'"
                                            x-text="product.stock_quantity"
                                        ></span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button
                                            type="button"
                                            @click="toggleProductActive(product)"
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border cursor-pointer transition-colors"
                                            :class="product.is_active ? 'bg-emerald-100 text-emerald-800 border-emerald-200 hover:bg-emerald-200' : 'bg-stone-100 text-stone-600 border-stone-300 hover:bg-stone-200'"
                                            x-text="product.is_active ? 'Active' : 'Inactive'"
                                        ></button>
                                    </td>
                                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                        <button
                                            type="button"
                                            @click="openEditProductModal(product)"
                                            class="text-emerald-700 hover:text-emerald-900 text-xs font-semibold cursor-pointer"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            :disabled="deletingProductId === product.id"
                                            @click="deleteProduct(product)"
                                            class="text-red-600 hover:text-red-800 text-xs font-semibold cursor-pointer disabled:opacity-50"
                                        >
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </div>

    <!-- ===================== MODAL 1: ORDER DETAIL & STATUS TRANSITION ===================== -->
    <div
        x-show="orderModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-stone-900/60"
        @keydown.escape.window="closeOrderModal()"
    >
        <div
            @click.away="closeOrderModal()"
            class="bg-white rounded-2xl shadow-xl border border-stone-200 max-w-2xl w-full max-h-[90vh] flex flex-col overflow-hidden"
        >
            <div class="px-6 py-4 border-b border-stone-200 flex items-center justify-between bg-stone-50">
                <div>
                    <h3 class="text-base font-bold text-stone-900" x-text="activeOrder ? activeOrder.order_number : 'Order Management'"></h3>
                    <p class="text-xs text-stone-500" x-show="activeOrder" x-text="activeOrder ? 'Ordered: ' + activeOrder.ordered_at : ''"></p>
                </div>
                <button
                    type="button"
                    @click="closeOrderModal()"
                    class="text-stone-400 hover:text-stone-700 text-xl leading-none px-2 cursor-pointer"
                    aria-label="Close modal"
                >&times;</button>
            </div>

            <div class="p-6 overflow-y-auto space-y-5 flex-1">
                <template x-if="orderModalLoading">
                    <div class="py-10 text-center text-sm text-stone-500">Loading order details...</div>
                </template>

                <template x-if="!orderModalLoading && orderModalError !== ''">
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="orderModalError"></div>
                </template>

                <template x-if="!orderModalLoading && activeOrder">
                    <div class="space-y-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <span
                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border"
                                :class="statusBadgeClass(activeOrder.status)"
                                x-text="statusLabel(activeOrder.status)"
                            ></span>
                            <div class="text-lg font-bold text-emerald-900" x-text="formatPrice(activeOrder.total_amount)"></div>
                        </div>

                        <template x-if="activeOrder.customer">
                            <div class="rounded-lg bg-stone-50 border border-stone-200 px-3.5 py-2.5 text-xs text-stone-700 space-y-0.5">
                                <div class="font-semibold text-stone-900" x-text="activeOrder.customer.company_name || activeOrder.customer.contact_name"></div>
                                <div x-text="activeOrder.customer.contact_name + ' · ' + activeOrder.customer.email"></div>
                                <div x-show="activeOrder.customer.phone || activeOrder.customer.location" x-text="[activeOrder.customer.phone, activeOrder.customer.location].filter(Boolean).join(' · ')"></div>
                            </div>
                        </template>

                        <template x-if="activeOrder.notes">
                            <div class="rounded-lg bg-amber-50/70 border border-amber-200 px-3.5 py-2.5 text-xs text-amber-950">
                                <span class="font-semibold">Customer Notes:</span>
                                <span x-text="activeOrder.notes"></span>
                            </div>
                        </template>

                        <!-- Line Items -->
                        <div>
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-stone-500 mb-2">Line Items</h4>
                            <div class="border border-stone-200 rounded-lg overflow-hidden">
                                <table class="min-w-full divide-y divide-stone-200 text-xs">
                                    <thead class="bg-stone-50 text-stone-500">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-semibold">Product</th>
                                            <th class="px-3 py-2 text-right font-semibold">Qty</th>
                                            <th class="px-3 py-2 text-right font-semibold">Unit Price</th>
                                            <th class="px-3 py-2 text-right font-semibold">Line Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-stone-100">
                                        <template x-for="item in (activeOrder.items ?? [])" :key="item.id">
                                            <tr>
                                                <td class="px-3 py-2 text-stone-900 font-medium" x-text="item.product_name_snapshot"></td>
                                                <td class="px-3 py-2 text-right text-stone-600" x-text="formatQty(item)"></td>
                                                <td class="px-3 py-2 text-right text-stone-600" x-text="formatPrice(item.unit_price_snapshot)"></td>
                                                <td class="px-3 py-2 text-right font-semibold text-stone-900" x-text="formatPrice(item.line_total)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Supplier Status Transition Controls -->
                        <template x-if="allowedNextStatuses(activeOrder.status).length > 0">
                            <div class="rounded-xl border border-stone-200 bg-stone-50 p-4 space-y-3">
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-stone-700">Update Order Status (Notifies Customer via Email)</h4>
                                <div>
                                    <label for="supplier-status-note" class="block text-xs text-stone-600 mb-1">Optional Supplier Note</label>
                                    <textarea
                                        id="supplier-status-note"
                                        rows="2"
                                        x-model="statusNote"
                                        placeholder="e.g. Dispatched with morning delivery van, or reason if declined..."
                                        class="w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-xs text-stone-900 outline-none focus:border-emerald-500"
                                    ></textarea>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="nextStatus in allowedNextStatuses(activeOrder.status)" :key="nextStatus">
                                        <button
                                            type="button"
                                            :disabled="updatingStatus"
                                            @click="transitionOrder(nextStatus)"
                                            class="rounded-lg px-3.5 py-2 text-xs font-semibold transition-colors cursor-pointer disabled:opacity-50"
                                            :class="transitionButtonClass(nextStatus)"
                                            x-text="'Mark ' + statusLabel(nextStatus)"
                                        ></button>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <!-- Status Timeline -->
                        <template x-if="activeOrder.status_history && activeOrder.status_history.length > 0">
                            <div>
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-stone-500 mb-2">Audit Timeline</h4>
                                <ul class="space-y-2 text-xs">
                                    <template x-for="entry in activeOrder.status_history" :key="entry.id">
                                        <li class="flex items-start justify-between gap-3 rounded-lg bg-stone-50 border border-stone-200 px-3 py-2">
                                            <div>
                                                <span class="font-semibold text-stone-900" x-text="statusLabel(entry.new_status)"></span>
                                                <span class="text-stone-500" x-show="entry.changed_by_name" x-text="' by ' + entry.changed_by_name"></span>
                                                <span class="text-stone-500" x-show="entry.note" x-text="' — ' + entry.note"></span>
                                            </div>
                                            <span class="text-stone-400 shrink-0" x-text="entry.changed_at"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <div class="px-6 py-3.5 border-t border-stone-200 bg-stone-50 flex justify-end">
                <button
                    type="button"
                    @click="closeOrderModal()"
                    class="rounded-lg border border-stone-300 bg-white hover:bg-stone-100 text-stone-700 px-4 py-2 text-xs font-semibold cursor-pointer"
                >
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- ===================== MODAL 2: ADD / EDIT PRODUCT ===================== -->
    <div
        x-show="productModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-stone-900/60"
        @keydown.escape.window="closeProductModal()"
    >
        <div
            @click.away="closeProductModal()"
            class="bg-white rounded-2xl shadow-xl border border-stone-200 max-w-lg w-full max-h-[90vh] flex flex-col overflow-hidden"
        >
            <div class="px-6 py-4 border-b border-stone-200 flex items-center justify-between bg-stone-50">
                <h3 class="text-base font-bold text-stone-900" x-text="productForm.id ? 'Edit Product' : 'Add New Product'"></h3>
                <button
                    type="button"
                    @click="closeProductModal()"
                    class="text-stone-400 hover:text-stone-700 text-xl leading-none px-2 cursor-pointer"
                    aria-label="Close modal"
                >&times;</button>
            </div>

            <form @submit.prevent="saveProduct()" class="p-6 overflow-y-auto space-y-4">
                <div>
                    <label for="prod-description" class="block text-xs font-semibold text-stone-700 mb-1">Product Description *</label>
                    <input
                        id="prod-description"
                        type="text"
                        required
                        x-model="productForm.description"
                        placeholder="e.g. Oyster Mushrooms (Fresh)"
                        class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm text-stone-900 outline-none focus:border-emerald-500"
                    >
                    <p x-show="productFormErrors.description" x-text="productFormErrors.description" class="text-xs text-red-600 mt-1"></p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="prod-category" class="block text-xs font-semibold text-stone-700 mb-1">Category *</label>
                        <select
                            id="prod-category"
                            required
                            x-model="productForm.category_id"
                            class="w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-stone-900 outline-none focus:border-emerald-500"
                        >
                            <template x-for="cat in categories" :key="cat.id">
                                <option :value="String(cat.id)" x-text="cat.name"></option>
                            </template>
                        </select>
                        <p x-show="productFormErrors.category_id" x-text="productFormErrors.category_id" class="text-xs text-red-600 mt-1"></p>
                    </div>

                    <div>
                        <label for="prod-unit" class="block text-xs font-semibold text-stone-700 mb-1">Unit *</label>
                        <select
                            id="prod-unit"
                            required
                            x-model="productForm.unit_id"
                            class="w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-stone-900 outline-none focus:border-emerald-500"
                        >
                            <template x-for="u in units" :key="u.id">
                                <option :value="String(u.id)" x-text="u.name + (u.allow_decimal ? ' (0.1 steps)' : ' (whole)')"></option>
                            </template>
                        </select>
                        <p x-show="productFormErrors.unit_id" x-text="productFormErrors.unit_id" class="text-xs text-red-600 mt-1"></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="prod-price" class="block text-xs font-semibold text-stone-700 mb-1">Unit Price (ZAR) *</label>
                        <input
                            id="prod-price"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                            x-model="productForm.unit_price"
                            class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm text-stone-900 outline-none focus:border-emerald-500"
                        >
                        <p x-show="productFormErrors.unit_price" x-text="productFormErrors.unit_price" class="text-xs text-red-600 mt-1"></p>
                    </div>

                    <div>
                        <label for="prod-stock" class="block text-xs font-semibold text-stone-700 mb-1">Stock Quantity *</label>
                        <input
                            id="prod-stock"
                            type="number"
                            step="0.1"
                            min="0"
                            required
                            x-model="productForm.stock_quantity"
                            class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm text-stone-900 outline-none focus:border-emerald-500"
                        >
                        <p x-show="productFormErrors.stock_quantity" x-text="productFormErrors.stock_quantity" class="text-xs text-red-600 mt-1"></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="prod-sku" class="block text-xs font-semibold text-stone-700 mb-1">SKU (auto-generated if blank)</label>
                        <input
                            id="prod-sku"
                            type="text"
                            x-model="productForm.sku"
                            placeholder="e.g. MUSH-OYSTER-01"
                            class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm font-mono text-stone-900 outline-none focus:border-emerald-500"
                        >
                        <p x-show="productFormErrors.sku" x-text="productFormErrors.sku" class="text-xs text-red-600 mt-1"></p>
                    </div>

                    <div>
                        <label for="prod-image" class="block text-xs font-semibold text-stone-700 mb-1">Image Path (optional)</label>
                        <input
                            id="prod-image"
                            type="text"
                            x-model="productForm.image_path"
                            placeholder="assets/images/products/..."
                            class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm text-stone-900 outline-none focus:border-emerald-500"
                        >
                        <p x-show="productFormErrors.image_path" x-text="productFormErrors.image_path" class="text-xs text-red-600 mt-1"></p>
                    </div>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-stone-700 cursor-pointer">
                    <input
                        type="checkbox"
                        x-model="productForm.is_active"
                        class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500"
                    >
                    <span>Active &amp; visible on customer storefront</span>
                </label>

                <div class="pt-3 border-t border-stone-200 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="closeProductModal()"
                        class="rounded-lg border border-stone-300 bg-white hover:bg-stone-100 text-stone-700 px-4 py-2 text-xs font-semibold cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="savingProduct"
                        class="rounded-lg bg-emerald-700 hover:bg-emerald-600 text-white px-5 py-2 text-xs font-semibold transition-colors cursor-pointer disabled:opacity-50"
                        x-text="savingProduct ? 'Saving...' : (productForm.id ? 'Update Product' : 'Create Product')"
                    ></button>
                </div>
            </form>
        </div>
    </div>
</div>
