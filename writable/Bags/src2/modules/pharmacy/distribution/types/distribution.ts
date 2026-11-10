
export type NumericValue = number | string;

export type DistributionOrderStatus =
  | "draft"
  | "partially_delivered"
  | "completed"
  | "cancelled";

export type DeliveryStatus = "draft" | "posted" | "cancelled";

export interface DistributionOrder {
  id: NumericValue;
  order_number: string;
  customer_id: NumericValue;
  customer_name?: string;
  order_date: string;
  requested_delivery_date: string | null;
  status: DistributionOrderStatus | string;
  subtotal: NumericValue;
  discount_amount: NumericValue;
  tax_amount: NumericValue;
  total_amount: NumericValue;
  notes: string | null;
  created_by: NumericValue;
  updated_by: NumericValue | null;
  created_at?: string;
  updated_at?: string;
}

export interface DistributionItem {
  id: NumericValue;
  distribution_order_id: NumericValue;
  product_id: NumericValue;
  product_name?: string;
  sku?: string;
  quantity: NumericValue;
  unit_price: NumericValue;
  discount_amount: NumericValue;
  subtotal: NumericValue;
  notes: string | null;
}

export interface Delivery {
  id: NumericValue;
  delivery_number: string;
  distribution_order_id: NumericValue;
  delivery_date: string;
  status: DeliveryStatus | string;
  recipient_name: string | null;
  recipient_phone: string | null;
  shipping_address: string | null;
  notes: string | null;
  created_by: NumericValue;
  updated_by: NumericValue | null;
  created_at?: string;
  updated_at?: string;
}

export interface DeliveryItem {
  id: NumericValue;
  delivery_id: NumericValue;
  distribution_item_id: NumericValue;
  batch_id: NumericValue;
  batch_number?: string;
  product_id?: NumericValue;
  product_name?: string;
  quantity_dispatched: NumericValue;
  unit_price: NumericValue;
}

export interface DistributionOrderDetail {
  order: DistributionOrder;
  items: DistributionItem[];
  deliveries: Delivery[];
}

export interface DistributionOrderPayload {
  order_number: string;
  customer_id: number;
  order_date: string;
  requested_delivery_date?: string | null;
  discount_amount?: number;
  tax_amount?: number;
  notes?: string | null;
  items: DistributionOrderItemPayload[];
}

export interface DistributionOrderItemPayload {
  product_id: number;
  quantity: number;
  unit_price: number;
  discount_amount?: number;
  notes?: string | null;
}

export interface CreateDeliveryPayload {
  delivery_number: string;
  distribution_order_id: number;
  delivery_date: string;
  recipient_name?: string | null;
  recipient_phone?: string | null;
  shipping_address?: string | null;
  notes?: string | null;
}

export interface DeliveryAllocation {
  batch_id: number;
  quantity: number;
}

export interface DeliveryAllocationPayload {
  [distributionItemId: number]: DeliveryAllocation[];
}

export interface PostDeliveryResult {
  delivery_id: number;
  status: DeliveryStatus;
  order_status: DistributionOrderStatus;
  total_dispatched: NumericValue;
}

export interface DistributionListResponse {
  items: DistributionOrder[];
  pagination?: {
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
    has_next: boolean;
    has_prev: boolean;
  };
}

export interface DistributionFilters {
  search?: string;
  status?: string;
  customer_id?: number | null;
}
