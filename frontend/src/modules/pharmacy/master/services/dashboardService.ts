import api from "@/api/client";
import type { ApiResponse } from "@/types/types";

import type {
    DashboardDateRange,
    DashboardOverview,
    CashFlowItem,
    TopSellingItem,
    TopSellingParams,
} from "@/modules/pharmacy/master/types/dashboard";

const BASE_URL = "/pharmacy/dashboard-api";

export const dashboardService = {
    /**
     * Ringkasan utama dashboard.
     */
    async getOverview(params: DashboardDateRange = {}) {
        const response = await api.get<ApiResponse<DashboardOverview>>(
            BASE_URL,
            { params },
        );

        return response.data;
    },

    /**
     * Grafik arus kas.
     */
    async getCashFlow(params: DashboardDateRange = {}) {
        const response = await api.get<ApiResponse<CashFlowItem[]>>(
            `${BASE_URL}/cash-flow`,
            { params },
        );

        return response.data;
    },

    /**
     * Produk terlaris.
     */
    async getTopSelling(params: TopSellingParams = {}) {
        const response = await api.get<ApiResponse<TopSellingItem[]>>(
            `${BASE_URL}/top-selling`,
            { params },
        );

        return response.data;
    },
};

export default dashboardService;
