import api from "@/api/client";

export interface Customer {
  id: number;
  code: string;
  member_number: string | null;
  barcode: string | null;
  name: string;
  customer_type: string;
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
  customer_type?: string;
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

export interface CustomerApiResponse<T> {
  status: number | boolean;
  error: boolean;
  message: string | string[];
  data?: T;
  errors?: Record<string, string>;
}

export interface CustomerSearchParams {
  page?: number;
  per_page?: number;
  search?: string;
  is_active?: number;
}

const BASE_URL = "/pharmacy/customer-api";

const customerService = {
  getAll(params: CustomerSearchParams = {}) {
    return api.get<CustomerApiResponse<CustomerListData>>(BASE_URL, {
      params,
    });
  },

  getById(id: number) {
    return api.get<CustomerApiResponse<Customer>>(
      `${BASE_URL}/show/${id}`,
    );
  },

  create(payload: CustomerPayload) {
    return api.post<CustomerApiResponse<Customer>>(
      `${BASE_URL}/create`,
      payload,
    );
  },

  update(id: number, payload: CustomerPayload) {
    return api.put<CustomerApiResponse<Customer>>(
      `${BASE_URL}/update/${id}`,
      payload,
    );
  },

  delete(id: number) {
    return api.delete<CustomerApiResponse<null>>(
      `${BASE_URL}/delete/${id}`,
    );
  },
};

export default customerService;
