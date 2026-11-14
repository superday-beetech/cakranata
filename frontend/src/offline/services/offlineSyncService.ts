import axios from "axios";
import type { Router } from "vue-router";

import { salesService } from "@/modules/sales/services/salesService";
import {
  getLocalTransaction,
  getPendingSyncQueue,
  markTransactionConflict,
  markTransactionRetry,
  markTransactionSynced,
  markTransactionSyncing,
  recoverStaleSyncQueue,
} from "@/offline/repositories/offlineRepository";

interface SyncSummary {
  processed: number;
  synced: number;
  conflicts: number;
  deferred: number;
}

interface ApiErrorBody {
  message?: unknown;
  errors?: unknown;
}

const MAX_RETRY_DELAY_MS = 5 * 60 * 1000;
let syncPromise: Promise<SyncSummary> | null = null;

function errorMessage(error: unknown): string {
  if (axios.isAxiosError<ApiErrorBody>(error)) {
    const message = error.response?.data?.message;

    if (Array.isArray(message)) {
      return message.map(String).join(", ");
    }

    if (typeof message === "string" && message.trim()) {
      return message;
    }

    return error.message || "Sinkronisasi gagal.";
  }

  return error instanceof Error ? error.message : "Sinkronisasi gagal.";
}

function responseStatus(error: unknown): number | undefined {
  return axios.isAxiosError(error) ? error.response?.status : undefined;
}

function conflictTypeFromError(error: unknown): "insufficient_stock" | "product_unavailable" | "price_changed" | "invalid_transaction" | "unknown" {
  const message = errorMessage(error).toLowerCase();

  if (/stok|stock|batch/.test(message)) return "insufficient_stock";
  if (/produk|product/.test(message) && /aktif|available|ditemukan|not found/.test(message)) return "product_unavailable";
  if (/harga|price/.test(message)) return "price_changed";

  const status = responseStatus(error);
  if (status === 400 || status === 422) return "invalid_transaction";

  return "unknown";
}

function retryDelay(attempts: number): number {
  const exponential = 5000 * 2 ** Math.max(0, attempts - 1);
  return Math.min(exponential, MAX_RETRY_DELAY_MS);
}

function emitSyncEvent(name: string, detail: Record<string, unknown>): void {
  if (typeof window !== "undefined") {
    window.dispatchEvent(new CustomEvent(name, { detail }));
  }
}

/**
 * Kirim antrean penjualan satu per satu.
 * Idempotency-Key selalu memakai key permanen yang tersimpan di IndexedDB.
 */
export function syncPendingSales(isAuthenticated: boolean): Promise<SyncSummary> {
  if (!isAuthenticated || typeof navigator === "undefined" || !navigator.onLine) {
    return Promise.resolve({ processed: 0, synced: 0, conflicts: 0, deferred: 0 });
  }

  if (syncPromise) {
    return syncPromise;
  }

  syncPromise = (async () => {
    const summary: SyncSummary = { processed: 0, synced: 0, conflicts: 0, deferred: 0 };

    await recoverStaleSyncQueue();

    const queue = await getPendingSyncQueue();

    for (const item of queue) {
      if (!navigator.onLine) {
        break;
      }

      const claimed = await markTransactionSyncing(item.localId);
      if (!claimed) {
        continue;
      }

      const transaction = await getLocalTransaction(item.localId);

      if (!transaction) {
        summary.deferred++;
        continue;
      }

      summary.processed++;

      try {
        const sale = await salesService.checkout(
          transaction.payload.sale,
          transaction.payload.payment,
          transaction.idempotencyKey,
        );

        await markTransactionSynced(transaction.localId, sale.id);
        summary.synced++;

        emitSyncEvent("offline:transaction-synced", {
          localId: transaction.localId,
          serverId: sale.id,
        });
      } catch (error) {
        const status = responseStatus(error);
        const message = errorMessage(error);

        if (status === 409 || status === 400 || status === 404 || status === 422) {
          const conflictType = conflictTypeFromError(error);
          const serverData = axios.isAxiosError(error) && error.response?.data && typeof error.response.data === "object"
            ? error.response.data as Record<string, unknown>
            : undefined;

          await markTransactionConflict(transaction.localId, conflictType, message, serverData);
          summary.conflicts++;

          emitSyncEvent("offline:transaction-conflict", {
            localId: transaction.localId,
            conflictType,
            message,
          });

          continue;
        }

        const queueAttempts = item.attempts + 1;
        const delay = status === 401 || status === 403
          ? 60_000
          : retryDelay(queueAttempts);

        const state = await markTransactionRetry(transaction.localId, message, delay);
        summary.deferred++;

        emitSyncEvent("offline:transaction-deferred", {
          localId: transaction.localId,
          message,
          state,
          retryAfterMs: delay,
        });

        // Gangguan jaringan, sesi, atau server: hentikan batch agar tidak membanjiri API.
        if (!status || status === 401 || status === 403 || status === 429 || status >= 500) {
          break;
        }
      }
    }

    emitSyncEvent("offline:sync-finished", summary);
    return summary;
  })().finally(() => {
    syncPromise = null;
  });

  return syncPromise;
}

/**
 * Sinkronisasi berjalan saat sesi terautentikasi kembali aktif,
 * jaringan pulih, atau tab POS kembali terlihat.
 */
export function installOfflineSync(isAuthenticated: () => boolean, router: Router): () => void {
  let retryTimer: number | null = null;

  const run = (): void => {
    void syncPendingSales(isAuthenticated()).catch((error: unknown) => {
      console.error("[Offline Sync] Gagal menjalankan antrean:", error);
    });
  };

  const handleDeferred = (event: Event): void => {
    const detail = (event as CustomEvent<{ retryAfterMs?: number }>).detail;
    const delay = Number(detail?.retryAfterMs);

    if (!Number.isFinite(delay) || delay < 0) return;

    if (retryTimer !== null) {
      window.clearTimeout(retryTimer);
    }

    retryTimer = window.setTimeout(() => {
      retryTimer = null;
      run();
    }, delay + 100);
  };

  const handleVisibilityChange = (): void => {
    if (document.visibilityState === "visible") {
      run();
    }
  };

  window.addEventListener("online", run);
  window.addEventListener("offline:queue-updated", run);
  window.addEventListener("offline:transaction-deferred", handleDeferred);
  document.addEventListener("visibilitychange", handleVisibilityChange);

  const removeAfterEach = router.afterEach((to) => {
    if (to.meta.requiresAuth) {
      run();
    }
  });

  run();

  return () => {
    window.removeEventListener("online", run);
    window.removeEventListener("offline:queue-updated", run);
    window.removeEventListener("offline:transaction-deferred", handleDeferred);

    if (retryTimer !== null) {
      window.clearTimeout(retryTimer);
      retryTimer = null;
    }

    document.removeEventListener("visibilitychange", handleVisibilityChange);
    removeAfterEach();
  };
}
