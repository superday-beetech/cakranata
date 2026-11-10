import api from "@/api/client";
import type { ApiResponse } from "@/types/types";

export interface Supplier {
  id: number;
  code: string;
  name: string;
  phone: string | null;
  email: string | null;
  address: string | null;
  is_active: number;
  created_by?: number | null;
  updated_by?: number | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface SupplierPayload {
  code: string;
  name: string;
  phone: string | null;
  email: string | null;
  address: string | null;
  is_active: number;
}

export interface SupplierPagination {
  current_page: number;
  per_page: number;
  total: number;
  total_pages: number;
}

export interface SupplierListData {
  items: Supplier[];
  pagination: SupplierPagination;
}

const BASE_URL = "/pharmacy/supplier-api";

const supplierService = {
  getAll(params?: {
    page?: number;
    per_page?: number;
    search?: string;
    is_active?: number;
  }) {
    return api.get<ApiResponse<SupplierListData>>(BASE_URL, {
      params,
    });
  },

  getById(id: number) {
    return api.get<ApiResponse<Supplier>>(`${BASE_URL}/show/${id}`);
  },

  create(payload: SupplierPayload) {
    return api.post<ApiResponse<Supplier>>(
      `${BASE_URL}/create`,
      payload,
    );
  },

  update(id: number, payload: SupplierPayload) {
    return api.put<ApiResponse<Supplier>>(
      `${BASE_URL}/update/${id}`,
      payload,
    );
  },

  delete(id: number) {
    return api.delete<ApiResponse<null>>(
      `${BASE_URL}/delete/${id}`,
    );
  },
};

export default supplierService;
