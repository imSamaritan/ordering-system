import { apiRequest, formatCurrency, getBasePath } from '../shared/api.js';
import type { ToastStore } from '../shared/toast.js';
import type { CustomerOrdersSummary, Order, OrderItem, OrderStatus } from '../types/index.js';

interface CustomerOrdersPayload {
  orders: Order[];
  summary: CustomerOrdersSummary;
}

interface CustomerOrderDetailPayload {
  order: Order;
}

const EMPTY_SUMMARY: CustomerOrdersSummary = {
  count: 0,
  total_spend: 0,
  delivered_count: 0,
  delivered_spend: 0,
  declined_count: 0,
  declined_total: 0,
  pending_count: 0,
  received_count: 0,
  in_progress_count: 0,
};

export interface CustomerDashboardComponent {
  orders: Order[];
  summary: CustomerOrdersSummary;
  selectedOrderIds: number[];
  loading: boolean;
  loadError: string;
  dateFrom: string;
  dateTo: string;
  orderNumberFilter: string;
  statusFilter: string;
  detailModalOpen: boolean;
  detailLoading: boolean;
  detailError: string;
  activeOrder: Order | null;
  init(): Promise<void>;
  loadOrders(): Promise<void>;
  applyFilters(): Promise<void>;
  resetFilters(): Promise<void>;
  isSelected(orderId: number): boolean;
  toggleSelection(orderId: number): void;
  get allSelected(): boolean;
  toggleSelectAll(): void;
  get selectedCount(): number;
  get selectedTotalSpend(): number;
  get activePipelineCount(): number;
  openOrderDetail(orderId: number): Promise<void>;
  closeOrderDetail(): void;
  statementPdfUrl(mode: 'statement' | 'selected' | 'single', singleOrderId?: number): string;
  formatPrice(amount: number): string;
  formatQty(item: OrderItem): string;
  statusLabel(status: OrderStatus | string): string;
  statusBadgeClass(status: OrderStatus | string): string;
}

