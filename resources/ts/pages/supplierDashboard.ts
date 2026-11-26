import { apiRequest, formatCurrency, getBasePath } from '../shared/api.js';
import type { ToastStore } from '../shared/toast.js';
import type {
  Category,
  Order,
  OrderItem,
  OrderStatus,
  Product,
  SupplierCompaniesSummary,
  SupplierCompanyCard,
  SupplierInventorySummary,
  Unit,
} from '../types/index.js';

interface SupplierCompaniesPayload {
  companies: SupplierCompanyCard[];
  summary: SupplierCompaniesSummary;
}

interface CompanyOrdersPayload {
  company: SupplierCompanyCard;
  orders: Order[];
}

interface OrderDetailPayload {
  order: Order;
}

interface OrderStatusUpdatePayload {
  message: string;
  order: Order;
  email_sent: boolean;
}

interface SupplierProductsPayload {
  products: Product[];
  categories: Category[];
  units: Unit[];
  summary: SupplierInventorySummary;
}

interface ProductMutationPayload {
  message: string;
  product: Product;
}

interface ProductDeletePayload {
  message: string;
  action: 'deleted' | 'deactivated';
  product?: Product;
  deleted_id?: number;
}

interface ProductFormState {
  id: number | null;
  description: string;
  category_id: string;
  unit_id: string;
  sku: string;
  unit_price: string;
  stock_quantity: string;
  image_path: string;
  is_active: boolean;
}

const EMPTY_COMPANIES_SUMMARY: SupplierCompaniesSummary = {
  total_companies: 0,
  total_orders: 0,
  pending_orders: 0,
  in_progress_orders: 0,
  delivered_orders: 0,
  total_revenue: 0,
};

const EMPTY_INVENTORY_SUMMARY: SupplierInventorySummary = {
  total: 0,
  active: 0,
  inactive: 0,
  low_stock: 0,
};

const ALLOWED_TRANSITIONS: Record<OrderStatus, OrderStatus[]> = {
  pending: ['received', 'in_progress', 'delivered', 'declined'],
  received: ['in_progress', 'delivered', 'declined'],
  in_progress: ['delivered', 'declined'],
  delivered: [],
  declined: [],
};

function createEmptyProductForm(): ProductFormState {
  return {
    id: null,
    description: '',
    category_id: '',
    unit_id: '',
    sku: '',
    unit_price: '0.00',
    stock_quantity: '100',
    image_path: '',
    is_active: true,
  };
}

export interface SupplierDashboardComponent {
  activeTab: 'companies' | 'inventory';

  // Companies & Orders state
  companies: SupplierCompanyCard[];
  companiesSummary: SupplierCompaniesSummary;
  companiesLoading: boolean;
  companiesError: string;
  companySearch: string;

  // Selected Company Orders drawer/panel state
  selectedCompany: SupplierCompanyCard | null;
  companyOrders: Order[];
  companyOrdersLoading: boolean;
  companyOrdersError: string;
  companyOrderStatusFilter: string;
  companyOrderNumberFilter: string;

  // Order Detail & Status transition modal state
  orderModalOpen: boolean;
  orderModalLoading: boolean;
  orderModalError: string;
  activeOrder: Order | null;
  statusNote: string;
  updatingStatus: boolean;

  // Inventory CRUD state
  products: Product[];
  categories: Category[];
  units: Unit[];
  inventorySummary: SupplierInventorySummary;
  inventoryLoading: boolean;
  inventoryError: string;
  inventoryLoaded: boolean;
  productSearch: string;
  productCategoryFilter: string;
  productStatusFilter: string;

  // Add/Edit Product modal state
  productModalOpen: boolean;
  productForm: ProductFormState;
  productFormErrors: Record<string, string>;
  savingProduct: boolean;
  deletingProductId: number | null;

  init(): Promise<void>;
  switchTab(tab: 'companies' | 'inventory'): Promise<void>;

  // Companies methods
  loadCompanies(): Promise<void>;
  openCompanyDetails(company: SupplierCompanyCard): Promise<void>;
  closeCompanyDetails(): void;
  loadCompanyOrders(): Promise<void>;

  // Order detail & status methods
  openOrderModal(orderId: number): Promise<void>;
  closeOrderModal(): void;
  allowedNextStatuses(status: OrderStatus | string): OrderStatus[];
  transitionOrder(nextStatus: OrderStatus): Promise<void>;

  // Inventory CRUD methods
  loadInventory(): Promise<void>;
  openCreateProductModal(): void;
  openEditProductModal(product: Product): void;
  closeProductModal(): void;
  saveProduct(): Promise<void>;
  toggleProductActive(product: Product): Promise<void>;
  deleteProduct(product: Product): Promise<void>;

