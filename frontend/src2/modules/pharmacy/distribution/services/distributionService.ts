
import api from "@/api/client";

import type {
  DistributionFilters,
  DistributionOrder,
  DistributionOrderDetail,
  DistributionOrderPayload,
} from "../types/distribution";
import type { ApiDataResponse } from "@/types/types";

const BASE_URL = "/pharmacy/distribution-api";

export const distributionService = {
  /**
   * Mengambil daftar pesanan distribusi.
   *
   * GET /pharmacy/distribution-api
   */
  async getOrders(
    filters: DistributionFilters = {},
  ): Promise<DistributionOrder[]> {
    const response = await api.get<ApiDataResponse<DistributionOrder[]>>(
      BASE_URL,
      {
        params: {
          search: filters.search || undefined,
          status: filters.status || undefined,
          customer_id: filters.customer_id || undefined,
        },
      },
    );

    return response.data.data;
  },

  /**
   * Mengambil detail pesanan, pelanggan, item produk,
   * dan riwayat pengiriman.
   *
   * GET /pharmacy/distribution-api/show/{id}
   */
  async getOrderDetail(
    id: number,
  ): Promise<DistributionOrderDetail> {
    if (!Number.isInteger(id) || id <= 0) {
      throw new Error("ID pesanan distribusi tidak valid.");
    }

    const response = await api.get<
      ApiDataResponse<DistributionOrderDetail>
    >(`${BASE_URL}/show/${id}`);

    return response.data.data;
  },

  /**
   * Membuat pesanan distribusi baru.
   *
   * POST /pharmacy/distribution-api/create
   */
  async createOrder(
    payload: DistributionOrderPayload,
  ): Promise<number> {
    const response = await api.post<ApiDataResponse<{ id: number }>>(
      `${BASE_URL}/create`,
      payload,
    );

    const id = Number(response.data.data.id);

    if (!Number.isInteger(id) || id <= 0) {
      throw new Error("ID pesanan tidak valid dari respons server.");
    }

    return id;
  },
};

export default distributionService;
