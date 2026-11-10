export type OrganizationUnitStatus = "active" | "inactive";

export interface OrganizationUnit {
  id: number;
  organization_id: number;
  parent_id: number | null;
  code: string;
  name: string;
  unit_type: string;
  status: OrganizationUnitStatus;
  metadata?: Record<string, unknown> | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface CreateOrganizationUnitPayload {
  parent_id?: number | null;
  code: string;
  name: string;
  unit_type: string;
  metadata?: Record<string, unknown> | null;
}

export interface UpdateOrganizationUnitPayload {
  parent_id?: number | null;
  code: string;
  name: string;
  unit_type: string;
  status?: OrganizationUnitStatus;
  metadata?: Record<string, unknown> | null;
}
