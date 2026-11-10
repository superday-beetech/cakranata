export type OrganizationMembershipStatus = "active" | "inactive";

export interface OrganizationMembership {
    id: number;
    organization_id: number;
    user_id: number;
    username?: string;
    unit_id: number | null;
    unit_name?: string | null;
    membership_role: string;
    status: OrganizationMembershipStatus;
    is_default: boolean | number;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface CreateOrganizationMembershipPayload {
    user_id: number;
    unit_id?: number | null;
    membership_role?: string;
    is_default?: boolean;
}

export interface UpdateOrganizationMembershipPayload {
    user_id?: number;
    unit_id?: number | null;
    membership_role?: string;
    status?: OrganizationMembershipStatus;
    is_default?: boolean;
}


export const ORGANIZATION_MEMBERSHIP_ROLES = [
    { value: "owner", label: "Pemilik" },
    { value: "admin", label: "Administrator" },
    { value: "manager", label: "Manajer" },
    { value: "supervisor", label: "Supervisor" },
    { value: "staff", label: "Staf" },
    { value: "member", label: "Anggota" },
    { value: "viewer", label: "Pengamat" },
] as const;

export type OrganizationMembershipRole =
    typeof ORGANIZATION_MEMBERSHIP_ROLES[number]["value"];

export function formatMembershipRole(role: string): string {
    return ORGANIZATION_MEMBERSHIP_ROLES.find(
        item => item.value === role
    )?.label ?? role;
}
