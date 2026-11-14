import api from "@/api/client";
import type { ApiResponse } from "@/types/types";
import type { CreateSalePayload, PostSalePayload, Sale, SalesListResponse } from "../types/sales";

const BASE_URL = "/pharmacy/sales-api";

export interface SalesFilters {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
}

function requireData<T>(data: T | null | undefined, message: string): T {
  if (data == null) {
    throw new Error(message);
  }
  return data;
}

function generateIdempotencyKey(): string {
  return typeof crypto !== "undefined" && crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).substring(2);
}

export const salesService = {
  async getSales(filters: SalesFilters = {}): Promise<SalesListResponse> {
    const response = await api.get<ApiResponse<SalesListResponse>>(BASE_URL, { params: filters });
    return requireData(response.data.data, "Response daftar penjualan tidak memiliki data.");
  },

  async getSale(id: number): Promise<Sale> {
    // Sesuaikan dengan route BE Anda (contoh: /show/{id} atau /{id})
    const response = await api.get<ApiResponse<Sale>>(`${BASE_URL}/show/${id}`);
    return requireData(response.data.data, "Response detail penjualan tidak memiliki data.");
  },

  async createSale(payload: CreateSalePayload): Promise<Sale> {
    // Sesuaikan dengan route BE Anda (contoh: /create atau tanpa /create)
    const response = await api.post<ApiResponse<Sale>>(`${BASE_URL}/create`, payload);
    return requireData(response.data.data, "Response pembuatan penjualan tidak memiliki data.");
  },

  async checkout(payload: CreateSalePayload, payment: PostSalePayload): Promise<Sale> {
    const response = await api.post<ApiResponse<Sale>>(
      `${BASE_URL}/checkout`,
      { ...payload, payment },
      { headers: { "Idempotency-Key": generateIdempotencyKey() } }, // ✅ Idempotency
    );
    return requireData(response.data.data, "Response checkout penjualan tidak memiliki data.");
  },

  async postSale(id: number, payload: PostSalePayload): Promise<Sale> {
    // ✅ console.log DIHAPUS
    const response = await api.post<ApiResponse<Sale>>(
      `${BASE_URL}/post/${id}`,
      payload,
      { headers: { "Idempotency-Key": generateIdempotencyKey() } }, // ✅ Idempotency
    );
    return requireData(response.data.data, "Response proses penjualan tidak memiliki data.");
  },

  async holdSale(id: number): Promise<Sale> {
    const response = await api.post<ApiResponse<Sale>>(`${BASE_URL}/hold/${id}`);
    return requireData(response.data.data, "Response penahanan penjualan tidak memiliki data.");
  },

  async resumeSale(id: number): Promise<Sale> {
    const response = await api.post<ApiResponse<Sale>>(`${BASE_URL}/resume/${id}`);
    return requireData(response.data.data, "Response melanjutkan penjualan tidak memiliki data.");
  },

  async cancelSale(id: number, options?: { restoreHeld?: boolean }): Promise<Sale> {
    const response = await api.post<ApiResponse<Sale>>(`${BASE_URL}/${id}/cancel`, {}, { params: { restore_held: options?.restoreHeld ? 1 : 0 } });
    return requireData(response.data.data, "Response pembatalan penjualan tidak memiliki data.");
  },
};
