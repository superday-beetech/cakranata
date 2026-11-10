import api from "@/api/client";
import type { ApiDataResponse } from "@/types/types";

export interface ReportDateFilter {
    date_from?: string;
    date_to?: string;
}

export interface ReportPagination {
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
}

export interface StockSummaryFilter {
    keyword?: string;
    product_id?: number;
    category_id?: number;
    active_only?: number | boolean;
    date_from?: string;
    date_to?: string;
    page?: number;
    per_page?: number;
}

export interface StockSummaryItem {
    product_id: number;
    sku: string;
    barcode: string | null;
    product_name: string;
    category_id: number;
    category_name: string | null;
    unit_id: number;
    unit_name: string | null;
    unit_abbreviation: string | null;
    purchase_price: number;
    selling_price: number;
    is_active: number;
    batch_count: number;
    opening_stock: number;
    total_in: number;
    total_out: number;
    closing_stock: number;
    stock_value: number;
    expired_batch_count: number;
    expiring_batch_count: number;
}

export interface StockCardFilter extends ReportDateFilter {
    product_id: number;
    batch_id?: number;
    page?: number;
    per_page?: number;
}

export interface StockCardProduct {
    id: number;
    sku: string;
    name: string;
    unit_name: string | null;
    unit_abbreviation: string | null;
}

export interface StockCardItem {
    id: number;
    product_id: number;
    batch_id: number;
    movement_date: string;
    movement_type: string;
    reference_type: string;
    reference_id: number;
    quantity_in: number;
    quantity_out: number;
    unit_cost: number;
    notes: string | null;
    batch_number: string | null;
    expired_at: string | null;
    balance: number;
}

export interface StockCardSummary {
    opening_stock: number;
    total_in: number;
    total_out: number;
    closing_stock: number;
}

export interface BatchExpiryFilter {
    keyword?: string;
    product_id?: number;
    category_id?: number;
    expiry_status?: "all" | "expired" | "expiring" | "safe";
    days?: number;
    stock_only?: number | boolean;
    page?: number;
    per_page?: number;
}

export interface BatchExpiryItem {
    batch_id: number;
    product_id: number;
    sku: string;
    product_name: string;
    category_name: string | null;
    unit: string | null;
    goods_receipt_item_id: number;
    batch_number: string;
    expired_at: string;
    received_quantity: number;
    quantity_remaining: number;
    unit_cost: number;
    status: string;
    days_until_expiry: number;
    expiry_status: "expired" | "expiring" | "safe";
}

export interface PaginatedReport<T> {
    items: T[];
    pagination: ReportPagination;
}

export interface StockCardReport {
    product: StockCardProduct;
    summary: StockCardSummary;
    items: StockCardItem[];
    pagination: ReportPagination;
}

export interface BatchExpiryReport extends PaginatedReport<BatchExpiryItem> {
    filters: {
        expiry_status: string;
        days: number;
    };
}

export interface StockSummaryReport {
    period: {
        date_from: string;
        date_to: string;
    };
    summary: {
        product_count: number;
        opening_stock: number;
        total_in: number;
        total_out: number;
        closing_stock: number;
        stock_value: number;
        expired_batch_count: number;
        expiring_batch_count: number;
    };
    items: StockSummaryItem[];
    pagination: ReportPagination;
}

const BASE_URL = "/pharmacy/report-api";

async function getReport<T>(
    endpoint: string,
    params: object = {},
): Promise<T> {
    const response = await api.get<ApiDataResponse<T>>(
        `${BASE_URL}/${endpoint}`,
        { params },
    );

    return response.data.data;
}

async function downloadReport(
    endpoint: string,
    params: StockSummaryFilter = {},
): Promise<Blob> {
    const response = await api.get<Blob>(
        `${BASE_URL}/${endpoint}`,
        {
            params,
            responseType: "blob",
        },
    );

    const blob = response.data;

    if (blob.type.includes("application/json")) {
        const text = await blob.text();

        let payload: { message?: string };

        try {
            payload = JSON.parse(text);
        } catch {
            throw new Error("Respons ekspor tidak valid.");
        }

        throw new Error(payload.message || "Gagal mengunduh laporan.");
    }

    return blob;
}

const reportService = {
    getStockSummary(
        filters: StockSummaryFilter = {},
    ): Promise<StockSummaryReport> {
        return getReport("stock-summary", filters);
    },

    downloadStockSummaryCsv(
        filters: StockSummaryFilter = {},
    ): Promise<Blob> {
        return downloadReport("stock-summary-csv", filters);
    },

    downloadStockSummaryExcel(
        filters: StockSummaryFilter = {},
    ): Promise<Blob> {
        return downloadReport("stock-summary-excel", filters);
    },

    downloadStockSummaryPdf(
        filters: StockSummaryFilter = {},
    ): Promise<Blob> {
        return downloadReport("stock-summary-pdf", filters);
    },

    downloadStockCardCsv(
        filters: StockSummaryFilter = {},
    ): Promise<Blob> {
        return downloadReport("stock-card-csv", filters);
    },

    downloadStockCardExcel(
        filters: StockSummaryFilter = {},
    ): Promise<Blob> {
        return downloadReport("stock-card-excel", filters);
    },

    downloadStockCardPdf(
        filters: StockSummaryFilter = {},
    ): Promise<Blob> {
        return downloadReport("stock-card-pdf", filters);
    },

    getStockCard(
        filters: StockCardFilter,
    ): Promise<StockCardReport> {
        return getReport("stock-card", filters);
    },

    getBatchExpiry(
        filters: BatchExpiryFilter = {},
    ): Promise<BatchExpiryReport> {
        return getReport("batch-expiry", filters);
    },
};

export default reportService;
