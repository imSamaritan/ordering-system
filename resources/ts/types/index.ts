export type UserRole = 'customer' | 'company' | 'supplier';

export type BuiltinThemeMode = 'light' | 'dark' | 'system';

export type ThemeModeId = BuiltinThemeMode | (string & {});

export type ColorScheme = 'light' | 'dark';

export interface ThemeDefinition {
  id: ThemeModeId;
  label: string;
  shortLabel: string;
  description: string;
  colorScheme: ColorScheme | 'system';
  icon: 'sun' | 'moon' | 'monitor' | 'palette';
}

export type OrderStatus =
  | 'pending'
  | 'received'
  | 'in_progress'
  | 'delivered'
  | 'declined'
  | 'cancelled';

export interface User {
  id: number;
  role: UserRole;
  company_name: string | null;
  contact_name: string;
  email: string;
  phone: string | null;
  location: string | null;
  email_verified_at: string | null;
  is_active: boolean;
}

export interface Category {
  id: number;
  name: string;
  slug: string;
  sort_order: number;
  product_count?: number;
}

export interface Unit {
  id: number;
  name: string;
  allow_decimal: boolean;
  step_size: number;
}

export interface Product {
  id: number;
  category_id: number;
  category_name?: string;
  category_slug?: string;
  unit_id: number;
  unit_name?: string;
  allow_decimal?: boolean;
  step_size?: number;
  sku: string;
  slug: string;
  description: string;
  image_path: string | null;
  unit_price: number;
  stock_quantity: number;
  is_active: boolean;
}

export interface CartItem {
  productId: number;
  sku: string;
  description: string;
  unitName: string;
  allowDecimal: boolean;
  stepSize: number;
  unitPrice: number;
  /** Stored as integer tenths (e.g. 1.3 kg => 13, 2 Unit => 20) to avoid float drift */
  quantityTenths: number;
  imagePath: string | null;
  maxStock?: number;
}

export interface OrderItem {
  id: number;
  product_id: number;
  product_name_snapshot: string;
  unit_name_snapshot: string;
  unit_price_snapshot: number;
  quantity: number;
  line_total: number;
}

export interface OrderStatusHistoryEntry {
  id: number;
  old_status: OrderStatus | null;
  new_status: OrderStatus;
  note: string | null;
  changed_at: string;
  changed_by_name: string | null;
  changed_by_role: UserRole | null;
}

export interface OrderCustomerSummary {
  id: number;
  company_name: string | null;
  contact_name: string;
  email: string;
  phone: string | null;
  location: string | null;
}

export interface Order {
  id: number;
  order_number: string;
  user_id: number;
  status: OrderStatus;
  total_amount: number;
  notes: string | null;
  ordered_at: string;
  received_at: string | null;
  delivered_at: string | null;
  cancelled_at?: string | null;
  item_count?: number;
  items?: OrderItem[];
  status_history?: OrderStatusHistoryEntry[];
  customer?: OrderCustomerSummary;
}

export interface SupplierCompanyCard {
  id: number;
  role: UserRole;
  company_name: string | null;
  contact_name: string;
  display_name: string;
  email: string;
  phone: string | null;
  location: string | null;
  email_verified_at: string | null;
  is_active: boolean;
  total_orders: number;
  pending_orders: number;
  received_orders: number;
  in_progress_orders: number;
  delivered_orders: number;
  declined_orders: number;
  cancelled_orders?: number;
  total_spend: number;
  last_ordered_at: string | null;
}

export interface SupplierCompaniesSummary {
  total_companies: number;
  total_orders: number;
  pending_orders: number;
  in_progress_orders: number;
  delivered_orders: number;
  total_revenue: number;
}

export interface SupplierInventorySummary {
  total: number;
  active: number;
  inactive: number;
  low_stock: number;
}

export interface CustomerOrdersSummary {
  count: number;
  total_spend: number;
  delivered_count: number;
  delivered_spend: number;
  declined_count: number;
  declined_total: number;
  cancelled_count: number;
  cancelled_total: number;
  pending_count: number;
  received_count: number;
  in_progress_count: number;
}

export interface ReorderPreviewItem {
  product_id: number;
  sku: string;
  description: string;
  unit_name: string;
  allow_decimal: boolean;
  step_size: number;
  image_path: string | null;
  unit_price: number;
  previous_unit_price: number;
  price_changed: boolean;
  price_delta: number;
  stock_quantity: number;
  ordered_quantity: number;
  suggested_quantity: number;
  reason?: string;
}

export interface ReorderUnavailableItem {
  product_id: number;
  description: string;
  unit_name: string;
  ordered_quantity: number;
  previous_unit_price: number;
  reason: string;
}

export interface ReorderPreviewSummary {
  total_items: number;
  available_count: number;
  adjusted_count: number;
  unavailable_count: number;
  price_changed_count: number;
  can_reorder: boolean;
}

export interface ReorderPreviewPayload {
  order_id: number;
  order_number: string;
  available: ReorderPreviewItem[];
  adjusted: ReorderPreviewItem[];
  unavailable: ReorderUnavailableItem[];
  summary: ReorderPreviewSummary;
}

export interface ApiResponse<T> {
  success: boolean;
  data?: T;
  message?: string;
  errors?: Record<string, string>;
}
