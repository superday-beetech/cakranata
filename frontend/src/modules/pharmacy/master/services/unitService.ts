import api from "@/api/client";
import type { ApiResponse } from "@/types/types";

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

const BASE_URL = "/pharmacy/unit-api";

const unitService = {
    getAll(params?: {
        page?: number;
        per_page?: number;
        search?: string;
    }) {
        return api.get<ApiResponse<UnitListData>>(BASE_URL, { params });
    },

    getById(id: number) {
        return api.get<ApiResponse<Unit>>(`${BASE_URL}/show/${id}`);
    },

    create(payload: UnitPayload) {
        return api.post<ApiResponse<Unit>>(
            `${BASE_URL}/create`,
            payload
        );
    },

    update(id: number, payload: UnitPayload) {
        return api.put<ApiResponse<Unit>>(
            `${BASE_URL}/update/${id}`,
            payload
        );
    },

    delete(id: number) {
        return api.delete<ApiResponse<{ id: number }>>(
            `${BASE_URL}/delete/${id}`
        );
    },
};

export default unitService;
