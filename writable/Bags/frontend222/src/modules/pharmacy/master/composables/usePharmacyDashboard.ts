import { ref } from "vue";
import type { ApiResponse } from "@/types/types";

import dashboardService from "@/modules/pharmacy/master/services/dashboardService";

import type {
    DashboardDateRange,
    DashboardOverview,
    CashFlowItem,
    TopSellingItem,
} from "@/modules/pharmacy/master/types/dashboard";

export function usePharmacyDashboard() {
    const loading = ref(false);
    const error = ref<string | null>(null);

    const dateRange = ref<DashboardDateRange>({});

    const overview = ref<ApiResponse<DashboardOverview> | null>(null);
    const cashFlow = ref<ApiResponse<CashFlowItem[]> | null>(null);
    const topSelling = ref<ApiResponse<TopSellingItem[]> | null>(null);

    const topSellingLimit = ref(10);

    function getErrorMessage(reason: unknown): string {
        if (reason instanceof Error) {
            return reason.message;
        }

        return "Terjadi kesalahan saat mengambil data dashboard.";
    }

    async function loadDashboard(): Promise<void> {
        loading.value = true;
        error.value = null;

        const params = { ...dateRange.value };

        try {
            const results = await Promise.allSettled([
                dashboardService.getOverview(params),
                dashboardService.getCashFlow(params),
                dashboardService.getTopSelling({
                    ...params,
                    limit: topSellingLimit.value,
                }),
            ]);

            const errors: string[] = [];

            if (results[0].status === "fulfilled") {
                overview.value = results[0].value;
            } else {
                errors.push(
                    `Overview: ${getErrorMessage(results[0].reason)}`,
                );
            }

            if (results[1].status === "fulfilled") {
                cashFlow.value = results[1].value;
            } else {
                errors.push(
                    `Arus kas: ${getErrorMessage(results[1].reason)}`,
                );
            }

            if (results[2].status === "fulfilled") {
                topSelling.value = results[2].value;
            } else {
                errors.push(
                    `Produk terlaris: ${getErrorMessage(results[2].reason)}`,
                );
            }

            if (errors.length) {
                error.value = errors.join("\n");
            }
        } finally {
            loading.value = false;
        }
    }

    async function setDateRange(
        range: DashboardDateRange,
    ): Promise<void> {
        dateRange.value = { ...range };
        await loadDashboard();
    }

    async function setTopSellingLimit(limit: number): Promise<void> {
        topSellingLimit.value = Math.max(1, Math.min(limit, 100));
        await loadDashboard();
    }

    async function refresh(): Promise<void> {
        await loadDashboard();
    }

    return {
        loading,
        error,
        dateRange,

        overview,
        cashFlow,
        topSelling,

        topSellingLimit,

        loadDashboard,
        setDateRange,
        setTopSellingLimit,
        refresh,
    };
}

export default usePharmacyDashboard;
