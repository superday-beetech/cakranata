export type SaleStatus = "draft" | "held" | "posted" | "cancelled";
export type PaymentStatus = "unpaid" | "partial" | "paid";
export type PaymentMethod =
  | "cash"
  | "transfer"
  | "qris"
  | "debit"
  | "credit"
  | "other";
export type DiscountMode = "amount" | "percent";
export type TaxMode = "amount" | "percent";
export type SettlementMode = "paid" | "partial" | "receivable";
export type AdditionalChargeType =
  | "donation"
  | "shipping"
  | "packaging"
  | "service"
  | "other";

export type NumericValue = number | string;

export interface SaleItemInput {
  product_id: number;
  quantity: number;
  unit_price: number;
  discount_amount?: number;
}

export interface PosAdditionalCharge {
  id: string;
  type: AdditionalChargeType;
  label: string;
  description: string;
  amount: number;
}

export interface PosDiscountInput {
  mode: DiscountMode;
  value: number;
}

export interface PosTaxInput {
  mode: TaxMode;
  value: number;
}

export interface CreateSalePayload {
  customer_id?: number | null;
  sale_date?: string;
  tax_amount?: number;
  notes?: string | null;
  items: SaleItemInput[];
}

export interface PostSalePayload {
  amount: number;
  payment_method: PaymentMethod;
  reference_number?: string | null;
}

export interface SaleItem {
  id: number;
  sale_id: number;
  product_id: number;
  batch_id: number | null;
  product_name?: string;
  sku?: string;
  barcode?: string | null;
  batch_number?: string | null;
  expired_at?: string | null;

  quantity: NumericValue;
  unit_price: NumericValue;
  unit_cost: NumericValue;
  discount_amount: NumericValue;
  subtotal: NumericValue;
}

export interface Sale {
  id: number;
  sale_number: string;
  customer_id: number | null;
  customer_name?: string | null;

  sale_date: string;
  status: SaleStatus;

  subtotal: NumericValue;
  discount_amount: NumericValue;
  tax_amount: NumericValue;
  total_amount: NumericValue;
  paid_amount: NumericValue;

  payment_status: PaymentStatus;
  payment_method?: PaymentMethod | null;
  reference_number?: string | null;
  notes?: string | null;

  items?: SaleItem[];
  created_at?: string;
  updated_at?: string;
}

export interface SalesPagination {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface SalesListResponse {
  data: Sale[];
  pagination: SalesPagination;
}

export interface PosCartItem {
  product_id: number;
  sku: string;
  barcode: string | null;
  product_name: string;
  unit_name?: string;
  quantity: number;
  unit_price: number;

  // Nominal diskon hasil perhitungan.
  discount_amount: number;

  // Input diskon item.
  discount_mode?: DiscountMode;
  discount_value?: number;

  notes?: string | null;
  available_stock?: NumericValue;
}

export interface PosSummary {
  subtotal: number;
  itemDiscount: number;
  transactionDiscount: number;
  discount: number;
  dpp: number;
  tax: number;
  additionalCharges: number;
  total: number;
  paid: number;
  change: number;
  receivable: number;
}

export interface ProductSearchResult {
  id: number;
  sku: string;
  barcode: string | null;
  name: string;
  unit_name?: string;
  selling_price: NumericValue;
  available_stock?: NumericValue;
  is_active: boolean;
}
