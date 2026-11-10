export interface FinanceDateFilter {
  date_from?: string;
  date_to?: string;
}

export interface SupplierPaymentPayload {
  payment_number: string;
  supplier_id: number;
  purchase_order_id?: number | null;
  payment_date: string;
  amount: number;
  payment_method: string;
  reference_number?: string | null;
  notes?: string | null;
}

export interface CustomerPaymentPayload {
  payment_number: string;
  customer_id: number;
  distribution_order_id?: number | null;
  payment_date: string;
  amount: number;
  payment_method: string;
  reference_number?: string | null;
  notes?: string | null;
}

export interface ExpensePayload {
  expense_number: string;
  expense_date: string;
  category: string;
  description: string;
  amount: number;
  payment_method: string;
  reference_number?: string | null;
  notes?: string | null;
}

export interface OutstandingResponse {
  total_amount: number;
  paid_amount: number;
  outstanding_amount: number;
}

export interface CreateFinanceResponse {
  id: number;
  message?: string;
}
