
import api from "@/api/client";

import type {
  CreateSalePayload,
  PostSalePayload,
  Sale,
  SalesListResponse,
} from "../types/sales";
import type { ApiDataResponse } from "@/types/types";

const BASE_URL = "/pharmacy/sales-api";

export interface SalesFilters {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
}

export const salesService = {
  async getSales(
    filters: SalesFilters = {},
  ): Promise<SalesListResponse> {
    const response = await api.get<ApiDataResponse<SalesListResponse>>(
      BASE_URL,
      { params: filters },
    );

    return response.data.data;
  },

  async getSale(id: number): Promise<Sale> {
    const response = await api.get<ApiDataResponse<Sale>>(
      `${BASE_URL}/${id}`,
    );

    return response.data.data;
  },

  async createSale(payload: CreateSalePayload): Promise<Sale> {
    const response = await api.post<ApiDataResponse<Sale>>(
      BASE_URL,
      payload,
    );

    return response.data.data;
  },

  async postSale(
    id: number,
    payload: PostSalePayload,
  ): Promise<Sale> {
    const response = await api.post<ApiDataResponse<Sale>>(
      `${BASE_URL}/${id}/post`,
      payload,
    );

    return response.data.data;
  },

  async holdSale(id: number): Promise<Sale> {
    const response = await api.post<ApiDataResponse<Sale>>(
        `${BASE_URL}/${id}/hold`,
    );

    return response.data.data;
},

async resumeSale(id: number): Promise<Sale> {
    const response = await api.post<ApiDataResponse<Sale>>(
        `${BASE_URL}/${id}/resume`,
    );

    return response.data.data;
},
};

export default salesService;
