import { apiRequest, formatCurrency, getBasePath } from '../shared/api.js';
import type { CartStore } from '../shared/cartStore.js';
import type { ToastStore } from '../shared/toast.js';
import type {
  CartItem,
  CustomerOrdersSummary,
  Order,
  OrderItem,
  OrderStatus,
  ReorderPreviewItem,
  ReorderPreviewPayload,
} from '../types/index.js';

interface CustomerOrdersPayload {
  orders: Order[];
  summary: CustomerOrdersSummary;
}

interface CustomerOrderDetailPayload {
  order: Order;
}

interface CustomerOrderMutationPayload {
  message: string;
  order: Order;
}

const EMPTY_SUMMARY: CustomerOrdersSummary = {
  count: 0,
  total_spend: 0,
  delivered_count: 0,
  delivered_spend: 0,
  declined_count: 0,
  declined_total: 0,
  cancelled_count: 0,
  cancelled_total: 0,
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
  reorderingOrderId: number | null;
  reorderChoiceModalOpen: boolean;
  pendingReorderPreview: ReorderPreviewPayload | null;
  editingNotes: boolean;
  notesDraft: string;
  savingNotes: boolean;
  cancelModalOpen: boolean;
  cancelReason: string;
  cancellingOrder: boolean;
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
  startEditingNotes(): void;
  cancelEditingNotes(): void;
  saveOrderNotes(): Promise<void>;
  openCancelModal(): void;
  closeCancelModal(): void;
  confirmCancelOrder(): Promise<void>;
  reorderOrder(orderId: number): Promise<void>;
  confirmReorder(mode: 'merge' | 'replace'): void;
  cancelReorderChoice(): void;
  applyReorder(preview: ReorderPreviewPayload, mode: 'merge' | 'replace'): void;
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
    reorderingOrderId: null,
    reorderChoiceModalOpen: false,
    pendingReorderPreview: null,
    editingNotes: false,
    notesDraft: '',
    savingNotes: false,
    cancelModalOpen: false,
    cancelReason: '',
    cancellingOrder: false,

    async init(): Promise<void> {
      window.addEventListener('order:placed', () => {
        void this.loadOrders();
      });
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
        // Default to selecting active/completed invoices; exclude declined and cancelled
        // unless the user explicitly filtered for them (or manually selects their checkboxes).
        const includeExcludedByDefault =
          this.statusFilter === 'declined' ||
          this.statusFilter === 'cancelled' ||
          this.statusFilter === 'complete_and_declined';
        this.selectedOrderIds = includeExcludedByDefault
          ? this.orders.map((o) => o.id)
          : this.orders
              .filter((o) => o.status !== 'declined' && o.status !== 'cancelled')
              .map((o) => o.id);
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
      this.editingNotes = false;
      this.notesDraft = '';
      this.cancelModalOpen = false;
      this.cancelReason = '';

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
      this.editingNotes = false;
      this.notesDraft = '';
      this.cancelModalOpen = false;
      this.cancelReason = '';
    },

    startEditingNotes(): void {
      if (!this.activeOrder || this.activeOrder.status !== 'pending') {
        return;
      }
      this.notesDraft = this.activeOrder.notes ?? '';
      this.editingNotes = true;
    },

    cancelEditingNotes(): void {
      this.editingNotes = false;
      this.notesDraft = '';
    },

    async saveOrderNotes(): Promise<void> {
      if (!this.activeOrder || this.savingNotes || this.activeOrder.status !== 'pending') {
        return;
      }

      const toast = window.Alpine.store('toast') as ToastStore;
      this.savingNotes = true;

      try {
        const res = await apiRequest<CustomerOrderMutationPayload>(
          `/customer/orders/${this.activeOrder.id}/notes`,
          {
            method: 'PATCH',
            body: JSON.stringify({
              notes: this.notesDraft.trim(),
            }),
          }
        );

        if (!res.success || !res.data?.order) {
          toast.push(res.message ?? 'Could not update delivery notes.', 'error');
          return;
        }

        this.activeOrder = res.data.order;
        this.editingNotes = false;
        this.notesDraft = '';
        toast.push(res.data.message || 'Delivery notes updated.', 'success');

        const updatedOrder = res.data.order;
        this.orders = this.orders.map((o) =>
          o.id === updatedOrder.id ? { ...o, notes: updatedOrder.notes } : o
        );
      } catch {
        toast.push('Network error while updating delivery notes.', 'error');
      } finally {
        this.savingNotes = false;
      }
    },

    openCancelModal(): void {
      if (!this.activeOrder || this.activeOrder.status !== 'pending') {
        return;
      }
      this.cancelReason = '';
      this.cancelModalOpen = true;
    },

    closeCancelModal(): void {
      if (this.cancellingOrder) {
        return;
      }
      this.cancelModalOpen = false;
      this.cancelReason = '';
    },

    async confirmCancelOrder(): Promise<void> {
      if (!this.activeOrder || this.cancellingOrder || this.activeOrder.status !== 'pending') {
        return;
      }

      const toast = window.Alpine.store('toast') as ToastStore;
      this.cancellingOrder = true;

      try {
        const res = await apiRequest<CustomerOrderMutationPayload>(
          `/customer/orders/${this.activeOrder.id}/cancel`,
          {
            method: 'POST',
            body: JSON.stringify({
              reason: this.cancelReason.trim(),
            }),
          }
        );

        if (!res.success || !res.data?.order) {
          toast.push(res.message ?? 'Could not cancel this order.', 'error');
          return;
        }

        this.activeOrder = res.data.order;
        this.cancelModalOpen = false;
        this.cancelReason = '';
        this.editingNotes = false;
        this.notesDraft = '';
        toast.push(res.data.message || 'Order cancelled.', 'success');
        await this.loadOrders();
      } catch {
        toast.push('Network error while cancelling order.', 'error');
      } finally {
        this.cancellingOrder = false;
      }
    },

    async reorderOrder(orderId: number): Promise<void> {
      if (this.reorderingOrderId !== null) {
        return;
      }

      const toast = window.Alpine.store('toast') as ToastStore;
      const cart = window.Alpine.store('cart') as CartStore;
      this.reorderingOrderId = orderId;

      try {
        const res = await apiRequest<ReorderPreviewPayload>(`/customer/orders/${orderId}/reorder`);
        if (!res.success || !res.data) {
          toast.push(res.message ?? 'Could not validate items for reorder.', 'error');
          return;
        }

        const preview = res.data;
        if (!preview.summary.can_reorder) {
          toast.push(
            `None of the items from ${preview.order_number} are currently available to reorder.`,
            'error'
          );
          return;
        }

        if (cart.totalItems > 0) {
          this.pendingReorderPreview = preview;
          this.reorderChoiceModalOpen = true;
          return;
        }

        this.applyReorder(preview, 'replace');
      } catch {
        toast.push('Network error while preparing your reorder.', 'error');
      } finally {
        this.reorderingOrderId = null;
      }
    },

    confirmReorder(mode: 'merge' | 'replace'): void {
      if (!this.pendingReorderPreview) {
        this.cancelReorderChoice();
        return;
      }
      const preview = this.pendingReorderPreview;
      this.reorderChoiceModalOpen = false;
      this.pendingReorderPreview = null;
      this.applyReorder(preview, mode);
    },

    cancelReorderChoice(): void {
      this.reorderChoiceModalOpen = false;
      this.pendingReorderPreview = null;
    },

    applyReorder(preview: ReorderPreviewPayload, mode: 'merge' | 'replace'): void {
      const cart = window.Alpine.store('cart') as CartStore;
      const toast = window.Alpine.store('toast') as ToastStore;

      const toCartItem = (item: ReorderPreviewItem): CartItem => ({
        productId: item.product_id,
        sku: item.sku,
        description: item.description,
        unitName: item.unit_name || 'Unit',
        allowDecimal: Boolean(item.allow_decimal),
        stepSize: item.step_size || (item.allow_decimal ? 0.1 : 1),
        unitPrice: item.unit_price,
        quantityTenths: Math.round(item.suggested_quantity * 10),
        imagePath: item.image_path,
        maxStock: item.stock_quantity,
      });

      const reorderItems = [...preview.available, ...preview.adjusted].map(toCartItem);
      cart.addBulkItems(reorderItems, mode);

      const notes: string[] = [];
      if (preview.summary.adjusted_count > 0) {
        notes.push(`${preview.summary.adjusted_count} qty adjusted to stock`);
      }
      if (preview.summary.unavailable_count > 0) {
        notes.push(`${preview.summary.unavailable_count} unavailable skipped`);
      }
      if (preview.summary.price_changed_count > 0) {
        notes.push(`${preview.summary.price_changed_count} updated to current price`);
      }

      const verb = mode === 'merge' ? 'Merged' : 'Loaded';
      const baseMsg = `${verb} ${reorderItems.length} item(s) from ${preview.order_number} into your cart.`;
      const fullMsg = notes.length > 0 ? `${baseMsg} (${notes.join('; ')})` : baseMsg;
      const toastType =
        preview.summary.adjusted_count > 0 ||
        preview.summary.unavailable_count > 0 ||
        preview.summary.price_changed_count > 0
          ? 'info'
          : 'success';

      toast.push(fullMsg, toastType);

      if (this.detailModalOpen) {
        this.closeOrderDetail();
      }
      cart.open();
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
        case 'cancelled':
          return 'Cancelled';
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
        case 'cancelled':
          return 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800/80 dark:text-slate-300 dark:border-slate-700';
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
