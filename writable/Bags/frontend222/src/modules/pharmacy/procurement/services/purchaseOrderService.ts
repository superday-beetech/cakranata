import api from "@/api/client";

import type {
    ApiResponse,
    CreatePurchaseOrderPayload,
    PurchaseOrder,
    PurchaseOrderListParams,
    PurchaseOrderListResult,
} from "../types/purchaseOrder";

const BASE_URL = "/pharmacy/procurement-api";

export const purchaseOrderService = {
    async getAll(
        params: PurchaseOrderListParams = {},
    ): Promise<ApiResponse<PurchaseOrderListResult>> {
        const response = await api.get<ApiResponse<PurchaseOrderListResult>>(
            `${BASE_URL}/purchaseOrders`,
            { params },
        );

        return response.data;
    },

    async getById(
        id: number,
    ): Promise<ApiResponse<PurchaseOrder>> {
        const response = await api.get<ApiResponse<PurchaseOrder>>(
            `${BASE_URL}/purchaseOrderDetail/${id}`,
        );

        return response.data;
    },

    async create(
        payload: CreatePurchaseOrderPayload,
    ): Promise<ApiResponse<PurchaseOrder>> {
        const response = await api.post<ApiResponse<PurchaseOrder>>(
            `${BASE_URL}/createPurchaseOrder`,
            payload,
        );

        return response.data;
    },

    async update(
        id: number,
        payload: CreatePurchaseOrderPayload,
    ): Promise<ApiResponse<PurchaseOrder>> {
        const response = await api.put<ApiResponse<PurchaseOrder>>(
            `${BASE_URL}/updatePurchaseOrder/${id}`,
            payload,
        );

        return response.data;
    },

    async delete(id: number): Promise<ApiResponse<null>> {
        const response = await api.delete<ApiResponse<null>>(
            `${BASE_URL}/deletePurchaseOrder/${id}`,
        );

        return response.data;
    },
};

export default purchaseOrderService;
