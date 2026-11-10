import api from "@/api/client";
import type { LegacyApiResponse } from "@/types/types";

import type {
    Organization,
    OrganizationUnit,
    OrganizationUnitPayload,
    PaginatedData,
} from "../types/organization";

const BASE_URL = "/organization/organizations";

export const organizationService = {
    async getAll() {
        const response = await api.get<
            LegacyApiResponse<PaginatedData<Organization>>
        >(BASE_URL, {
            params: {
                page: 1,
                per_page: 100,
            },
        });

        return response.data.data;
    },

    async getById(id: number) {
        const response = await api.get<LegacyApiResponse<Organization>>(
            `${BASE_URL}/${id}`,
        );

        return response.data.data;
    },

    async getUnits(
        organizationId: number,
        includeInactive = false,
    ) {
        const response = await api.get<LegacyApiResponse<OrganizationUnit[]>>(
            `${BASE_URL}/index-units/${organizationId}`,
            {
                params: {
                    include_inactive: includeInactive ? 1 : 0,
                },
            },
        );

        return response.data.data;
    },

    async getUnit(
        organizationId: number,
        unitId: number,
    ) {
        const response = await api.get<LegacyApiResponse<OrganizationUnit>>(
            `${BASE_URL}/${organizationId}/units/${unitId}`,
        );

        return response.data.data;
    },

    async createUnit(
        organizationId: number,
        payload: OrganizationUnitPayload,
    ) {
        const response = await api.post<LegacyApiResponse<OrganizationUnit>>(
            `${BASE_URL}/${organizationId}/units`,
            payload,
        );

        return response.data.data;
    },

    async updateUnit(
        organizationId: number,
        unitId: number,
        payload: Partial<OrganizationUnitPayload>,
    ) {
        const response = await api.put<LegacyApiResponse<OrganizationUnit>>(
            `${BASE_URL}/${organizationId}/units/${unitId}`,
            payload,
        );

        return response.data.data;
    },

    async deactivateUnit(
        organizationId: number,
        unitId: number,
    ) {
        const response = await api.delete<
            LegacyApiResponse<{ id: number; status: string }>
        >(`${BASE_URL}/${organizationId}/units/${unitId}`);

        return response.data.data;
    },
};
