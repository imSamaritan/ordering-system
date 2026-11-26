import type { CartItem, Product } from '../types/index.js';

const STORAGE_KEY = 'ordering_system_cart_v1';

export interface CartOrderItemPayload {
  product_id: number;
  quantity: number;
}

export interface CartStore {
  items: CartItem[];
  isOpen: boolean;
  init(): void;
  persist(): void;
  open(): void;
  close(): void;
  toggle(): void;
  increment(product: Product): boolean;
  incrementById(productId: number, maxStock?: number): boolean;
  decrement(productId: number): void;
  remove(productId: number): void;
  clear(): void;
  quantityOf(productId: number): number;
  formatQuantity(quantity: number, allowDecimal: boolean): string;
  lineTotal(item: CartItem): number;
  toOrderItems(): CartOrderItemPayload[];
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
            this.items = (parsed as CartItem[]).filter(
              (item) =>
                item &&
                typeof item.productId === 'number' &&
                typeof item.quantityTenths === 'number' &&
                item.quantityTenths > 0
            );
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

    open(): void {
      this.isOpen = true;
    },

    close(): void {
      this.isOpen = false;
    },

    toggle(): void {
      this.isOpen = !this.isOpen;
    },

    increment(product: Product): boolean {
      const stepTenths = product.allow_decimal ? 1 : 10;
      const maxTenths = Math.round(product.stock_quantity * 10);
      const existing = this.items.find((i) => i.productId === product.id);
      const nextTenths = (existing?.quantityTenths ?? 0) + stepTenths;

      if (nextTenths > maxTenths) {
        return false;
      }

      if (existing) {
        existing.quantityTenths = nextTenths;
        existing.unitPrice = product.unit_price;
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
      return true;
    },

    incrementById(productId: number, maxStock?: number): boolean {
      const existing = this.items.find((i) => i.productId === productId);
      if (!existing) {
        return false;
      }
      const stepTenths = existing.allowDecimal ? 1 : 10;
      const nextTenths = existing.quantityTenths + stepTenths;
      if (maxStock !== undefined && nextTenths > Math.round(maxStock * 10)) {
        return false;
      }
      existing.quantityTenths = nextTenths;
      this.persist();
      return true;
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

    formatQuantity(quantity: number, allowDecimal: boolean): string {
      return allowDecimal ? quantity.toFixed(1) : String(Math.round(quantity));
    },

    lineTotal(item: CartItem): number {
      return Math.round(item.unitPrice * item.quantityTenths * 10) / 100;
    },

    toOrderItems(): CartOrderItemPayload[] {
      return this.items.map((item) => ({
        product_id: item.productId,
        quantity: item.quantityTenths / 10,
      }));
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
