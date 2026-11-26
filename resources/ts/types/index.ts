export type UserRole = 'customer' | 'company' | 'supplier';

export type OrderStatus =
  | 'pending'
  | 'received'
  | 'in_progress'
  | 'delivered'
  | 'declined';

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
  items?: OrderItem[];
}

export interface ApiResponse<T> {
  success: boolean;
  data?: T;
  message?: string;
  errors?: Record<string, string>;
}
