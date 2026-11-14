// =============================================================================
// Domain types — Route Registry
// =============================================================================

export const HTTP_VERBS = ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS", "HEAD"] as const;

export type HttpVerb = (typeof HTTP_VERBS)[number];

export type ScanStatus = "new" | "scanned" | "unknown" | string;

export type ApplyAction = "insert" | "update" | "delete";

export type StatusField = "routable" | "allowed";

export type ScanFilter = "all" | "new" | "registered";

export type ScanItemState = "new" | "registered";

// -----------------------------------------------------------------------------
// RegistryRow — MUTABLE (untuk optimistic update di Pinia)
// -----------------------------------------------------------------------------

export interface RegistryRow {
  id: number;
  module: string;
  controller: string;
  method: string;
  endpoint: string;
  url_route: string | null;
  namespace: string | null;
  is_discovered: 0 | 1;
  is_routable: 0 | 1;
  is_allowed: 0 | 1;
  scan_status: ScanStatus;
  last_scanned_at: string | null;
  permissions: string[];
  verbs: HttpVerb[];
}

// -----------------------------------------------------------------------------
// Items
// -----------------------------------------------------------------------------

export interface RegisteredItem {
  module: string;
  controller: string;
  method: string;
  endpoint: string;
  registry: RegistryRow;
}

export interface ScanItem {
  module: string;
  controller: string;
  method: string;
  endpoint: string;
  url_route: string | null;
  namespace: string | null;
  permission: string | null;
  has_interface: boolean;
  state: ScanItemState;
  registry: RegistryRow | null;
}

// -----------------------------------------------------------------------------
// Payload API
// -----------------------------------------------------------------------------

export interface Pagination {
  readonly page: number;
  readonly perPage: number;
  readonly total: number;
  readonly totalPages: number;
  readonly hasPrev: boolean;
  readonly hasNext: boolean;
}

export interface RegisteredQuery {
  page: number;
  perPage: number;
  search: string;
}

export interface RegisteredPayload {
  readonly items: RegisteredItem[];
  readonly pagination: Pagination;
}

export interface ScanPayload {
  readonly total: number;
  readonly items: ScanItem[];
}

export interface ApplyPayload {
  readonly action: ApplyAction;
  readonly selected: readonly string[];
  readonly url_route?: Readonly<Record<string, string>>;
  readonly permissions?: Readonly<Record<string, string>>;
}

export interface ApplyResult {
  readonly inserted?: number;
  readonly updated?: number;
  readonly deleted?: readonly string[];
  readonly skipped?: number;
  readonly total_deleted?: number;
  readonly total_skipped?: number;
}

export interface VerbsPayload {
  readonly id: number;
  readonly endpoint: string;
  readonly verbs: readonly HttpVerb[];
}

export interface SaveVerbsPayload {
  readonly endpoint: string;
  readonly verbs: readonly HttpVerb[];
}

export interface SaveVerbsResult {
  readonly endpoint: string;
  readonly verbs: readonly HttpVerb[];
  readonly saved: number;
}

export interface UpdateStatusPayload {
  readonly id: number;
  readonly field: StatusField;
  readonly value: boolean;
}

export interface UpdateStatusResult {
  readonly id: number;
  readonly endpoint: string;
  readonly field: StatusField;
  readonly value: boolean;
  readonly is_routable: 0 | 1;
  readonly is_allowed: 0 | 1;
}

export interface ShieldPreviewItem {
  readonly permission: string;
  readonly description: string;
  readonly exists: boolean;
}

export interface ShieldSyncPayload {
  readonly items: ReadonlyArray<{ readonly permission: string; readonly description: string }>;
}

export interface ShieldSyncResult {
  readonly inserted: number;
  readonly updated: number;
  readonly existing: number;
  readonly skipped: number;
}
