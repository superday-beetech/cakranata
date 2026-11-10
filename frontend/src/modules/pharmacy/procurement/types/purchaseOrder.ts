type Numeric = number | string;

export interface PurchaseOrderItem {
  id?: number;
  purchase_order_id?: number;
  product_id: number;
  sku?: string;
  product_name?: string;
  barcode?: string;
  quantity: Numeric;
  unit_cost: Numeric;
  discount_amount: Numeric;
  subtotal: Numeric;
  notes?: string | null;
}

export interface PurchaseOrder {
  items: any;
  id: number;
  order_number: string;
  supplier_id: number;
  supplier_name?: string;
  supplier_phone?: string;
  supplier_email?: string;
  supplier_address?: string;
  order_date: string;
  expected_date?: string | null;
  status: string;
  subtotal: Numeric;
  discount_amount: Numeric;
  tax_amount: Numeric;
  total_amount: Numeric;
  notes?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface PurchaseOrderDetail extends PurchaseOrder {
  items: PurchaseOrderItem[];
}

export interface PurchaseOrderPagination {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
  has_next: boolean;
  has_prev: boolean;
}

export interface PurchaseOrderListResult {
  items: PurchaseOrder[];
  pagination: PurchaseOrderPagination;
}

export interface PurchaseOrderListParams {
  search?: string;
  status?: string;
  supplier_id?: number;
  page?: number;
  per_page?: number;
}

export interface CreatePurchaseOrderPayload {
  order_number: string;
  supplier_id: number;
  order_date: string;
  expected_date?: string | null;
  discount_amount: number;
  tax_amount: number;
  notes?: string | null;
  items: Array<{
    product_id: number;
    quantity: number;
    unit_cost: number;
    discount_amount: number;
    notes?: string | null;
  }>;
}

export interface ApiResponse<T> {
  status: number;
  error: boolean;
  message: string;
  data: T;
}

export interface PurchaseOrderDetail extends PurchaseOrder {
  items: PurchaseOrderItem[];
}
