import api from "@/api/client";

import type { CreateSalePayload, PostSalePayload, Sale, SalesListResponse } from "../types/sales";

const BASE_URL = "/pharmacy/sales-api";

interface ApiResponse<T> {
  status: boolean;
  error: boolean;
  message: string;
  data: T;
  csrf_token?: string;
}

export interface SalesFilters {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
}

export const salesService = {
  async getSales(filters: SalesFilters = {}): Promise<SalesListResponse> {
    const response = await api.get<ApiResponse<SalesListResponse>>(BASE_URL, { params: filters });

    return response.data.data;
  },

  async getSale(id: number): Promise<Sale> {
    const response = await api.get<ApiResponse<Sale>>(`${BASE_URL}/${id}`);

    return response.data.data;
  },

  async createSale(payload: CreateSalePayload): Promise<Sale> {
    const response = await api.post<ApiResponse<Sale>>(`${BASE_URL}/create`, payload);

    return response.data.data;
  },

  async postSale(id: number, payload: PostSalePayload): Promise<Sale> {
    const response = await api.post<ApiResponse<Sale>>(`${BASE_URL}/post/${id}`, payload);

    return response.data.data;
  },

  async holdSale(id: number): Promise<Sale> {
    const response = await api.post<ApiResponse<Sale>>(`${BASE_URL}/hold/${id}`);

    return response.data.data;
  },

  async resumeSale(id: number): Promise<Sale> {
    const response = await api.post<ApiResponse<Sale>>(`${BASE_URL}/resume/${id}`);

    return response.data.data;
  },
};

export default salesService;
