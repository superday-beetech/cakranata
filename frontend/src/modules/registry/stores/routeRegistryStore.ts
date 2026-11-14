// =============================================================================
// Pinia store — single source of truth untuk modul Route Registry.
// =============================================================================

import { defineStore } from "pinia";
import { computed, reactive, ref } from "vue";

import { useNotificationStore } from "@/composables/useNotification";

import { routeRegistryApi } from "../services/routeRegistryApi";
import { ApiError } from "../services/apiError";
import { routeRegistryService } from "../services/routeRegistryService";
import type { ApplyPayload, ApplyResult, HttpVerb, Pagination, RegisteredItem, RegisteredQuery, ScanItem, ShieldPreviewItem, ShieldSyncResult, StatusField, UpdateStatusResult } from "../types/route-registry";

const DEFAULT_QUERY: RegisteredQuery = { page: 1, perPage: 25, search: "" };

function describeError(error: unknown, fallback: string): string {
  if (error instanceof ApiError) return error.message;
  if (error instanceof Error) return error.message || fallback;
  return fallback;
}

export const useRouteRegistryStore = defineStore("route-registry", () => {
  const notification = useNotificationStore();

  // -------- State --------
  const registeredItems = ref<RegisteredItem[]>([]);
  const scanItems = ref<ScanItem[]>([]);
  const scanLoaded = ref(false);
  const pagination = ref<Pagination>({
    page: 1,
    perPage: 25,
    total: 0,
    totalPages: 1,
    hasPrev: false,
    hasNext: false,
  });
  const query = reactive<RegisteredQuery>({ ...DEFAULT_QUERY });

  const loading = reactive({
    registered: false,
    scan: false,
    apply: false,
    verbs: false,
    shield: false,
  });

  const statusPending = reactive(new Set<string>());
  const statusKey = (id: number, field: StatusField) => `${id}:${field}`;

  // -------- Getters --------
  const registeredTotal = computed(() => pagination.value.total);
  const routableOnPage = computed(() => registeredItems.value.filter((i) => i.registry.is_routable === 1).length);

  // -------- Helpers --------
  function applyRegistryStatus(id: number, result: Pick<UpdateStatusResult, "is_routable" | "is_allowed">): void {
    for (const item of registeredItems.value) {
      if (item.registry.id !== id) continue;
      item.registry.is_routable = result.is_routable;
      item.registry.is_allowed = result.is_allowed;
    }
    for (const item of scanItems.value) {
      if (!item.registry || item.registry.id !== id) continue;
      item.registry.is_routable = result.is_routable;
      item.registry.is_allowed = result.is_allowed;
    }
  }

  function applyRegistryVerbs(endpoint: string, verbs: readonly HttpVerb[]): void {
    for (const item of registeredItems.value) {
      if (item.registry.endpoint !== endpoint) continue;
      item.registry.verbs = [...verbs];
    }
    for (const item of scanItems.value) {
      if (!item.registry || item.registry.endpoint !== endpoint) continue;
      item.registry.verbs = [...verbs];
    }
  }

  function findRegistrySnapshot(id: number): { is_routable: 0 | 1; is_allowed: 0 | 1 } | null {
    for (const item of registeredItems.value) {
      if (item.registry.id === id) {
        return {
          is_routable: item.registry.is_routable,
          is_allowed: item.registry.is_allowed,
        };
      }
    }
    for (const item of scanItems.value) {
      if (item.registry?.id === id) {
        return {
          is_routable: item.registry.is_routable,
          is_allowed: item.registry.is_allowed,
        };
      }
    }
    return null;
  }

  // -------- Actions: registered --------
  async function loadRegistered(partial?: Partial<RegisteredQuery>): Promise<void> {
    if (partial) Object.assign(query, partial);

    loading.registered = true;
    try {
      const payload = await routeRegistryApi.registered({ ...query });
      registeredItems.value = payload.items;
      pagination.value = payload.pagination;
      query.page = payload.pagination.page;
      query.perPage = payload.pagination.perPage;
    } catch (error) {
      notification.error(describeError(error, "Gagal memuat registry."));
      throw error;
    } finally {
      loading.registered = false;
    }
  }

  const reloadRegistered = () => loadRegistered();
  const changePage = (page: number) => loadRegistered({ page });
  const changePerPage = (perPage: number) => loadRegistered({ page: 1, perPage });
  const changeSearch = (search: string) => loadRegistered({ page: 1, search });

  // -------- Actions: scan --------
  async function runScan(): Promise<void> {
    loading.scan = true;
    try {
      const payload = await routeRegistryApi.scan();
      scanItems.value = payload.items;
      scanLoaded.value = true;
    } catch (error) {
      notification.error(describeError(error, "Gagal memindai modul."));
      throw error;
    } finally {
      loading.scan = false;
    }
  }

  // -------- Actions: apply --------
  async function applyScan(payload: ApplyPayload): Promise<ApplyResult> {
    loading.apply = true;
    try {
      const { apply, scan, registered } = await routeRegistryService.applyAndRefresh(payload, { ...query });

      scanItems.value = scan.items;
      scanLoaded.value = true;
      registeredItems.value = registered.items;
      pagination.value = registered.pagination;

      return apply;
    } catch (error) {
      notification.error(describeError(error, "Gagal menerapkan perubahan."));
      throw error;
    } finally {
      loading.apply = false;
    }
  }

  // -------- Actions: status --------
  async function updateStatus(id: number, field: StatusField, value: boolean): Promise<boolean> {
    const key = statusKey(id, field);
    if (statusPending.has(key)) return false;

    const snapshot = findRegistrySnapshot(id);

    statusPending.add(key);
    if (snapshot) {
      applyRegistryStatus(id, {
        is_routable: field === "routable" ? (value ? 1 : 0) : snapshot.is_routable,
        is_allowed: field === "allowed" ? (value ? 1 : 0) : snapshot.is_allowed,
      });
    }

    try {
      const result = await routeRegistryApi.updateStatus({ id, field, value });
      applyRegistryStatus(id, result);
      return true;
    } catch (error) {
      if (snapshot) applyRegistryStatus(id, snapshot);
      notification.error(describeError(error, "Gagal memperbarui status."));
      return false;
    } finally {
      statusPending.delete(key);
    }
  }

  // -------- Actions: verbs --------
  async function fetchVerbs(id: number): Promise<{ endpoint: string; verbs: HttpVerb[] }> {
    loading.verbs = true;
    try {
      const payload = await routeRegistryApi.getVerbs(id);
      return { endpoint: payload.endpoint, verbs: [...payload.verbs] };
    } catch (error) {
      notification.error(describeError(error, "Gagal memuat HTTP verbs."));
      throw error;
    } finally {
      loading.verbs = false;
    }
  }

  async function saveVerbs(endpoint: string, verbs: HttpVerb[]): Promise<void> {
    loading.verbs = true;
    try {
      await routeRegistryApi.saveVerbs({ endpoint, verbs });
      applyRegistryVerbs(endpoint, verbs);
      notification.success("HTTP verbs berhasil disimpan.");
    } catch (error) {
      notification.error(describeError(error, "Gagal menyimpan HTTP verbs."));
      throw error;
    } finally {
      loading.verbs = false;
    }
  }

  // -------- Actions: shield --------
  async function previewShield(): Promise<ShieldPreviewItem[]> {
    loading.shield = true;
    try {
      return await routeRegistryApi.previewShield();
    } catch (error) {
      notification.error(describeError(error, "Gagal memuat preview Shield."));
      throw error;
    } finally {
      loading.shield = false;
    }
  }

  async function syncShield(items: ReadonlyArray<{ permission: string; description: string }>): Promise<ShieldSyncResult> {
    loading.shield = true;
    try {
      const result = await routeRegistryService.syncShield({ items });
      notification.success(`Sinkronisasi berhasil. Ditambahkan: ${result.inserted}, Diperbarui: ${result.updated}, ` + `Sudah ada: ${result.existing}, Dilewati: ${result.skipped}.`);
      return result;
    } catch (error) {
      notification.error(describeError(error, "Sinkronisasi Shield gagal."));
      throw error;
    } finally {
      loading.shield = false;
    }
  }

  return {
    registeredItems,
    scanItems,
    scanLoaded,
    pagination,
    query,
    loading,
    registeredTotal,
    routableOnPage,
    loadRegistered,
    reloadRegistered,
    changePage,
    changePerPage,
    changeSearch,
    runScan,
    applyScan,
    updateStatus,
    fetchVerbs,
    saveVerbs,
    previewShield,
    syncShield,
  };
});