  // Helpers
  formatPrice(amount: number): string;
  formatQty(item: OrderItem): string;
  productImageUrl(imagePath: string | null): string;
  statusLabel(status: OrderStatus | string): string;
  statusBadgeClass(status: OrderStatus | string): string;
  transitionButtonClass(status: OrderStatus): string;
}

export function supplierDashboardPage(
  initialTab: 'companies' | 'inventory' = 'companies'
): SupplierDashboardComponent {
  return {
    activeTab: initialTab,

    companies: [],
    companiesSummary: { ...EMPTY_COMPANIES_SUMMARY },
    companiesLoading: true,
    companiesError: '',
    companySearch: '',

    selectedCompany: null,
    companyOrders: [],
    companyOrdersLoading: false,
    companyOrdersError: '',
    companyOrderStatusFilter: 'all',
    companyOrderNumberFilter: '',

    orderModalOpen: false,
    orderModalLoading: false,
    orderModalError: '',
    activeOrder: null,
    statusNote: '',
    updatingStatus: false,

    products: [],
    categories: [],
    units: [],
    inventorySummary: { ...EMPTY_INVENTORY_SUMMARY },
    inventoryLoading: false,
    inventoryError: '',
    inventoryLoaded: false,
    productSearch: '',
    productCategoryFilter: 'all',
    productStatusFilter: 'all',

    productModalOpen: false,
    productForm: createEmptyProductForm(),
    productFormErrors: {},
    savingProduct: false,
    deletingProductId: null,

    async init(): Promise<void> {
      if (this.activeTab === 'inventory') {
        await Promise.all([this.loadInventory(), this.loadCompanies()]);
      } else {
        await this.loadCompanies();
      }
    },

    async switchTab(tab: 'companies' | 'inventory'): Promise<void> {
      this.activeTab = tab;
      const base = getBasePath();
      const targetUrl = tab === 'inventory' ? `${base}/supplier/inventory` : `${base}/supplier`;
      if (window.location.pathname !== targetUrl) {
        window.history.replaceState({}, '', targetUrl);
      }

      if (tab === 'inventory' && !this.inventoryLoaded) {
        await this.loadInventory();
      }
    },

    async loadCompanies(): Promise<void> {
      this.companiesLoading = true;
      this.companiesError = '';

      const params = new URLSearchParams();
      if (this.companySearch.trim() !== '') {
        params.set('search', this.companySearch.trim());
      }
      const qs = params.toString();
      const path = qs !== '' ? `/supplier/companies?${qs}` : '/supplier/companies';

      try {
        const res = await apiRequest<SupplierCompaniesPayload>(path);
        if (!res.success || !res.data) {
          this.companiesError = res.message ?? 'Unable to load customer and company accounts.';
          this.companies = [];
          this.companiesSummary = { ...EMPTY_COMPANIES_SUMMARY };
          return;
        }
        this.companies = res.data.companies;
        this.companiesSummary = res.data.summary;

        // Refresh selectedCompany card stats if open
        if (this.selectedCompany) {
          const updated = this.companies.find((c) => c.id === this.selectedCompany?.id);
          if (updated) {
            this.selectedCompany = updated;
          }
        }
      } catch {
        this.companiesError = 'Network error while loading companies.';
      } finally {
        this.companiesLoading = false;
      }
    },

    async openCompanyDetails(company: SupplierCompanyCard): Promise<void> {
      this.selectedCompany = company;
      this.companyOrderStatusFilter = 'all';
      this.companyOrderNumberFilter = '';
      await this.loadCompanyOrders();
    },

    closeCompanyDetails(): void {
      this.selectedCompany = null;
      this.companyOrders = [];
      this.companyOrdersError = '';
    },

    async loadCompanyOrders(): Promise<void> {
      if (!this.selectedCompany) {
        return;
      }

      this.companyOrdersLoading = true;
      this.companyOrdersError = '';

      const params = new URLSearchParams();
      if (this.companyOrderStatusFilter !== 'all') {
        params.set('status', this.companyOrderStatusFilter);
      }
      if (this.companyOrderNumberFilter.trim() !== '') {
        params.set('order_number', this.companyOrderNumberFilter.trim());
      }
      const qs = params.toString();
      const basePath = `/supplier/companies/${this.selectedCompany.id}/orders`;
      const path = qs !== '' ? `${basePath}?${qs}` : basePath;

      try {
        const res = await apiRequest<CompanyOrdersPayload>(path);
        if (!res.success || !res.data) {
          this.companyOrdersError = res.message ?? 'Unable to load orders for this account.';
          this.companyOrders = [];
          return;
        }
        this.selectedCompany = res.data.company;
        this.companyOrders = res.data.orders;
      } catch {
        this.companyOrdersError = 'Network error while loading company orders.';
      } finally {
        this.companyOrdersLoading = false;
      }
    },

    async openOrderModal(orderId: number): Promise<void> {
      this.orderModalOpen = true;
      this.orderModalLoading = true;
      this.orderModalError = '';
      this.activeOrder = null;
      this.statusNote = '';

      try {
        const res = await apiRequest<OrderDetailPayload>(`/supplier/orders/${orderId}`);
        if (!res.success || !res.data?.order) {
          this.orderModalError = res.message ?? 'Unable to load order details.';
          return;
        }
        this.activeOrder = res.data.order;
      } catch {
        this.orderModalError = 'Network error while loading order details.';
      } finally {
        this.orderModalLoading = false;
      }
    },

    closeOrderModal(): void {
      this.orderModalOpen = false;
      this.activeOrder = null;
      this.orderModalError = '';
      this.statusNote = '';
    },

    allowedNextStatuses(status: OrderStatus | string): OrderStatus[] {
      return ALLOWED_TRANSITIONS[status as OrderStatus] ?? [];
    },

    async transitionOrder(nextStatus: OrderStatus): Promise<void> {
      if (!this.activeOrder || this.updatingStatus) {
        return;
      }

      if (nextStatus === 'declined') {
        const confirmed = window.confirm(
          `Decline order ${this.activeOrder.order_number}? Reserved stock will be restored and the customer will be notified.`
        );
        if (!confirmed) {
          return;
        }
      }

      this.updatingStatus = true;
      const toast = window.Alpine.store('toast') as ToastStore;

      try {
        const res = await apiRequest<OrderStatusUpdatePayload>(
          `/supplier/orders/${this.activeOrder.id}/status`,
          {
            method: 'PATCH',
            body: JSON.stringify({
              status: nextStatus,
              note: this.statusNote.trim(),
            }),
          }
        );

        if (!res.success || !res.data?.order) {
          toast.push(res.message ?? 'Unable to update order status.', 'error');
          return;
        }

        this.activeOrder = res.data.order;
        this.statusNote = '';
        toast.push(res.data.message, 'success');

        // Refresh company orders & summary cards in background
        await Promise.all([this.loadCompanyOrders(), this.loadCompanies()]);
        if (this.inventoryLoaded && nextStatus === 'declined') {
          await this.loadInventory();
        }
      } catch {
        toast.push('Network error while updating order status.', 'error');
      } finally {
        this.updatingStatus = false;
      }
    },

    async loadInventory(): Promise<void> {
      this.inventoryLoading = true;
      this.inventoryError = '';

      const params = new URLSearchParams();
      if (this.productCategoryFilter !== 'all') {
        params.set('category', this.productCategoryFilter);
      }
      if (this.productStatusFilter !== 'all') {
        params.set('status', this.productStatusFilter);
      }
      if (this.productSearch.trim() !== '') {
        params.set('search', this.productSearch.trim());
      }
      const qs = params.toString();
      const path = qs !== '' ? `/supplier/products?${qs}` : '/supplier/products';

      try {
        const res = await apiRequest<SupplierProductsPayload>(path);
        if (!res.success || !res.data) {
          this.inventoryError = res.message ?? 'Unable to load product inventory.';
          return;
        }

        this.products = res.data.products;
        this.categories = res.data.categories;
        this.units = res.data.units;
        this.inventorySummary = res.data.summary;
        this.inventoryLoaded = true;
      } catch {
        this.inventoryError = 'Network error while loading inventory.';
      } finally {
        this.inventoryLoading = false;
      }
    },

    openCreateProductModal(): void {
      const form = createEmptyProductForm();
      const firstCategory = this.categories[0];
      if (firstCategory) {
        form.category_id = String(firstCategory.id);
      }
      const firstUnit = this.units[0];
      if (firstUnit) {
        form.unit_id = String(firstUnit.id);
      }
      this.productForm = form;
      this.productFormErrors = {};
      this.productModalOpen = true;
    },

    openEditProductModal(product: Product): void {
      this.productForm = {
        id: product.id,
        description: product.description,
        category_id: String(product.category_id),
        unit_id: String(product.unit_id),
        sku: product.sku,
        unit_price: product.unit_price.toFixed(2),
        stock_quantity: String(product.stock_quantity),
        image_path: product.image_path ?? '',
        is_active: product.is_active,
      };
      this.productFormErrors = {};
      this.productModalOpen = true;
    },

    closeProductModal(): void {
      this.productModalOpen = false;
      this.productFormErrors = {};
    },

    async saveProduct(): Promise<void> {
      if (this.savingProduct) {
        return;
      }

      this.savingProduct = true;
      this.productFormErrors = {};
      const toast = window.Alpine.store('toast') as ToastStore;

      const isEdit = this.productForm.id !== null;
      const endpoint = isEdit
        ? `/supplier/products/${this.productForm.id}`
        : '/supplier/products';
      const method = isEdit ? 'PATCH' : 'POST';

      const payload: Record<string, unknown> = {
        description: this.productForm.description.trim(),
        category_id: Number(this.productForm.category_id),
        unit_id: Number(this.productForm.unit_id),
        unit_price: Number(this.productForm.unit_price),
        stock_quantity: Number(this.productForm.stock_quantity),
        image_path: this.productForm.image_path.trim(),
        is_active: this.productForm.is_active,
      };
      if (this.productForm.sku.trim() !== '') {
        payload.sku = this.productForm.sku.trim();
      }

      try {
        const res = await apiRequest<ProductMutationPayload>(endpoint, {
          method,
          body: JSON.stringify(payload),
        });

        if (!res.success) {
          this.productFormErrors = res.errors ?? {};
          toast.push(res.message ?? 'Could not save product.', 'error');
          return;
        }

        toast.push(res.data?.message ?? 'Product saved.', 'success');
        this.productModalOpen = false;
        await this.loadInventory();
      } catch {
        toast.push('Network error while saving product.', 'error');
      } finally {
        this.savingProduct = false;
      }
    },

    async toggleProductActive(product: Product): Promise<void> {
      const toast = window.Alpine.store('toast') as ToastStore;
      try {
        const res = await apiRequest<ProductMutationPayload>(`/supplier/products/${product.id}`, {
          method: 'PATCH',
          body: JSON.stringify({ is_active: !product.is_active }),
        });

        if (!res.success || !res.data?.product) {
          toast.push(res.message ?? 'Could not update product status.', 'error');
          return;
        }

        toast.push(
          `${res.data.product.description} is now ${res.data.product.is_active ? 'active' : 'inactive'}.`,
          'success'
        );
        await this.loadInventory();
      } catch {
        toast.push('Network error while updating product visibility.', 'error');
      }
    },

    async deleteProduct(product: Product): Promise<void> {
      const confirmed = window.confirm(
        `Delete "${product.description}"? If it appears on past invoices, it will be deactivated instead of permanently removed.`
      );
      if (!confirmed) {
        return;
      }

      this.deletingProductId = product.id;
      const toast = window.Alpine.store('toast') as ToastStore;

      try {
        const res = await apiRequest<ProductDeletePayload>(`/supplier/products/${product.id}`, {
          method: 'DELETE',
        });

        if (!res.success || !res.data) {
          toast.push(res.message ?? 'Could not delete product.', 'error');
          return;
        }

        toast.push(res.data.message, res.data.action === 'deleted' ? 'success' : 'info');
        await this.loadInventory();
      } catch {
        toast.push('Network error while deleting product.', 'error');
      } finally {
        this.deletingProductId = null;
      }
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

    productImageUrl(imagePath: string | null): string {
      if (!imagePath) {
        return '';
      }
      if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
        return imagePath;
      }
      return `${getBasePath()}/${imagePath.replace(/^\/+/, '')}`;
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
          return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        case 'declined':
          return 'bg-red-100 text-red-800 border-red-200';
        case 'in_progress':
          return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'received':
          return 'bg-amber-100 text-amber-800 border-amber-200';
        default:
          return 'bg-stone-100 text-stone-700 border-stone-200';
      }
    },

    transitionButtonClass(status: OrderStatus): string {
      switch (status) {
        case 'delivered':
          return 'bg-emerald-700 hover:bg-emerald-600 text-white';
        case 'declined':
          return 'bg-red-600 hover:bg-red-500 text-white';
        case 'in_progress':
          return 'bg-blue-700 hover:bg-blue-600 text-white';
        default:
          return 'bg-amber-600 hover:bg-amber-500 text-white';
      }
    },
  };
}

document.addEventListener('alpine:init', () => {
  window.Alpine.data('supplierDashboardPage', (tab?: unknown) =>
    supplierDashboardPage(tab === 'inventory' ? 'inventory' : 'companies')
  );
});
