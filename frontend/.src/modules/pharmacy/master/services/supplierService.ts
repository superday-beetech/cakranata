import api from "@/api/client";

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

export interface SupplierApiResponse<T> {
  status: number;
  error: boolean;
  message: string | string[];
  data?: T;
  errors?: Record<string, string>;
}

const BASE_URL = "/pharmacy/supplier-api";

const supplierService = {
  getAll(params?: {
    page?: number;
    per_page?: number;
    search?: string;
    is_active?: number;
  }) {
    return api.get<SupplierApiResponse<SupplierListData>>(BASE_URL, {
      params,
    });
  },

  getById(id: number) {
    return api.get<SupplierApiResponse<Supplier>>(`${BASE_URL}/show/${id}`);
  },

  create(payload: SupplierPayload) {
    return api.post<SupplierApiResponse<Supplier>>(
      `${BASE_URL}/create`,
      payload,
    );
  },

  update(id: number, payload: SupplierPayload) {
    return api.put<SupplierApiResponse<Supplier>>(
      `${BASE_URL}/update/${id}`,
      payload,
    );
  },

  delete(id: number) {
    return api.delete<SupplierApiResponse<null>>(
      `${BASE_URL}/delete/${id}`,
    );
  },
};

export default supplierService;
