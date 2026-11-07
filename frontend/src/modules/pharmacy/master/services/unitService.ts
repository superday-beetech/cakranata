import api from "@/api/client";

export interface Unit {
    id: number;
    name: string;
    abbreviation: string;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface UnitPayload {
    name: string;
    abbreviation: string;
}

export interface UnitPagination {
    current_page: number;
    per_page: number;
    total: number;
    total_pages: number;
}

export interface UnitListData {
    items: Unit[];
    pagination: UnitPagination;
}

export interface UnitApiResponse<T> {
    status: number;
    error: boolean;
    message: string | string[];
    data?: T;
    errors?: Record<string, string>;
}

const BASE_URL = "/pharmacy/unit-api";

const unitService = {
    getAll(params?: {
        page?: number;
        per_page?: number;
        search?: string;
    }) {
        return api.get<UnitApiResponse<UnitListData>>(BASE_URL, { params });
    },

    getById(id: number) {
        return api.get<UnitApiResponse<Unit>>(`${BASE_URL}/show/${id}`);
    },

    create(payload: UnitPayload) {
        return api.post<UnitApiResponse<Unit>>(
            `${BASE_URL}/create`,
            payload
        );
    },

    update(id: number, payload: UnitPayload) {
        return api.put<UnitApiResponse<Unit>>(
            `${BASE_URL}/update/${id}`,
            payload
        );
    },

    delete(id: number) {
        return api.delete<UnitApiResponse<{ id: number }>>(
            `${BASE_URL}/delete/${id}`
        );
    },
};

export default unitService;