export function customerDashboardPage(): CustomerDashboardComponent {
  return {
    orders: [],
    summary: { ...EMPTY_SUMMARY },
    selectedOrderIds: [],
    loading: true,
    loadError: '',
    dateFrom: '',
    dateTo: '',
    orderNumberFilter: '',
    statusFilter: 'all',
    detailModalOpen: false,
    detailLoading: false,
    detailError: '',
    activeOrder: null,

    async init(): Promise<void> {
      await this.loadOrders();
    },

    async loadOrders(): Promise<void> {
      this.loading = true;
      this.loadError = '';

      const params = new URLSearchParams();
      if (this.dateFrom.trim() !== '') {
        params.set('date_from', this.dateFrom.trim());
      }
      if (this.dateTo.trim() !== '') {
        params.set('date_to', this.dateTo.trim());
      }
      if (this.orderNumberFilter.trim() !== '') {
        params.set('order_number', this.orderNumberFilter.trim());
      }
      if (this.statusFilter !== '' && this.statusFilter !== 'all') {
        params.set('status', this.statusFilter);
      }

      const qs = params.toString();
      const path = qs !== '' ? `/customer/orders?${qs}` : '/customer/orders';

      try {
        const res = await apiRequest<CustomerOrdersPayload>(path);
        if (!res.success || !res.data) {
          this.loadError = res.message ?? 'Unable to load your orders.';
          this.orders = [];
          this.summary = { ...EMPTY_SUMMARY };
          this.selectedOrderIds = [];
          return;
        }

        this.orders = res.data.orders;
        this.summary = res.data.summary;
        // Default to selecting all matching invoices so Total Spend reflects the current view immediately
        this.selectedOrderIds = this.orders.map((o) => o.id);
      } catch {
        this.loadError = 'Network error while loading your order history.';
        this.orders = [];
        this.summary = { ...EMPTY_SUMMARY };
        this.selectedOrderIds = [];
      } finally {
        this.loading = false;
      }
    },

    async applyFilters(): Promise<void> {
      if (this.dateFrom !== '' && this.dateTo !== '' && this.dateFrom > this.dateTo) {
        const toast = window.Alpine.store('toast') as ToastStore;
        toast.push('Start date cannot be later than end date.', 'error');
        return;
      }
      await this.loadOrders();
    },

    async resetFilters(): Promise<void> {
      this.dateFrom = '';
      this.dateTo = '';
      this.orderNumberFilter = '';
      this.statusFilter = 'all';
      await this.loadOrders();
    },

    isSelected(orderId: number): boolean {
      return this.selectedOrderIds.includes(orderId);
    },

    toggleSelection(orderId: number): void {
      if (this.isSelected(orderId)) {
        this.selectedOrderIds = this.selectedOrderIds.filter((id) => id !== orderId);
      } else {
        this.selectedOrderIds = [...this.selectedOrderIds, orderId];
      }
    },

    get allSelected(): boolean {
      return this.orders.length > 0 && this.selectedOrderIds.length === this.orders.length;
    },

    toggleSelectAll(): void {
      if (this.allSelected) {
        this.selectedOrderIds = [];
      } else {
        this.selectedOrderIds = this.orders.map((o) => o.id);
      }
    },

    get selectedCount(): number {
      return this.selectedOrderIds.length;
    },

    get selectedTotalSpend(): number {
      if (this.selectedOrderIds.length === 0) {
        return 0;
      }
      const selectedSet = new Set(this.selectedOrderIds);
      let cents = 0;
      for (const order of this.orders) {
        if (selectedSet.has(order.id)) {
          cents += Math.round(order.total_amount * 100);
        }
      }
      return cents / 100;
    },

    get activePipelineCount(): number {
      return (
        this.summary.pending_count +
        this.summary.received_count +
        this.summary.in_progress_count
      );
    },

    async openOrderDetail(orderId: number): Promise<void> {
      this.detailModalOpen = true;
      this.detailLoading = true;
      this.detailError = '';
      this.activeOrder = null;

      try {
        const res = await apiRequest<CustomerOrderDetailPayload>(`/customer/orders/${orderId}`);
        if (!res.success || !res.data?.order) {
          this.detailError = res.message ?? 'Could not load order details.';
          return;
        }
        this.activeOrder = res.data.order;
      } catch {
        this.detailError = 'Network error while fetching order details.';
      } finally {
        this.detailLoading = false;
      }
    },

    closeOrderDetail(): void {
      this.detailModalOpen = false;
      this.activeOrder = null;
      this.detailError = '';
    },

    statementPdfUrl(mode: 'statement' | 'selected' | 'single', singleOrderId?: number): string {
      const base = `${getBasePath()}/api/v1/customer/reports/pdf`;
      const params = new URLSearchParams();

      if (mode === 'single' && singleOrderId) {
        params.set('order_ids', String(singleOrderId));
      } else if (mode === 'selected') {
        if (this.selectedOrderIds.length > 0) {
          params.set('order_ids', this.selectedOrderIds.join(','));
        }
        if (this.dateFrom.trim() !== '') {
          params.set('date_from', this.dateFrom.trim());
        }
        if (this.dateTo.trim() !== '') {
          params.set('date_to', this.dateTo.trim());
        }
      } else {
        // Complete / Delivered & Declined statement
        params.set('status', 'complete_and_declined');
        if (this.dateFrom.trim() !== '') {
          params.set('date_from', this.dateFrom.trim());
        }
        if (this.dateTo.trim() !== '') {
          params.set('date_to', this.dateTo.trim());
        }
        if (this.orderNumberFilter.trim() !== '') {
          params.set('order_number', this.orderNumberFilter.trim());
        }
      }

      const qs = params.toString();
      return qs !== '' ? `${base}?${qs}` : base;
    },

    formatPrice(amount: number): string {
      return formatCurrency(amount);
    },

    formatQty(item: OrderItem): string {
      const unit = item.unit_name_snapshot || 'Unit';
      if (unit.toLowerCase() === 'kg') {
        return `${item.quantity.toFixed(1)} kg`;
      }
      return `${Math.round(item.quantity)} ${unit}`;
    },

    statusLabel(status: OrderStatus | string): string {
      switch (status) {
        case 'delivered':
          return 'Delivered';
        case 'declined':
          return 'Declined';
        case 'in_progress':
          return 'In Progress';
        case 'received':
          return 'Received';
        default:
          return 'Pending';
      }
    },

    statusBadgeClass(status: OrderStatus | string): string {
      switch (status) {
        case 'delivered':
          return 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-950/70 dark:text-emerald-300 dark:border-emerald-800/80';
        case 'declined':
          return 'bg-red-100 text-red-800 border-red-200 dark:bg-red-950/70 dark:text-red-300 dark:border-red-800/80';
        case 'in_progress':
          return 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-950/70 dark:text-blue-300 dark:border-blue-800/80';
        case 'received':
          return 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-950/70 dark:text-amber-300 dark:border-amber-800/80';
        default:
          return 'bg-stone-100 text-stone-700 border-stone-200 dark:bg-stone-800 dark:text-stone-300 dark:border-stone-700';
      }
    },
  };
}

document.addEventListener('alpine:init', () => {
  window.Alpine.data('customerDashboardPage', customerDashboardPage);
});
