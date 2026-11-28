import { apiRequest, formatCurrency, getBasePath } from './api.js';
import type { AuthStore } from './authStore.js';
import type { CartStore } from './cartStore.js';
import type { ToastStore } from './toast.js';
import type { CartItem, Order } from '../types/index.js';

interface PlaceOrderPayload {
  message: string;
  order: Order;
  emails?: {
    customer_sent: boolean;
    supplier_sent: boolean;
  };
}

export interface CartCategoryGroup {
  categoryId: number;
  slug: string;
  name: string;
  items: CartItem[];
  itemCount: number;
  subtotal: number;
}

export interface CartDrawerComponent {
  orderNotes: string;
  submittingOrder: boolean;
  checkoutError: string;
  brokenImages: Record<number, boolean>;
  collapsedCategories: Record<string, boolean>;
  get groupedItems(): CartCategoryGroup[];
  get allSectionsCollapsed(): boolean;
  get allSectionsExpanded(): boolean;
  isCategoryCollapsed(slug: string): boolean;
  toggleCategory(slug: string): void;
  collapseAllCategories(): void;
  expandAllCategories(): void;
  clearCart(): void;
  incrementCartItem(productId: number): void;
  productImageUrl(imagePath: string | null | undefined): string;
  markImageBroken(productId: number): void;
  formatPrice(amount: number): string;
  submitOrder(): Promise<void>;
  loginUrl(): string;
  storefrontUrl(): string;
}

export function cartDrawer(): CartDrawerComponent {
  return {
    orderNotes: '',
    submittingOrder: false,
    checkoutError: '',
    brokenImages: {},
    collapsedCategories: {},

    get groupedItems(): CartCategoryGroup[] {
      const cart = window.Alpine.store('cart') as CartStore;
      if (cart.items.length === 0) {
        return [];
      }

      const groupsMap = new Map<
        string,
        {
          categoryId: number;
          slug: string;
          name: string;
          items: CartItem[];
          subtotalCents: number;
        }
      >();

      for (const item of cart.items) {
        const rawSlug = (item.categorySlug ?? '').trim().toLowerCase();
        const slug = rawSlug !== '' ? rawSlug : 'uncategorized';
        const rawName = (item.categoryName ?? '').trim();
        const name = rawName !== '' ? rawName : 'Other Items';
        const categoryId =
          typeof item.categoryId === 'number' && item.categoryId > 0
            ? item.categoryId
            : Number.MAX_SAFE_INTEGER;
        const lineCents = Math.round(item.unitPrice * item.quantityTenths * 10);

        const existing = groupsMap.get(slug);
        if (existing) {
          existing.items.push(item);
          existing.subtotalCents += lineCents;
          if (categoryId < existing.categoryId) {
            existing.categoryId = categoryId;
          }
        } else {
          groupsMap.set(slug, {
            categoryId,
            slug,
            name,
            items: [item],
            subtotalCents: lineCents,
          });
        }
      }

      return Array.from(groupsMap.values())
        .sort((a, b) => a.categoryId - b.categoryId || a.name.localeCompare(b.name))
        .map((group) => ({
          categoryId: group.categoryId,
          slug: group.slug,
          name: group.name,
          items: group.items,
          itemCount: group.items.length,
          subtotal: group.subtotalCents / 100,
        }));
    },

    get allSectionsCollapsed(): boolean {
      const groups = this.groupedItems;
      return groups.length > 0 && groups.every((group) => Boolean(this.collapsedCategories[group.slug]));
    },

    get allSectionsExpanded(): boolean {
      const groups = this.groupedItems;
      return groups.length > 0 && groups.every((group) => !this.collapsedCategories[group.slug]);
    },

    isCategoryCollapsed(slug: string): boolean {
      return Boolean(this.collapsedCategories[slug]);
    },

    toggleCategory(slug: string): void {
      this.collapsedCategories = {
        ...this.collapsedCategories,
        [slug]: !this.collapsedCategories[slug],
      };
    },

    collapseAllCategories(): void {
      const next: Record<string, boolean> = {};
      for (const group of this.groupedItems) {
        next[group.slug] = true;
      }
      this.collapsedCategories = next;
    },

    expandAllCategories(): void {
      this.collapsedCategories = {};
    },

    clearCart(): void {
      const cart = window.Alpine.store('cart') as CartStore;
      cart.clear();
      this.collapsedCategories = {};
    },

    incrementCartItem(productId: number): void {
      const cart = window.Alpine.store('cart') as CartStore;
      const toast = window.Alpine.store('toast') as ToastStore;
      const ok = cart.incrementById(productId);
      if (!ok) {
        toast.push('Maximum available stock reached for this item.', 'error');
      }
    },

    productImageUrl(imagePath: string | null | undefined): string {
      if (!imagePath) {
        return '';
      }
      const trimmed = imagePath.trim();
      if (trimmed === '') {
        return '';
      }
      if (trimmed.startsWith('http://') || trimmed.startsWith('https://')) {
        return trimmed;
      }
      return `${getBasePath()}/${trimmed.replace(/^\/+/, '')}`;
    },

    markImageBroken(productId: number): void {
      this.brokenImages[productId] = true;
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

        const placedOrder = res.data.order;
        this.orderNotes = '';
        this.checkoutError = '';
        this.collapsedCategories = {};
        cart.clear();
        cart.close();
        toast.push(`Order ${placedOrder.order_number} placed!`, 'success');

        window.dispatchEvent(
          new CustomEvent<{ order: Order }>('order:placed', {
            detail: { order: placedOrder },
          })
        );
      } catch {
        this.checkoutError = 'Network error while submitting your order.';
        toast.push(this.checkoutError, 'error');
      } finally {
        this.submittingOrder = false;
      }
    },

    loginUrl(): string {
      return `${getBasePath()}/login`;
    },

    storefrontUrl(): string {
      return `${getBasePath()}/`;
    },
  };
}
