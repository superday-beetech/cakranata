// =============================================================================
// HTTP layer murni — Route Registry.
// =============================================================================

import api from "@/api/client";
import type { ApiResponse } from "@/types/types";
import { ApiError, toApiError } from "./apiError";
import type {
  ApplyPayload,
  ApplyResult,
  RegisteredItem,
  RegisteredPayload,
  RegisteredQuery,
  RegistryRow,
  SaveVerbsPayload,
  SaveVerbsResult,
  ScanItem,
  ScanPayload,
  ShieldPreviewItem,
  ShieldSyncPayload,
  ShieldSyncResult,
  UpdateStatusPayload,
  UpdateStatusResult,
  VerbsPayload,
} from "../types/route-registry";

// PENTING: Jangan diubah. Prefix `route-registry-api` dibutuhkan ApiRouter.
const BASE = "/route-registry/route-registry-api";

function unwrap<T>(payload: ApiResponse<T> | undefined, fallback: string): T {
  if (!payload) throw new ApiError({ message: fallback, code: "unknown" });
  if (payload.error === true) {
    throw new ApiError({
      message: (Array.isArray(payload.message) ? payload.message.join(" ") : payload.message) || fallback,
      code: "validation",
      httpStatus: payload.status,
      fieldErrors: payload.errors ?? {},
    });
  }
  return payload.data as T;
}

async function call<T>(fn: () => Promise<{ data: ApiResponse<T> }>, fallback: string): Promise<T> {
  try {
    const { data } = await fn();
    return unwrap(data, fallback);
  } catch (error) {
    throw toApiError(error, fallback);
  }
}

// -----------------------------------------------------------------------------
// Normalizer: TINYINT MySQL ("1") -> number (1)
// -----------------------------------------------------------------------------
function toBinary(value: unknown): 0 | 1 {
  return Number(value) === 1 ? 1 : 0;
}

function normalizeRegistryRow(row: RegistryRow): RegistryRow {
  return {
    ...row,
    id: Number(row.id),
    is_discovered: toBinary(row.is_discovered),
    is_routable: toBinary(row.is_routable),
    is_allowed: toBinary(row.is_allowed),
  };
}

function normalizeScanItem(item: ScanItem): ScanItem {
  return {
    ...item,
    registry: item.registry ? normalizeRegistryRow(item.registry) : null,
  };
}

function normalizeRegisteredItem(item: RegisteredItem): RegisteredItem {
  return {
    ...item,
    registry: normalizeRegistryRow(item.registry),
  };
}

export const routeRegistryApi = {
  async scan(): Promise<ScanPayload> {
    const payload = await call(() => api.get<ApiResponse<ScanPayload>>(`${BASE}/scan`), "Gagal memindai modul.");
    return { ...payload, items: payload.items.map(normalizeScanItem) };
  },

  async registered(query: RegisteredQuery): Promise<RegisteredPayload> {
    const payload = await call(
      () =>
        api.get<ApiResponse<RegisteredPayload>>(`${BASE}/registered`, {
          params: {
            page: query.page,
            perPage: query.perPage,
            search: query.search || undefined,
          },
        }),
      "Gagal memuat registry.",
    );
    return { ...payload, items: payload.items.map(normalizeRegisteredItem) };
  },

  apply(payload: ApplyPayload): Promise<ApplyResult> {
    return call(() => api.post<ApiResponse<ApplyResult>>(`${BASE}/apply`, payload), "Gagal menerapkan perubahan registry.");
  },

  getVerbs(id: number): Promise<VerbsPayload> {
    return call(() => api.get<ApiResponse<VerbsPayload>>(`${BASE}/getVerbs/${id}`), "Gagal memuat HTTP verbs.");
  },

  saveVerbs(payload: SaveVerbsPayload): Promise<SaveVerbsResult> {
    return call(() => api.post<ApiResponse<SaveVerbsResult>>(`${BASE}/saveVerbs`, payload), "Gagal menyimpan HTTP verbs.");
  },

  async updateStatus(payload: UpdateStatusPayload): Promise<UpdateStatusResult> {
    const result = await call(() => api.patch<ApiResponse<UpdateStatusResult>>(`${BASE}/updateStatus`, payload), "Gagal memperbarui status endpoint.");
    return {
      ...result,
      id: Number(result.id),
      is_routable: toBinary(result.is_routable),
      is_allowed: toBinary(result.is_allowed),
    };
  },

  previewShield(): Promise<ShieldPreviewItem[]> {
    return call(() => api.get<ApiResponse<ShieldPreviewItem[]>>(`${BASE}/previewShieldPermissions`), "Gagal memuat preview permission Shield.").then((items) => (Array.isArray(items) ? items : []));
  },

  syncShield(payload: ShieldSyncPayload): Promise<ShieldSyncResult> {
    return call(() => api.post<ApiResponse<ShieldSyncResult>>(`${BASE}/syncShieldPermissions`, payload), "Sinkronisasi permission ke Shield gagal.");
  },
};
