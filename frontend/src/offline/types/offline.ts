import type { CreateSalePayload, PostSalePayload } from "@/modules/sales/types/sales";

export type OfflineStockPolicy = "allow_negative" | "quota";

export type LocalTransactionStatus = "pending" | "syncing" | "synced" | "conflict" | "failed";

export type SyncQueueStatus = "pending" | "processing" | "completed" | "conflict" | "failed";

export interface OfflineStockPolicySnapshot {
  policy: OfflineStockPolicy;
  settingsVersion: number;
  createdAt: string;
}

export interface ProductCache {
  id: number | string;
  barcode?: string | null;
  name: string;
  sku?: string | null;
  unitId?: number | null;
  price?: number;
  isActive: boolean;
  updatedAt: string;
  cachedAt: string;
  rawData?: Record<string, unknown>;
}

export interface StockSnapshot {
  id: string;
  productId: number | string;
  warehouseId: number | string;
  quantity: number;
  serverVersion?: number;
  serverUpdatedAt?: string;
  cachedAt: string;
}

export interface StockQuota {
  id: string;
  productId: number | string;
  warehouseId: number | string;

  allocatedQuantity: number;
  usedQuantity: number;
  remainingQuantity: number;

  allocationId: string;
  settingsVersion: number;

  validFrom: string;
  expiresAt: string;
  cachedAt: string;
}

export interface SalesDraft {
  id: string;
  status: "draft";

  warehouseId?: number | string;
  customerId?: number | string | null;

  payload: Record<string, unknown>;

  createdAt: string;
  updatedAt: string;
}

export interface LocalTransaction {
  localId: string;
  idempotencyKey: string;
  serverId?: number | string;
  transactionType: "sale";
  status: LocalTransactionStatus;
  warehouseId: number | string | null;
  policySnapshot: OfflineStockPolicySnapshot;
  payload: OfflineSalePayload;
  createdAt: string;
  updatedAt: string;
  syncedAt?: string;
  errorMessage?: string;
}

export interface SyncQueueItem {
  localId: string;

  status: SyncQueueStatus;

  attempts: number;
  nextAttemptAt?: string;

  createdAt: string;
  updatedAt: string;

  lastAttemptAt?: string;
  errorMessage?: string;
}

export interface SyncConflict {
  id: string;
  localId: string;

  conflictType: "insufficient_stock" | "product_unavailable" | "price_changed" | "invalid_transaction" | "unknown";

  message: string;

  serverData?: Record<string, unknown>;
  localData?: Record<string, unknown>;

  createdAt: string;
  resolvedAt?: string;

  resolution?: string;
}

export interface OfflineSetting<T = unknown> {
  key: string;
  value: T;

  version: number;
  updatedAt: string;
  cachedAt: string;
}

export interface SyncMetadata {
  key: string;
  value: string | number | boolean | null;
  updatedAt: string;
}

export interface ProductCache {
  id: number | string;
  barcode?: string | null;
  name: string;
  sku?: string | null;
  unitName?: string | null;
  unitId?: number | null;
  price?: number;
  isActive: boolean;
  updatedAt: string;
  cachedAt: string;
  rawData?: Record<string, unknown>;
}

export interface OfflineSalePayload {
  sale: CreateSalePayload;
  payment: PostSalePayload;
}
