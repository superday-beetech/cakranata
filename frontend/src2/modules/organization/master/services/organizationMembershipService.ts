import api from "@/api/client";

import type {
    CreateOrganizationMembershipPayload,
    UpdateOrganizationMembershipPayload,
} from "../types/organizationMembership";

const BASE_URL = "/organization/organization-memberships";

const organizationMembershipService = {
    getAll(
        organizationId: number,
        params?: {
            page?: number;
            per_page?: number;
            q?: string;
            status?: string;
        }
    ) {
        return api.get(
            `${BASE_URL}/index-memberships/${organizationId}`,
            { params }
        );
    },

    getMembership(organizationId: number, membershipId: number) {
        return api.post(`${BASE_URL}/show-memberships`, {
            organization_id: organizationId,
            membership_id: membershipId,
        });
    },

    createMembership(
        organizationId: number,
        payload: CreateOrganizationMembershipPayload
    ) {
        return api.post(
            `${BASE_URL}/create-memberships/${organizationId}`,
            payload
        );
    },

    updateMembership(
    organizationId: number,
    membershipId: number,
    payload: UpdateOrganizationMembershipPayload
        ) {
            return api.put(`${BASE_URL}/update-memberships`, {
                organization_id: organizationId,
                membership_id: membershipId,
                ...payload,
            });
        },

        deactivateMembership(
            organizationId: number,
            membershipId: number
        ) {
            return api.post(`${BASE_URL}/delete-memberships`, {
                organization_id: organizationId,
                membership_id: membershipId,
            });
        },

    getUsers(params?: {
        page?: number;
        per_page?: number;
        q?: string;
    }) {
        return api.get(`${BASE_URL}/index-users`, { params });
    },
};

export default organizationMembershipService;
