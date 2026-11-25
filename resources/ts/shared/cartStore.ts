import type { CartItem, Product } from '../types/index.js';

const STORAGE_KEY = 'ordering_system_cart_v1';

export interface CartStore {
  items: CartItem[];
  isOpen: boolean;
  init(): void;
  persist(): void;
  increment(product: Product): void;
  decrement(productId: number): void;
  remove(productId: number): void;
  clear(): void;
  quantityOf(productId: number): number;
  lineTotal(item: CartItem): number;
  get totalItems(): number;
  get subtotal(): number;
}

export function createCartStore(): CartStore {
  return {
    items: [],
    isOpen: false,

    init(): void {
      try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        if (raw) {
          const parsed = JSON.parse(raw) as unknown;
          if (Array.isArray(parsed)) {
            this.items = parsed as CartItem[];
          }
        }
      } catch {
        this.items = [];
      }
    },

    persist(): void {
      try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(this.items));
      } catch {
        // Ignore storage quota errors
      }
    },

    increment(product: Product): void {
      const stepTenths = product.allow_decimal ? 1 : 10;
      const existing = this.items.find((i) => i.productId === product.id);
      if (existing) {
        existing.quantityTenths += stepTenths;
      } else {
        this.items.push({
          productId: product.id,
          sku: product.sku,
          description: product.description,
          unitName: product.unit_name ?? 'Unit',
          allowDecimal: Boolean(product.allow_decimal),
          stepSize: product.step_size ?? (product.allow_decimal ? 0.1 : 1),
          unitPrice: product.unit_price,
          quantityTenths: stepTenths,
          imagePath: product.image_path,
        });
      }
      this.persist();
    },

    decrement(productId: number): void {
      const idx = this.items.findIndex((i) => i.productId === productId);
      if (idx === -1) {
        return;
      }
      const item = this.items[idx];
      if (!item) {
        return;
      }
      const stepTenths = item.allowDecimal ? 1 : 10;
      item.quantityTenths -= stepTenths;
      if (item.quantityTenths <= 0) {
        this.items.splice(idx, 1);
      }
      this.persist();
    },

    remove(productId: number): void {
      this.items = this.items.filter((i) => i.productId !== productId);
      this.persist();
    },

    clear(): void {
      this.items = [];
      this.persist();
    },

    quantityOf(productId: number): number {
      const item = this.items.find((i) => i.productId === productId);
      return item ? item.quantityTenths / 10 : 0;
    },

    lineTotal(item: CartItem): number {
      return Math.round(item.unitPrice * item.quantityTenths * 10) / 100;
    },

    get totalItems(): number {
      return this.items.length;
    },

    get subtotal(): number {
      const cents = this.items.reduce(
        (sum, item) => sum + Math.round(item.unitPrice * item.quantityTenths * 10),
        0
      );
      return cents / 100;
    },
  };
}
