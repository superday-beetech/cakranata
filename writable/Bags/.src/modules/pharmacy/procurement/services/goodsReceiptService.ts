
import api from "@/api/client";

import type {
    ApiResponse,
    GoodsReceipt,
    GoodsReceiptListParams,
    GoodsReceiptListResult,
    GoodsReceiptPayload,
} from "../types/goodsReceipt";

const BASE_URL = "/pharmacy/procurement-api";

export interface GoodsReceiptBatchRow {
    batch_number: string;
    quantity: number;
    expired_at: string | null;
}

export interface GoodsReceiptPostPayload {
    batch_breakdown: Record<number, GoodsReceiptBatchRow[]>;
}

export interface GoodsReceiptPostResult {
    receipt_id: number;
    receipt_number: string;
    status: "posted";
    purchase_order_id: number;
    purchase_order_status: "partial" | "received";
    batch_ids: number[];
}

const goodsReceiptService = {
    async getAll(params: GoodsReceiptListParams = {}) {
        const response = await api.get<
            ApiResponse<GoodsReceiptListResult>
        >(`${BASE_URL}/goodsReceipts`, {
            params,
        });

        return response.data.data;
    },

    async getById(id: number) {
        const response = await api.get<
            ApiResponse<GoodsReceipt>
        >(`${BASE_URL}/goodsReceiptDetail/${id}`);

        return response.data.data;
    },

    async create(payload: GoodsReceiptPayload) {
        const response = await api.post<
            ApiResponse<GoodsReceipt>
        >(`${BASE_URL}/createGoodsReceipt`, payload);

        return response.data.data;
    },

    async post(
        id: number,
        payload: GoodsReceiptPostPayload
    ) {
        const response = await api.post<
            ApiResponse<GoodsReceiptPostResult>
        >(
            `${BASE_URL}/postGoodsReceipt/${id}`,
            payload
        );

        return response.data.data;
    },
};

export default goodsReceiptService;
