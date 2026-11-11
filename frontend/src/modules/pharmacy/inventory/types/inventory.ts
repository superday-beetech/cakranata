export interface InventoryPagination {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
  has_next: boolean;
  has_prev: boolean;
}
export type NumericValue = number | string;

export interface PaginatedData<T> {
  items: T[];
  pagination: InventoryPagination;
}

export interface StockOverview {
  product_id: NumericValue;
  sku: string;
  barcode: string | null;
  product_name: string;
  available_stock: NumericValue;
  expired_stock: NumericValue;
  batch_count: NumericValue;
}

export interface ProductBatch {
  id: NumericValue;
  product_id: NumericValue;
  goods_receipt_item_id: NumericValue;
  batch_number: string;
  expired_at: string | null;
  received_quantity: NumericValue;
  quantity_remaining: NumericValue;
  unit_cost: NumericValue;
  status: string;
  sku: string;
  barcode: string | null;
  product_name: string;
  created_at?: string;
  updated_at?: string;
}

export interface StockMovement {
  id: NumericValue;
  product_id: NumericValue;
  batch_id: NumericValue | null;
  movement_date: string;
  movement_type: string;
  reference_type: string;
  reference_id: NumericValue;
  quantity_in: NumericValue;
  quantity_out: NumericValue;
  unit_cost: NumericValue;
  notes: string | null;
  created_by: NumericValue;
  sku: string;
  product_name: string;
  batch_number: string | null;
}

export interface StockOpname {
  id: NumericValue;
  opname_number: string;
  opname_date: string;
  status: string;
  notes: string | null;
  created_by: NumericValue;
  updated_by: NumericValue | null;
  created_at?: string;
  updated_at?: string;
}

export interface StockOpnameItem {
  id: NumericValue;
  stock_opname_id: NumericValue;
  product_id: NumericValue;
  batch_id: NumericValue | null;
  system_quantity: NumericValue;
  counted_quantity: NumericValue | null;
  difference_quantity: NumericValue | null;
  notes: string | null;
  sku?: string;
  product_name?: string;
  batch_number?: string | null;
  expired_at?: string | null;
}

export interface StockOpnameDetail {
  opname: StockOpname;
  items: StockOpnameItem[];
}

export interface InventoryListParams {
  page?: NumericValue;
  per_page?: NumericValue;
  search?: string;
  product_id?: NumericValue;
  status?: string;
  movement_type?: string;
  reference_type?: string;
  reference_id?: NumericValue;
  date_from?: string;
  date_to?: string;
}

export interface CreateStockOpnamePayload {
  opname_number: string;
  opname_date: string;
  notes?: string | null;
}

export interface StockOpnameCountItem {
  item_id: number;
  counted_quantity: number;
  notes?: string | null;
}

export interface SaveStockOpnameCountsPayload {
  items: StockOpnameCountItem[];
}

export interface StockOpnameResult {
  id: number;
}

export interface StockOpnameDetail {
  opname: StockOpname;
  items: StockOpnameItem[];
}
