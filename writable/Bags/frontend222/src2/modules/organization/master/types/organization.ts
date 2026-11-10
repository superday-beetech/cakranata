export type OrganizationStatus = "active" | "inactive" | "suspended";

export interface Organization {
    id: number;
    code: string;
    slug: string;
    name: string;
    legal_name: string | null;
    organization_type: string;
    status: OrganizationStatus;
    timezone: string;
    currency: string;
    settings?: Record<string, unknown> | null;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface OrganizationPagination {
    current_page: number;
    per_page: number;
    total: number;
    total_pages: number;
}

export interface OrganizationListParams {
    page?: number;
    per_page?: number;
    q?: string;
}

export interface CreateOrganizationPayload {
    code: string;
    slug: string;
    name: string;
    legal_name?: string | null;
    organization_type: string;
    timezone: string;
    currency: string;
}

export interface UpdateOrganizationPayload {
    name: string;
    legal_name?: string | null;
    timezone: string;
    currency: string;
     organization_type: string,
}

export interface OrganizationListData {
    items: Organization[];
    pagination: OrganizationPagination;
}
