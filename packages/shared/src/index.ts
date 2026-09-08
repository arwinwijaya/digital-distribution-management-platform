// Shared types for Digital Distribution Management Platform

// User types
export interface User {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  phone?: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export type UserRole = 'admin' | 'supplier' | 'outlet' | 'sales' | 'driver';

// Auth types
export interface LoginRequest {
  email: string;
  password: string;
}

export interface LoginResponse {
  status: 'success' | 'error';
  data: {
    token: string;
    token_type: string;
    expires_in: number;
    user: User;
  };
}

export interface AuthError {
  status: 'error';
  message: string;
}

// Product types
export interface Product {
  id: number;
  name: string;
  sku: string;
  description?: string;
  price: number;
  stock: number;
  is_available: boolean;
  supplier_id: number;
  category_id?: number;
  image_url?: string;
  created_at: string;
  updated_at: string;
}

// Order types
export interface Order {
  id: number;
  order_number: string;
  outlet_id: number;
  status: OrderStatus;
  total_amount: number;
  notes?: string;
  created_at: string;
  updated_at: string;
}

export type OrderStatus = 'new' | 'confirmed' | 'processing' | 'delivered' | 'paid' | 'cancelled';

export interface OrderItem {
  id: number;
  order_id: number;
  product_id: number;
  quantity: number;
  unit_price: number;
  total_price: number;
}

// API Response types
export interface ApiResponse<T> {
  status: 'success' | 'error';
  data: T;
  message?: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

// Dashboard types
export interface DashboardMetrics {
  total_outlets: number;
  active_outlets: number;
  total_orders: number;
  monthly_sales: number;
  top_products: Product[];
  recent_orders: Order[];
}
