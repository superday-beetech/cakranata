import api from "@/api/client";

import type {
  CreateManualPaymentPayload,
  PaymentMethodRecord,
  SalePayment,
  VerifyManualPaymentPayload,
} from "../types/payment";

const BASE_URL = "/pharmacy/payments-api";

interface ApiResponse<T> {
  status: boolean;
  error: boolean;
  message: string;
  data: T;
  csrf_token?: string;
}

export const paymentService = {
  async getMethods(): Promise<PaymentMethodRecord[]> {
    const response = await api.get<ApiResponse<PaymentMethodRecord[]>>(
      `${BASE_URL}/methods`,
    );

    return response.data.data;
  },

  async getPayment(id: number): Promise<SalePayment> {
    const response = await api.get<ApiResponse<SalePayment>>(
      `${BASE_URL}/show`,
      { params: { id } },
    );

    return response.data.data;
  },

  async createManualPayment(
    payload: CreateManualPaymentPayload,
  ): Promise<{ id: number; payment: SalePayment }> {
    const response = await api.post<
      ApiResponse<{ id: number; payment: SalePayment }>
    >(`${BASE_URL}/manual`, payload);

    return response.data.data;
  },

  async verifyManualPayment(
    payload: VerifyManualPaymentPayload,
  ): Promise<SalePayment> {
    const response = await api.post<ApiResponse<SalePayment>>(
      `${BASE_URL}/verify`,
      payload,
    );

    return response.data.data;
  },
};

export default paymentService;
