import { apiRequest, formatCurrency, getBasePath } from '../shared/api.js';
import type { AuthStore } from '../shared/authStore.js';
import type { CartStore } from '../shared/cartStore.js';
import type { ToastStore } from '../shared/toast.js';
import type { Category, Order, Product } from '../types/index.js';

interface CategoriesPayload {
  categories: Category[];
}

interface ProductsPayload {
  products: Product[];
  count: number;
}

interface PlaceOrderPayload {
  message: string;
  order: Order;
  emails?: {
    customer_sent: boolean;
    supplier_sent: boolean;
  };
}

export interface StorefrontComponent {
  categories: Category[];
  products: Product[];
  loading: boolean;
  loadError: string;
  search: string;
  selectedCategory: string;
  orderNotes: string;
  submittingOrder: boolean;
  checkoutError: string;
  lastOrder: Order | null;
  brokenImages: Record<number, boolean>;
  init(): Promise<void>;
  loadCatalog(): Promise<void>;
  selectCategory(slug: string): void;
  get filteredProducts(): Product[];
  get totalActiveCount(): number;
  imageUrl(product: Product): string;
  markImageBroken(productId: number): void;
  addOrIncrement(product: Product): void;
  incrementCartItem(productId: number): void;
  decrementProduct(productId: number): void;
  productStock(productId: number): number | undefined;
  formatPrice(amount: number): string;
  submitOrder(): Promise<void>;
  dismissConfirmation(): void;
  loginUrl(): string;
}

export function storefrontPage(): StorefrontComponent {
  return {
    categories: [],
    products: [],
    loading: true,
    loadError: '',
    search: '',
    selectedCategory: 'all',
    orderNotes: '',
    submittingOrder: false,
    checkoutError: '',
    lastOrder: null,
    brokenImages: {},

    async init(): Promise<void> {
      await this.loadCatalog();
    },

    async loadCatalog(): Promise<void> {
      this.loading = true;
      this.loadError = '';

      try {
        const [catRes, prodRes] = await Promise.all([
          apiRequest<CategoriesPayload>('/categories'),
          apiRequest<ProductsPayload>('/products'),
        ]);

        if (catRes.success && catRes.data) {
          this.categories = catRes.data.categories;
        }
        if (prodRes.success && prodRes.data) {
          this.products = prodRes.data.products;
        } else {
          this.loadError = prodRes.message ?? 'Unable to load catalog.';
        }
      } catch {
        this.loadError = 'Network error while loading fresh produce catalog.';
      } finally {
        this.loading = false;
      }
    },

    selectCategory(slug: string): void {
      this.selectedCategory = slug;
    },

    get filteredProducts(): Product[] {
      const q = this.search.trim().toLowerCase();
      return this.products.filter((p) => {
        if (this.selectedCategory !== 'all' && p.category_slug !== this.selectedCategory) {
          return false;
        }
        if (q !== '') {
          const hay = `${p.description} ${p.sku} ${p.category_name ?? ''}`.toLowerCase();
          return hay.includes(q);
        }
        return true;
      });
    },

    get totalActiveCount(): number {
      return this.products.length;
    },

    imageUrl(product: Product): string {
      if (!product.image_path) {
        return '';
      }
      const base = getBasePath();
      const rel = product.image_path.replace(/^\/+/, '');
      return `${base}/${rel}`;
    },

    markImageBroken(productId: number): void {
      this.brokenImages[productId] = true;
    },

    addOrIncrement(product: Product): void {
      const cart = window.Alpine.store('cart') as CartStore;
      const toast = window.Alpine.store('toast') as ToastStore;

      if (product.stock_quantity <= 0) {
        toast.push(`${product.description} is currently out of stock.`, 'error');
        return;
      }

      const added = cart.increment(product);
      if (!added) {
        toast.push(
          `Maximum available stock reached for ${product.description} (${product.stock_quantity} ${product.unit_name ?? 'Unit'}).`,
          'error'
        );
      }
    },

    incrementCartItem(productId: number): void {
      const cart = window.Alpine.store('cart') as CartStore;
      const toast = window.Alpine.store('toast') as ToastStore;
      const maxStock = this.productStock(productId);
      const ok = cart.incrementById(productId, maxStock);
      if (!ok) {
        toast.push('Maximum available stock reached for this item.', 'error');
      }
    },

    decrementProduct(productId: number): void {
      const cart = window.Alpine.store('cart') as CartStore;
      cart.decrement(productId);
    },

    productStock(productId: number): number | undefined {
      const found = this.products.find((p) => p.id === productId);
      return found ? found.stock_quantity : undefined;
    },

    formatPrice(amount: number): string {
      return formatCurrency(amount);
    },

    async submitOrder(): Promise<void> {
      const auth = window.Alpine.store('auth') as AuthStore;
      const cart = window.Alpine.store('cart') as CartStore;
      const toast = window.Alpine.store('toast') as ToastStore;

      this.checkoutError = '';

      if (!auth.user) {
        this.checkoutError = 'Please sign in as a customer or company account to place an order.';
        return;
      }

      if (auth.user.role === 'supplier') {
        this.checkoutError = 'Supplier accounts cannot place storefront orders.';
        return;
      }

      if (cart.items.length === 0) {
        this.checkoutError = 'Your cart is empty.';
        return;
      }

      this.submittingOrder = true;
      try {
        const res = await apiRequest<PlaceOrderPayload>('/orders', {
          method: 'POST',
          body: JSON.stringify({
            items: cart.toOrderItems(),
            notes: this.orderNotes.trim() || null,
          }),
        });

        if (!res.success || !res.data?.order) {
          this.checkoutError = res.message ?? 'Could not place order. Please review your cart.';
          toast.push(this.checkoutError, 'error');
          return;
        }

        this.lastOrder = res.data.order;
        this.orderNotes = '';
        cart.clear();
        cart.close();
        toast.push(`Order ${res.data.order.order_number} placed!`, 'success');

        // Refresh catalog stock counts in background
        await this.loadCatalog();
      } catch {
        this.checkoutError = 'Network error while submitting your order.';
        toast.push(this.checkoutError, 'error');
      } finally {
        this.submittingOrder = false;
      }
    },

    dismissConfirmation(): void {
      this.lastOrder = null;
    },

    loginUrl(): string {
      return `${getBasePath()}/login`;
    },
  };
}

document.addEventListener('alpine:init', () => {
  window.Alpine.data('storefrontPage', storefrontPage);
});
