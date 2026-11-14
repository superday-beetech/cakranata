// =============================================================================
// Service layer — orkestrasi lintas endpoint.
// =============================================================================
// Dipakai store ketika satu aksi butuh >1 panggilan API atau perlu
// menggabungkan hasil dari beberapa endpoint. Tidak menyimpan state.

import { routeRegistryApi } from "../services/routeRegistryApi";
import type { ApplyPayload, ApplyResult, RegisteredQuery, ShieldSyncPayload, ShieldSyncResult } from "../types/route-registry";

export interface ApplyAndRefreshResult {
  readonly apply: ApplyResult;
  readonly scan: Awaited<ReturnType<typeof routeRegistryApi.scan>>;
  readonly registered: Awaited<ReturnType<typeof routeRegistryApi.registered>>;
}

export const routeRegistryService = {
  /**
   * Apply perubahan lalu ambil ulang snapshot scan + registered
   * agar UI konsisten tanpa dua request terpisah di komponen.
   */
  async applyAndRefresh(payload: ApplyPayload, query: RegisteredQuery): Promise<ApplyAndRefreshResult> {
    const applied = await routeRegistryApi.apply(payload);

    const [scan, registered] = await Promise.all([routeRegistryApi.scan(), routeRegistryApi.registered(query)]);

    return { apply: applied, scan, registered };
  },

  async syncShield(payload: ShieldSyncPayload): Promise<ShieldSyncResult> {
    return routeRegistryApi.syncShield(payload);
  },
};
