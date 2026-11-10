import api from "@/api/client";

import type {
    Organization,
    OrganizationListData,
    OrganizationListParams,
    CreateOrganizationPayload,
    UpdateOrganizationPayload,
} from "../types/organization";

const BASE_URL = "/organization/organizations";

const organizationService = {
    getAll(params: OrganizationListParams = {}) {
        return api.get<{
            error: boolean;
            message: string | string[];
            data: OrganizationListData;
        }>(BASE_URL, { params });
    },

    getById(id: number) {
        return api.get<{
            error: boolean;
            message: string | string[];
            data: Organization;
        }>(`${BASE_URL}/show-organization/${id}`);
    },

    create(payload: CreateOrganizationPayload) {
        return api.post<{
            error: boolean;
            message: string | string[];
            data: Organization;
        }>(`${BASE_URL}/create-organization`, payload);
    },

    update(id: number, payload: UpdateOrganizationPayload) {
        return api.put<{
            error: boolean;
            message: string | string[];
            data: Organization;
        }>(`${BASE_URL}/update-organization/${id}`, payload);
    },

    delete(id: number) {
        return api.delete<{
            error: boolean;
            message: string | string[];
            data: unknown;
        }>(`${BASE_URL}/delete-organization/${id}`);
    },
};

export default organizationService;
