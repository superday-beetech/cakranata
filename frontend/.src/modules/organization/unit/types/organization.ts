export interface Organization {
    id: number;
    code: string;
    slug: string;
    name: string;
    legal_name?: string | null;
    organization_type: string;
    status: string;
    timezone: string;
    currency: string;
    membership_role?: string;
    is_default?: number;
}

export interface OrganizationUnit {
    id: number;
    organization_id: number;
    parent_id: number | null;
    code: string;
    name: string;
    unit_type: string;
    status: "active" | "inactive";
    metadata?: string | Record<string, unknown> | null;
    created_at?: string;
    updated_at?: string;
}

export interface OrganizationUnitPayload {
    code: string;
    name: string;
    unit_type: string;
    parent_id: number | null;
}

export interface ApiResponse<T> {
    status: "success" | "error";
    error: boolean;
    message: string;
    data: T;
    code?: string;
    details?: Record<string, unknown>;
}

export interface PaginatedData<T> {
    items: T[];
    pagination: {
        page: number;
        per_page: number;
        total: number;
        total_pages: number;
    };
}
