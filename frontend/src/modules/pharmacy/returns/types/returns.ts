export interface PurchaseReturnItem {
  goods_receipt_item_id: number;
  batch_id: number;
  quantity: number;
  notes?: string;
}

export interface CreatePurchaseReturnPayload {
  return_number: string;
  goods_receipt_id: number;
  return_date: string;
  reason?: string;
  notes?: string;
  items: PurchaseReturnItem[];
}

export interface SalesReturnItem {
  delivery_item_id: number;
  quantity: number;
  restockable: boolean;
  condition_notes?: string;
}

export interface CreateSalesReturnPayload {
  return_number: string;
  delivery_id: number;
  return_date: string;
  reason?: string;
  notes?: string;
  items: SalesReturnItem[];
}
