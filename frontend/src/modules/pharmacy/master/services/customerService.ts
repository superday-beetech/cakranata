import api from "@/api/client";
import type { ApiResponse } from "@/types/types";

export interface Customer {
  id: number;
  code: string;
  member_number: string | null;
  barcode: string | null;
  name: string;
  customer_type: string | null;
  phone: string | null;
  email: string | null;
  address: string | null;
  is_active: number;
  created_by?: number | null;
  updated_by?: number | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface CustomerPayload {
  code?: string;
  member_number?: string | null;
  barcode?: string | null;
  name: string;
  customer_type?: string | null;
  phone?: string | null;
  email?: string | null;
  address?: string | null;
  is_active?: number;
}

export interface CustomerPagination {
  current_page: number;
  per_page: number;
  total: number;
  total_pages: number;
}

export interface CustomerListData {
  items: Customer[];
  pagination: CustomerPagination;
}

export interface CustomerSearchParams {
  page?: number;
  per_page?: number;
  search?: string;
  customer_type?: string;
  is_active?: number;
}

const BASE_URL = "/pharmacy/customer-api";

const customerService = {
  getAll(params: CustomerSearchParams = {}) {
    return api.get<ApiResponse<CustomerListData>>(BASE_URL, {
      params,
    });
  },

  getById(id: number) {
    return api.get<ApiResponse<Customer>>(
      `${BASE_URL}/show/${id}`,
    );
  },

  create(payload: CustomerPayload) {
    return api.post<ApiResponse<Customer>>(
      `${BASE_URL}/create`,
      payload,
    );
  },

  update(id: number, payload: CustomerPayload) {
    return api.put<ApiResponse<Customer>>(
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

export default customerService;
