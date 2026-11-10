import api from "@/api/client";

import type {
    ApiResponse,
    InventoryListParams,
    PaginatedData,
    ProductBatch,
    StockMovement,
    StockOpname,
    StockOpnameDetail,
    StockOverview,
    CreateStockOpnamePayload,
    StockOpnameResult,
    SaveStockOpnameCountsPayload,
} from "../types/inventory";

const BASE_URL = "/pharmacy/inventory-api";

export const inventoryService = {
    async getStockOverview(
        params: InventoryListParams = {}
    ): Promise<PaginatedData<StockOverview>> {
        const response = await api.get<ApiResponse<PaginatedData<StockOverview>>>(
            `${BASE_URL}/stock-overview`,
            { params }
        );

        return response.data.data;
    },

    async getBatches(
        params: InventoryListParams = {}
    ): Promise<PaginatedData<ProductBatch>> {
        const response = await api.get<ApiResponse<PaginatedData<ProductBatch>>>(
            `${BASE_URL}/batches`,
            { params }
        );

        return response.data.data;
    },

    async getBatchDetail(id: number): Promise<ProductBatch> {
        const response = await api.get<ApiResponse<ProductBatch>>(
            `${BASE_URL}/batch/${id}`
        );

        return response.data.data;
    },

    async getMovements(
        params: InventoryListParams = {}
    ): Promise<PaginatedData<StockMovement>> {
        const response = await api.get<ApiResponse<PaginatedData<StockMovement>>>(
            `${BASE_URL}/movements`,
            { params }
        );

        return response.data.data;
    },

    async getFefo(
        productId: number,
        quantity: number
    ): Promise<unknown[]> {
        const response = await api.get<ApiResponse<unknown[]>>(
            `${BASE_URL}/fefo/${productId}`,
            {
                params: { quantity },
            }
        );

        return response.data.data;
    },

    async getOpnames(
        params: InventoryListParams = {}
    ): Promise<PaginatedData<StockOpname>> {
        const response = await api.get<ApiResponse<PaginatedData<StockOpname>>>(
            `${BASE_URL}/stock-opnames`,
            { params }
        );

        return response.data.data;
    },

    async getOpnameDetail(id: number): Promise<StockOpnameDetail> {
        const response = await api.get<ApiResponse<StockOpnameDetail>>(
            `${BASE_URL}/opname/${id}`
        );

        return response.data.data;
    },

async createStockOpname(
  payload: CreateStockOpnamePayload
): Promise<StockOpnameResult> {
  const response = await api.post<ApiResponse<StockOpnameResult>>(
    `${BASE_URL}/create-stock-opname`,
    payload
  );

  return response.data.data;
},

async saveStockOpnameCounts(
  id: number,
  payload: SaveStockOpnameCountsPayload
): Promise<unknown> {
  const response = await api.put<ApiResponse<unknown>>(
    `${BASE_URL}/save-stock-opname/${id}`,
    payload
  );

  return response.data.data;
},

async postStockOpname(id: number): Promise<unknown> {
  const response = await api.post<ApiResponse<unknown>>(
    `${BASE_URL}/post-stock-opname/${id}`
  );

  return response.data.data;
},

};
