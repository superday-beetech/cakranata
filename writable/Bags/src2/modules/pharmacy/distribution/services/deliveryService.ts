
import api from "@/api/client";

import type {
  CreateDeliveryPayload,
  Delivery,
  DeliveryAllocationPayload,
  DeliveryItem,
  DistributionItem,
  PostDeliveryResult,
} from "../types/distribution";
import type { ApiDataResponse } from "@/types/types";

const BASE_URL = "/pharmacy/delivery-api";

export interface DeliveryFilters {
  search?: string;
  status?: string;
  distribution_order_id?: number;
  page?: number;
  per_page?: number;
}

export interface DeliveryListResponse {
  items: Delivery[];
  pagination?: {
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
    has_next: boolean;
    has_prev: boolean;
  };
}

export interface DeliveryDetailResponse {
  delivery: Delivery;
  order_items: DistributionItem[];
  items: DeliveryItem[];
}

interface CreateDeliveryResponse {
  id: number;
}

export const deliveryService = {
  /**
   * Daftar dokumen pengiriman.
   *
   * GET /pharmacy/delivery-api
   */
  async getDeliveries(
    filters: DeliveryFilters = {},
  ): Promise<DeliveryListResponse> {
    const response = await api.get<
      ApiDataResponse<DeliveryListResponse>
    >(BASE_URL, {
      params: {
        search: filters.search || undefined,
        status: filters.status || undefined,
        distribution_order_id:
          filters.distribution_order_id || undefined,
        page: filters.page || 1,
        per_page: filters.per_page || 20,
      },
    });

    return response.data.data;
  },

  /**
   * Detail dokumen pengiriman beserta item pesanan dan alokasi batch.
   *
   * GET /pharmacy/delivery-api/show/{id}
   */
  async getDeliveryDetail(
    id: number,
  ): Promise<DeliveryDetailResponse> {
    const response = await api.get<
      ApiDataResponse<DeliveryDetailResponse>
    >(`${BASE_URL}/show/${id}`);

    return response.data.data;
  },

  /**
   * Membuat dokumen pengiriman berstatus draft.
   *
   * POST /pharmacy/delivery-api/create
   */
  async createDelivery(
    payload: CreateDeliveryPayload,
  ): Promise<number> {
    const response = await api.post<
      ApiDataResponse<CreateDeliveryResponse>
    >(`${BASE_URL}/create`, payload);

    return Number(response.data.data.id);
  },

  /**
   * Posting pengiriman dan mengurangi stok batch.
   *
   * POST /pharmacy/delivery-api/post/{id}
   */
  async postDelivery(
    id: number,
    allocations: DeliveryAllocationPayload = {},
  ): Promise<PostDeliveryResult> {
    const response = await api.post<
      ApiDataResponse<PostDeliveryResult>
    >(`${BASE_URL}/post/${id}`, {
      allocations,
    });

    return response.data.data;
  },
};

export default deliveryService;
