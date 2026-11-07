
export type GoodsReceiptStatus =
    | "draft"
    | "posted"
    | "cancelled";

export interface GoodsReceiptItem {
    id: number;
    goods_receipt_id: number;
    purchase_order_item_id: number;
    product_id: number;
    product_name?: string;
    sku?: string;
    barcode?: string;
    quantity_received: number;
    unit_cost: number;
    notes?: string | null;
    ordered_quantity?: number;
}

export interface GoodsReceipt {
    id: number;
    receipt_number: string;
    purchase_order_id: number;
    order_number?: string;
    supplier_name?: string;
    supplier_phone?: string;
    received_date: string;
    supplier_delivery_number?: string | null;
    status: GoodsReceiptStatus;
    notes?: string | null;
    items: GoodsReceiptItem[];
    created_by?: number;
    updated_by?: number;
    created_at?: string;
    updated_at?: string;
}

export interface GoodsReceiptPayload {
    receipt_number: string;
    purchase_order_id: number;
    received_date: string;
    supplier_delivery_number?: string | null;
    notes?: string | null;
    items: Array<{
        purchase_order_item_id: number;
        quantity_received: number;
        unit_cost: number;
        notes?: string | null;
    }>;
}

export interface GoodsReceiptListParams {
    page?: number;
    per_page?: number;
    search?: string;
    status?: GoodsReceiptStatus | "";
    purchase_order_id?: number;
}


export interface GoodsReceiptPagination {
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
    has_next: boolean;
    has_prev: boolean;
}

export interface GoodsReceiptListResult {
    items: GoodsReceipt[];
    pagination: GoodsReceiptPagination;
}

export interface ApiResponse<T> {
    status: boolean;
    message?: string;
    data: T;
    error?: string | null;
}
