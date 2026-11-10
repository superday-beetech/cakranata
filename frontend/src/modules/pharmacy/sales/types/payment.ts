export type PaymentChannel =
  | "cash"
  | "bank_transfer"
  | "qris"
  | "ewallet"
  | "virtual_account"
  | "card"
  | "other";

export type PaymentProcessingType = "manual" | "gateway";

export type SalePaymentStatus =
  | "pending"
  | "pending_verification"
  | "paid"
  | "failed"
  | "expired"
  | "cancelled"
  | "refunded"
  | "partially_refunded";

export interface PaymentMethodRecord {
  id: number;
  code: PaymentChannel;
  name: string;
  channel: PaymentChannel;
  processing_type: PaymentProcessingType;
  requires_confirmation: boolean | number;
  provider_required: boolean | number;
  is_active: boolean | number;
  sort_order: number;
}

export interface SalePayment {
  id: number;
  payment_number: string;
  sale_id: number;
  payment_method_id: number;
  provider_id: number | null;
  amount: number | string;
  status: SalePaymentStatus;
  processing_type: PaymentProcessingType;
  reference_number: string | null;
  external_reference: string | null;
  method_code: PaymentChannel;
  method_name: string;
  provider_code: string | null;
  paid_at: string | null;
  expires_at: string | null;
  created_at: string | null;
}

export interface CreateManualPaymentPayload {
  sale_id: number;
  method: "cash" | "bank_transfer";
  amount: number;
  reference_number?: string | null;
  notes?: string | null;
  idempotency_key: string;
}

export interface VerifyManualPaymentPayload {
  payment_id: number;
  decision: "approve" | "reject";
  notes?: string | null;
}
