import api from "@/api/client";

export interface PurchaseReturnItem {
    goods_receipt_item_id: number;
    batch_id: number;
    quantity: number;
    notes?: string;
}

export interface CreatePurchaseReturnPayload {
    return_number: string;
    goods_receipt_id: number;
    return_date: string;
    reason?: string;
    notes?: string;
    items: PurchaseReturnItem[];
}

export interface SalesReturnItem {
    delivery_item_id: number;
    quantity: number;
    restockable: boolean;
    condition_notes?: string;
}

export interface CreateSalesReturnPayload {
    return_number: string;
    delivery_id: number;
    return_date: string;
    reason?: string;
    notes?: string;
    items: SalesReturnItem[];
}

export const returnsService = {

    async listPurchase() {
    return api.get("/pharmacy/returns-api/list-purchase");
    },

    async showPurchase(id: number) {
        return api.get(`/pharmacy/returns-api/show-purchase/${id}`);
    },

    async createPurchase(payload: CreatePurchaseReturnPayload) {
        return api.post(
            "/pharmacy/returns-api/create-purchase",
            payload
        );
    },

    async postPurchase(id: number) {
        return api.post(
            `/pharmacy/returns-api/post-purchase/${id}`
        );
    },

    async createSales(payload: CreateSalesReturnPayload) {
        return api.post(
            "/pharmacy/returns-api/create-sales",
            payload
        );
    },

    async postSales(id: number) {
        return api.post(
            `/pharmacy/returns-api/post-sales/${id}`
        );
    },
};

export default returnsService;
