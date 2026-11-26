import { apiRequest, formatCurrency, getBasePath } from '../shared/api.js';
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

export interface StorefrontComponent {
  categories: Category[];
  products: Product[];
  loading: boolean;
  loadError: string;
  search: string;
  selectedCategory: string;
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
  decrementProduct(productId: number): void;
  formatPrice(amount: number): string;
  dismissConfirmation(): void;
}

export function storefrontPage(): StorefrontComponent {
  return {
    categories: [],
    products: [],
    loading: true,
    loadError: '',
    search: '',
    selectedCategory: 'all',
    lastOrder: null,
    brokenImages: {},

    async init(): Promise<void> {
      window.addEventListener('order:placed', (event: Event) => {
        const customEvent = event as CustomEvent<{ order?: Order }>;
        if (customEvent.detail?.order) {
          this.lastOrder = customEvent.detail.order;
        }
        void this.loadCatalog();
      });

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

    decrementProduct(productId: number): void {
      const cart = window.Alpine.store('cart') as CartStore;
      cart.decrement(productId);
    },

    formatPrice(amount: number): string {
      return formatCurrency(amount);
    },

    dismissConfirmation(): void {
      this.lastOrder = null;
    },
  };
}

document.addEventListener('alpine:init', () => {
  window.Alpine.data('storefrontPage', storefrontPage);
});
