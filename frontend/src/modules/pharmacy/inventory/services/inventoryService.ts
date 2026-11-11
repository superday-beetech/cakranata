import api from "@/api/client";
import type { ApiResponse } from "@/types/types";
import type { InventoryListParams, PaginatedData, ProductBatch, StockMovement, StockOpname, StockOpnameDetail, StockOverview, CreateStockOpnamePayload, StockOpnameResult, SaveStockOpnameCountsPayload } from "../types/inventory";

const BASE_URL = "/pharmacy/inventory-api";

function requireData<T>(data: T | null | undefined, message: string): T {
  if (data == null) {
    throw new Error(message);
  }

  return data;
}

export const inventoryService = {
  async getStockOverview(params: InventoryListParams = {}): Promise<PaginatedData<StockOverview>> {
    const response = await api.get<ApiResponse<PaginatedData<StockOverview>>>(`${BASE_URL}/stock-overview`, { params });

    return requireData(response.data.data, "Response Inventory tidak memiliki data.");
  },

  async getBatches(params: InventoryListParams = {}): Promise<PaginatedData<ProductBatch>> {
    const response = await api.get<ApiResponse<PaginatedData<ProductBatch>>>(`${BASE_URL}/batches`, { params });

    return requireData(response.data.data, "Response Inventory tidak memiliki data.");
  },

  async getBatchDetail(id: number): Promise<ProductBatch> {
    const response = await api.get<ApiResponse<ProductBatch>>(`${BASE_URL}/batch/${id}`);

    return requireData(response.data.data, "Response Detail Inventory tidak memiliki data.");
  },

  async getMovements(params: InventoryListParams = {}): Promise<PaginatedData<StockMovement>> {
    const response = await api.get<ApiResponse<PaginatedData<StockMovement>>>(`${BASE_URL}/movements`, { params });

    return requireData(response.data.data, "Response Stok tidak memiliki data.");
  },

  async getFefo(productId: number, quantity: number): Promise<unknown[]> {
    const response = await api.get<ApiResponse<unknown[]>>(`${BASE_URL}/fefo/${productId}`, {
      params: { quantity },
    });

    return requireData(response.data.data, "Response Tidak memiliki data.");
  },

  async getOpnames(params: InventoryListParams = {}): Promise<PaginatedData<StockOpname>> {
    const response = await api.get<ApiResponse<PaginatedData<StockOpname>>>(`${BASE_URL}/stock-opnames`, { params });

    return requireData(response.data.data, "Response Tidak memiliki data.");
  },

  async getOpnameDetail(id: number): Promise<StockOpnameDetail> {
    const response = await api.get<ApiResponse<StockOpnameDetail>>(`${BASE_URL}/opname/${id}`);

    return requireData(response.data.data, "Response Tidak memiliki data.");
  },

  async createStockOpname(payload: CreateStockOpnamePayload): Promise<StockOpnameResult> {
    const response = await api.post<ApiResponse<StockOpnameResult>>(`${BASE_URL}/create-stock-opname`, payload);

    return requireData(response.data.data, "Response Tidak memiliki data.");
  },

  async saveStockOpnameCounts(id: number, payload: SaveStockOpnameCountsPayload): Promise<unknown> {
    const response = await api.put<ApiResponse<unknown>>(`${BASE_URL}/save-stock-opname/${id}`, payload);

    return response.data.data;
  },

  async postStockOpname(id: number): Promise<unknown> {
    const response = await api.post<ApiResponse<unknown>>(`${BASE_URL}/post-stock-opname/${id}`);

    return response.data.data;
  },
};
