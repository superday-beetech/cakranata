import api from "@/api/client";
import type { ApiDataResponse } from "@/types/types";

import type {
    CreatePurchaseOrderPayload,
    PurchaseOrder,
    PurchaseOrderListParams,
    PurchaseOrderListResult,
} from "../types/purchaseOrder";

const BASE_URL = "/pharmacy/procurement-api";

export const purchaseOrderService = {
    async getAll(
        params: PurchaseOrderListParams = {},
    ): Promise<ApiDataResponse<PurchaseOrderListResult>> {
        const response = await api.get<ApiDataResponse<PurchaseOrderListResult>>(
            `${BASE_URL}/purchaseOrders`,
            { params },
        );

        return response.data;
    },

    async getById(
        id: number,
    ): Promise<ApiDataResponse<PurchaseOrder>> {
        const response = await api.get<ApiDataResponse<PurchaseOrder>>(
            `${BASE_URL}/purchaseOrderDetail/${id}`,
        );

        return response.data;
    },

    async create(
        payload: CreatePurchaseOrderPayload,
    ): Promise<ApiDataResponse<PurchaseOrder>> {
        const response = await api.post<ApiDataResponse<PurchaseOrder>>(
            `${BASE_URL}/createPurchaseOrder`,
            payload,
        );

        return response.data;
    },

    async update(
        id: number,
        payload: CreatePurchaseOrderPayload,
    ): Promise<ApiDataResponse<PurchaseOrder>> {
        const response = await api.put<ApiDataResponse<PurchaseOrder>>(
            `${BASE_URL}/updatePurchaseOrder/${id}`,
            payload,
        );

        return response.data;
    },

    async delete(id: number): Promise<ApiDataResponse<null>> {
        const response = await api.delete<ApiDataResponse<null>>(
            `${BASE_URL}/deletePurchaseOrder/${id}`,
        );

        return response.data;
    },
};

export default purchaseOrderService;
