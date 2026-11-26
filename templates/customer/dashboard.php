<?php
/**
 * @var string $basePath
 */
$base = $basePath ?? '';
?>
<div x-data="customerDashboardPage" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-8 space-y-5 sm:space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-stone-900 tracking-tight">Customer Order Dashboard</h1>
            <p class="text-sm text-stone-600 mt-1">
                Track your fresh produce orders, calculate total spend across selected invoices, and export PDF statements.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-2.5">
            <a
                :href="statementPdfUrl('statement')"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-700 hover:bg-emerald-600 text-white px-4 py-2.5 sm:py-2 text-sm font-semibold shadow-sm transition-colors"
            >
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>PDF Statement (Delivered &amp; Declined)</span>
            </a>

            <a
                x-show="selectedCount > 0"
                :href="statementPdfUrl('selected')"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-stone-300 bg-white hover:bg-stone-50 text-stone-800 px-3.5 py-2.5 sm:py-2 text-sm font-medium shadow-sm transition-colors"
            >
                <span>Export Selected (<span x-text="selectedCount"></span>)</span>
            </a>

            <a
                href="<?= htmlspecialchars($base . '/', ENT_QUOTES, 'UTF-8') ?>"
                class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-900 px-3.5 py-2.5 sm:py-2 text-sm font-medium transition-colors"
            >
                <span>+ New Order</span>
            </a>
        </div>
    </div>

    <!-- Summary Metric Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-4 sm:p-5 shadow-xs">
            <div class="text-[11px] sm:text-xs font-semibold uppercase tracking-wider text-emerald-800">Total Spend (Selected)</div>
            <div class="mt-1.5 sm:mt-2 text-lg sm:text-2xl font-bold text-emerald-950 truncate" x-text="formatPrice(selectedTotalSpend)"></div>
            <div class="mt-1 text-[11px] sm:text-xs text-emerald-700">
                <span x-text="selectedCount"></span> of <span x-text="orders.length"></span> invoices
            </div>
        </div>

        <div class="rounded-xl border border-stone-200 bg-white p-4 sm:p-5 shadow-xs">
            <div class="text-[11px] sm:text-xs font-semibold uppercase tracking-wider text-stone-500">Delivered Spend</div>
            <div class="mt-1.5 sm:mt-2 text-lg sm:text-2xl font-bold text-stone-900 truncate" x-text="formatPrice(summary.delivered_spend)"></div>
            <div class="mt-1 text-[11px] sm:text-xs text-stone-500">
                <span x-text="summary.delivered_count"></span> completed order(s)
            </div>
        </div>

        <div class="rounded-xl border border-stone-200 bg-white p-4 sm:p-5 shadow-xs">
            <div class="text-[11px] sm:text-xs font-semibold uppercase tracking-wider text-stone-500">Declined Orders</div>
            <div class="mt-1.5 sm:mt-2 text-lg sm:text-2xl font-bold text-red-700 truncate" x-text="formatPrice(summary.declined_total)"></div>
            <div class="mt-1 text-[11px] sm:text-xs text-stone-500">
                <span x-text="summary.declined_count"></span> declined order(s)
            </div>
        </div>

        <div class="rounded-xl border border-stone-200 bg-white p-4 sm:p-5 shadow-xs">
            <div class="text-[11px] sm:text-xs font-semibold uppercase tracking-wider text-stone-500">In Progress / Open</div>
            <div class="mt-1.5 sm:mt-2 text-lg sm:text-2xl font-bold text-stone-900" x-text="activePipelineCount"></div>
            <div class="mt-1 text-[11px] sm:text-xs text-stone-500 leading-relaxed">
                Pending: <span x-text="summary.pending_count"></span> &middot;
                Received: <span x-text="summary.received_count"></span> &middot;
                Processing: <span x-text="summary.in_progress_count"></span>
            </div>
        </div>
    </div>

    <!-- Filters Form -->
    <form
        @submit.prevent="applyFilters()"
        class="rounded-xl border border-stone-200 bg-white p-4 sm:p-5 shadow-xs"
    >
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
            <div>
                <label for="filter-date-from" class="block text-xs font-semibold text-stone-600 mb-1">Date From</label>
                <input
                    id="filter-date-from"
                    type="date"
                    x-model="dateFrom"
                    class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm text-stone-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none"
                >
            </div>

            <div>
                <label for="filter-date-to" class="block text-xs font-semibold text-stone-600 mb-1">Date To</label>
                <input
                    id="filter-date-to"
                    type="date"
                    x-model="dateTo"
                    class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm text-stone-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none"
                >
            </div>

            <div>
                <label for="filter-order-number" class="block text-xs font-semibold text-stone-600 mb-1">Order Number</label>
                <input
                    id="filter-order-number"
                    type="text"
                    x-model="orderNumberFilter"
                    placeholder="e.g. ORD-2026-"
                    class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm text-stone-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none"
                >
            </div>

            <div>
                <label for="filter-status" class="block text-xs font-semibold text-stone-600 mb-1">Order Status</label>
                <select
                    id="filter-status"
                    x-model="statusFilter"
                    class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm text-stone-900 bg-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none"
                >
                    <option value="all">All Statuses</option>
                    <option value="complete_and_declined">Delivered &amp; Declined</option>
                    <option value="pending">Pending</option>
                    <option value="received">Received</option>
                    <option value="in_progress">In Progress</option>
                    <option value="delivered">Delivered (Complete)</option>
                    <option value="declined">Declined</option>
                </select>
            </div>

            <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-1">
                <button
                    type="submit"
                    class="flex-1 min-h-10 rounded-lg bg-stone-900 hover:bg-stone-800 text-white px-4 py-2 text-sm font-semibold transition-colors cursor-pointer"
                >
                    Filter
                </button>
                <button
                    type="button"
                    @click="resetFilters()"
                    class="min-h-10 rounded-lg border border-stone-300 bg-white hover:bg-stone-50 text-stone-700 px-3.5 py-2 text-sm font-medium transition-colors cursor-pointer"
                >
                    Reset
                </button>
            </div>
        </div>
    </form>

    <!-- Error Alert -->
    <template x-if="loadError !== ''">
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="loadError"></div>
    </template>

    <!-- Orders Table / Mobile Cards -->
    <div class="rounded-xl border border-stone-200 bg-white shadow-xs overflow-hidden">
        <div class="px-4 sm:px-5 py-3.5 border-b border-stone-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1.5 bg-stone-50/70">
            <h2 class="text-sm font-semibold text-stone-800">
                Order Invoices (<span x-text="orders.length"></span>)
            </h2>
            <span class="text-xs text-stone-500">
                Check or uncheck invoices to update Total Spend
            </span>
        </div>

        <!-- Loading state -->
        <template x-if="loading">
            <div class="p-8 sm:p-10 text-center text-sm text-stone-500">Loading your order history...</div>
        </template>

        <!-- Empty state -->
        <template x-if="!loading && orders.length === 0 && loadError === ''">
            <div class="p-8 sm:p-10 text-center space-y-2">
                <p class="text-sm font-medium text-stone-700">No orders found for the selected filters.</p>
                <p class="text-xs text-stone-500">Try clearing your filters or place a new produce order from the storefront.</p>
            </div>
        </template>

        <!-- Mobile Card List -->
        <template x-if="!loading && orders.length > 0">
            <div class="md:hidden divide-y divide-stone-200">
                <div class="px-4 py-2.5 bg-stone-50 flex items-center justify-between text-xs text-stone-600">
                    <label class="inline-flex items-center gap-2 font-medium cursor-pointer">
                        <input
                            type="checkbox"
                            :checked="allSelected"
                            @change="toggleSelectAll()"
                            aria-label="Select all orders"
                            class="w-4 h-4 rounded border-stone-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                        >
                        <span>Select all invoices</span>
                    </label>
                    <span class="font-semibold text-emerald-900" x-text="formatPrice(selectedTotalSpend)"></span>
                </div>

                <template x-for="order in orders" :key="order.id">
                    <div
                        class="p-4 space-y-3 transition-colors"
                        :class="isSelected(order.id) ? 'bg-emerald-50/30' : 'bg-white'"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <label class="inline-flex items-start gap-2.5 min-w-0 cursor-pointer">
                                <input
                                    type="checkbox"
                                    :checked="isSelected(order.id)"
                                    @change="toggleSelection(order.id)"
                                    :aria-label="'Select order ' + order.order_number"
                                    class="mt-0.5 w-4 h-4 rounded border-stone-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer shrink-0"
                                >
                                <div class="min-w-0">
                                    <div class="font-mono text-sm font-bold text-stone-900 truncate" x-text="order.order_number"></div>
                                    <div class="text-xs text-stone-500 mt-0.5" x-text="order.ordered_at"></div>
                                </div>
                            </label>
                            <span
                                class="shrink-0 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border"
                                :class="statusBadgeClass(order.status)"
                                x-text="statusLabel(order.status)"
                            ></span>
                        </div>

                        <div class="flex items-center justify-between text-xs text-stone-600 pt-1">
                            <span><strong class="text-stone-900" x-text="order.item_count ?? 0"></strong> item(s)</span>
                            <span class="text-sm font-bold text-stone-900 font-mono" x-text="formatPrice(order.total_amount)"></span>
                        </div>

                        <button
                            type="button"
                            @click="openOrderDetail(order.id)"
                            class="w-full rounded-lg border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-900 py-2 text-xs font-semibold transition-colors cursor-pointer"
                        >
                            View Invoice
                        </button>
                    </div>
                </template>
            </div>
        </template>

        <!-- Desktop Table content -->
        <template x-if="!loading && orders.length > 0">
            <div class="hidden md:block overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead class="bg-stone-50 text-xs uppercase tracking-wider text-stone-500">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left w-10">
                                <input
                                    type="checkbox"
                                    :checked="allSelected"
                                    @change="toggleSelectAll()"
                                    aria-label="Select all orders"
                                    class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                                >
                            </th>
                            <th scope="col" class="px-4 py-3 text-left font-semibold">Order #</th>
                            <th scope="col" class="px-4 py-3 text-left font-semibold">Ordered Date</th>
                            <th scope="col" class="px-4 py-3 text-left font-semibold">Status</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Items</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Total</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100 bg-white">
                        <template x-for="order in orders" :key="order.id">
                            <tr
                                class="hover:bg-stone-50/80 transition-colors"
                                :class="isSelected(order.id) ? 'bg-emerald-50/30' : ''"
                            >
                                <td class="px-4 py-3.5">
                                    <input
                                        type="checkbox"
                                        :checked="isSelected(order.id)"
                                        @change="toggleSelection(order.id)"
                                        :aria-label="'Select order ' + order.order_number"
                                        class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                                    >
                                </td>
                                <td class="px-4 py-3.5 font-mono font-semibold text-stone-900 whitespace-nowrap" x-text="order.order_number"></td>
                                <td class="px-4 py-3.5 text-stone-600 whitespace-nowrap" x-text="order.ordered_at"></td>
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border"
                                        :class="statusBadgeClass(order.status)"
                                        x-text="statusLabel(order.status)"
                                    ></span>
                                </td>
                                <td class="px-4 py-3.5 text-right text-stone-600" x-text="order.item_count ?? 0"></td>
                                <td class="px-4 py-3.5 text-right font-semibold text-stone-900 whitespace-nowrap" x-text="formatPrice(order.total_amount)"></td>
                                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                    <button
                                        type="button"
                                        @click="openOrderDetail(order.id)"
                                        class="text-emerald-700 hover:text-emerald-900 font-semibold text-xs cursor-pointer"
                                    >
                                        View Invoice
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>
    </div>

    <!-- Order Detail Modal -->
    <div
        x-show="detailModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4 bg-stone-900/60"
        @keydown.escape.window="closeOrderDetail()"
    >
        <div
            @click.away="closeOrderDetail()"
            class="bg-white rounded-2xl shadow-xl border border-stone-200 max-w-2xl w-full max-h-[92dvh] sm:max-h-[90vh] flex flex-col overflow-hidden"
        >
            <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-b border-stone-200 flex items-center justify-between gap-2 bg-stone-50 shrink-0">
                <div class="min-w-0">
                    <h3 class="text-base font-bold text-stone-900 truncate" x-text="activeOrder ? activeOrder.order_number : 'Order Invoice'"></h3>
                    <p class="text-xs text-stone-500" x-show="activeOrder" x-text="activeOrder ? 'Ordered: ' + activeOrder.ordered_at : ''"></p>
                </div>
                <button
                    type="button"
                    @click="closeOrderDetail()"
                    class="w-9 h-9 inline-flex items-center justify-center rounded-lg text-stone-400 hover:text-stone-700 hover:bg-stone-200/60 text-xl leading-none cursor-pointer shrink-0"
                    aria-label="Close modal"
                >&times;</button>
            </div>

            <div class="p-4 sm:p-6 overflow-y-auto space-y-5 flex-1">
                <template x-if="detailLoading">
                    <div class="py-10 text-center text-sm text-stone-500">Loading invoice details...</div>
                </template>

                <template x-if="!detailLoading && detailError !== ''">
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="detailError"></div>
                </template>

                <template x-if="!detailLoading && activeOrder">
                    <div class="space-y-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <span
                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border"
                                :class="statusBadgeClass(activeOrder.status)"
                                x-text="statusLabel(activeOrder.status)"
                            ></span>
                            <div class="text-lg font-bold text-emerald-900" x-text="formatPrice(activeOrder.total_amount)"></div>
                        </div>

                        <template x-if="activeOrder.notes">
                            <div class="rounded-lg bg-stone-50 border border-stone-200 px-3.5 py-2.5 text-xs text-stone-700">
                                <span class="font-semibold text-stone-900">Order Notes:</span>
                                <span x-text="activeOrder.notes"></span>
                            </div>
                        </template>

                        <!-- Line Items -->
                        <div>
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-stone-500 mb-2">Line Items</h4>
                            <div class="border border-stone-200 rounded-lg overflow-x-auto">
                                <table class="min-w-full divide-y divide-stone-200 text-xs">
                                    <thead class="bg-stone-50 text-stone-500">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-semibold">Product</th>
                                            <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">Qty</th>
                                            <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">Unit Price</th>
                                            <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">Line Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-stone-100">
                                        <template x-for="item in (activeOrder.items ?? [])" :key="item.id">
                                            <tr>
                                                <td class="px-3 py-2 text-stone-900 font-medium" x-text="item.product_name_snapshot"></td>
                                                <td class="px-3 py-2 text-right text-stone-600 whitespace-nowrap" x-text="formatQty(item)"></td>
                                                <td class="px-3 py-2 text-right text-stone-600 whitespace-nowrap" x-text="formatPrice(item.unit_price_snapshot)"></td>
                                                <td class="px-3 py-2 text-right font-semibold text-stone-900 whitespace-nowrap" x-text="formatPrice(item.line_total)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Status History -->
                        <template x-if="activeOrder.status_history && activeOrder.status_history.length > 0">
                            <div>
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-stone-500 mb-2">Status Timeline</h4>
                                <ul class="space-y-2 text-xs">
                                    <template x-for="entry in activeOrder.status_history" :key="entry.id">
                                        <li class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-1 sm:gap-3 rounded-lg bg-stone-50 border border-stone-200 px-3 py-2">
                                            <div>
                                                <span class="font-semibold text-stone-900" x-text="statusLabel(entry.new_status)"></span>
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

            <div class="px-4 sm:px-6 py-3.5 border-t border-stone-200 bg-stone-50 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-2 shrink-0">
                <a
                    x-show="activeOrder"
                    :href="activeOrder ? statementPdfUrl('single', activeOrder.id) : '#'"
                    target="_blank"
                    rel="noopener"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 rounded-lg bg-emerald-700 hover:bg-emerald-600 text-white px-3.5 py-2.5 sm:py-2 text-xs font-semibold transition-colors"
                >
                    Download Invoice PDF
                </a>
                <button
                    type="button"
                    @click="closeOrderDetail()"
                    class="w-full sm:w-auto sm:ml-auto rounded-lg border border-stone-300 bg-white hover:bg-stone-100 text-stone-700 px-3.5 py-2.5 sm:py-2 text-xs font-semibold transition-colors cursor-pointer"
                >
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
