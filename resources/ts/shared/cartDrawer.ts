import { apiRequest, formatCurrency, getBasePath } from './api.js';
import type { AuthStore } from './authStore.js';
import type { CartStore } from './cartStore.js';
import type { ToastStore } from './toast.js';
import type { Order } from '../types/index.js';

interface PlaceOrderPayload {
  message: string;
  order: Order;
  emails?: {
    customer_sent: boolean;
    supplier_sent: boolean;
  };
}

export interface CartDrawerComponent {
  orderNotes: string;
  submittingOrder: boolean;
  checkoutError: string;
  brokenImages: Record<number, boolean>;
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
