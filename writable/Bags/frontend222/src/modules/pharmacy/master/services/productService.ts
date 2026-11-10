import api from "@/api/client";

export interface Product {
    id: number;
    sku: string | null;
    barcode: string | null;
    name: string;
    category_id: number;
    unit_id: number;
    category_name?: string | null;
    unit_name?: string | null;
    purchase_price: number | string;
    selling_price: number | string;
    description: string | null;
    is_active: number;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface ProductPayload {
    sku: string | null;
    barcode: string | null;
    name: string;
    category_id: number;
    unit_id: number;
    purchase_price: number;
    selling_price: number;
    description: string | null;
    is_active: number;
}

export interface ProductPagination {
    current_page: number;
    per_page: number;
    total: number;
    total_pages: number;
}

export interface ProductListData {
    items: Product[];
    pagination: ProductPagination;
}

export interface ProductApiResponse<T> {
    status: number;
    error: boolean;
    message: string | string[];
    data?: T;
    errors?: Record<string, string>;
}

const BASE_URL = "/pharmacy/product-api";

const productService = {
    getAll(params?: {
    page?: number;
    per_page?: number;
    search?: string;
    date_from?: string;
    date_to?: string;
    is_active?: number;
}) {
    return api.get<ProductApiResponse<ProductListData>>(
        BASE_URL,
        { params }
    );
},

    getById(id: number) {
        return api.get<ProductApiResponse<Product>>(
            `${BASE_URL}/show/${id}`
        );
    },

    create(payload: ProductPayload) {
        return api.post<ProductApiResponse<Product>>(
            `${BASE_URL}/create`,
            payload
        );
    },

    update(id: number, payload: ProductPayload) {
        return api.put<ProductApiResponse<Product>>(
            `${BASE_URL}/update/${id}`,
            payload
        );
    },

    delete(id: number) {
        return api.delete<ProductApiResponse<{ id: number }>>(
            `${BASE_URL}/delete/${id}`
        );
    },
};

export default productService;
