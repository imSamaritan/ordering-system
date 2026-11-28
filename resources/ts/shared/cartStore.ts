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
  addBulkItems(items: CartItem[], mode: 'merge' | 'replace'): void;
  syncProductMetadata(products: Product[]): void;
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
        existing.maxStock = product.stock_quantity;
        existing.imagePath = product.image_path;
        existing.categoryId = product.category_id;
        existing.categorySlug = product.category_slug;
        existing.categoryName = product.category_name;
      } else {
        this.items.push({
          productId: product.id,
          categoryId: product.category_id,
          categorySlug: product.category_slug,
          categoryName: product.category_name,
          sku: product.sku,
          description: product.description,
          unitName: product.unit_name ?? 'Unit',
          allowDecimal: Boolean(product.allow_decimal),
          stepSize: product.step_size ?? (product.allow_decimal ? 0.1 : 1),
          unitPrice: product.unit_price,
          quantityTenths: stepTenths,
          imagePath: product.image_path,
          maxStock: product.stock_quantity,
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
      const resolvedMaxStock = maxStock ?? existing.maxStock;
      if (resolvedMaxStock !== undefined && nextTenths > Math.round(resolvedMaxStock * 10)) {
        return false;
      }
      existing.quantityTenths = nextTenths;
      this.persist();
      return true;
    },

    addBulkItems(items: CartItem[], mode: 'merge' | 'replace'): void {
      if (mode === 'replace') {
        this.items = [];
      }

      for (const incoming of items) {
        if (!incoming || incoming.quantityTenths <= 0) {
          continue;
        }

        const stepTenths = incoming.allowDecimal ? 1 : 10;
        let maxTenths: number | undefined;
        if (incoming.maxStock !== undefined) {
          const rawStockTenths = Math.floor(Math.round(incoming.maxStock * 10 * 100) / 100);
          maxTenths = incoming.allowDecimal
            ? rawStockTenths
            : Math.floor(rawStockTenths / 10) * 10;
        }

        const normalizedIncomingTenths = incoming.allowDecimal
          ? Math.max(1, Math.round(incoming.quantityTenths))
          : Math.max(10, Math.round(incoming.quantityTenths / 10) * 10);

        const existing = this.items.find((i) => i.productId === incoming.productId);
        if (existing) {
          let combinedTenths = existing.quantityTenths + normalizedIncomingTenths;
          if (maxTenths !== undefined) {
            combinedTenths = Math.min(combinedTenths, maxTenths);
          }
          if (combinedTenths >= stepTenths) {
            existing.quantityTenths = combinedTenths;
            existing.sku = incoming.sku;
            existing.description = incoming.description;
            existing.unitName = incoming.unitName;
            existing.allowDecimal = incoming.allowDecimal;
            existing.stepSize = incoming.stepSize;
            existing.unitPrice = incoming.unitPrice;
            existing.imagePath = incoming.imagePath;
            if (incoming.categoryId !== undefined) {
              existing.categoryId = incoming.categoryId;
            }
            if (incoming.categorySlug !== undefined) {
              existing.categorySlug = incoming.categorySlug;
            }
            if (incoming.categoryName !== undefined) {
              existing.categoryName = incoming.categoryName;
            }
            if (incoming.maxStock !== undefined) {
              existing.maxStock = incoming.maxStock;
            }
          }
        } else {
          let targetTenths = normalizedIncomingTenths;
          if (maxTenths !== undefined) {
            targetTenths = Math.min(targetTenths, maxTenths);
          }
          if (targetTenths >= stepTenths) {
            this.items.push({
              ...incoming,
              quantityTenths: targetTenths,
            });
          }
        }
      }

      this.persist();
    },

    syncProductMetadata(products: Product[]): void {
      if (this.items.length === 0 || products.length === 0) {
        return;
      }

      const productById = new Map<number, Product>();
      for (const product of products) {
        productById.set(product.id, product);
      }

      let changed = false;
      for (const item of this.items) {
        const product = productById.get(item.productId);
        if (!product) {
          continue;
        }
        if (item.categoryId !== product.category_id) {
          item.categoryId = product.category_id;
          changed = true;
        }
        if (product.category_slug && item.categorySlug !== product.category_slug) {
          item.categorySlug = product.category_slug;
          changed = true;
        }
        if (product.category_name && item.categoryName !== product.category_name) {
          item.categoryName = product.category_name;
          changed = true;
        }
      }

      if (changed) {
        this.persist();
      }
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
