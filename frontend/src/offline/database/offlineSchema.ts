export const OFFLINE_DATABASE_NAME = "acn_pharmacy_offline";

export const OFFLINE_DATABASE_VERSION = 1;

export const OFFLINE_SCHEMA = {
  products: "id, barcode, name, sku, updatedAt, cachedAt",

  stockSnapshots: "id, productId, warehouseId, [warehouseId+productId], cachedAt",

  salesDrafts: "id, status, warehouseId, updatedAt",

  transactions: "localId, idempotencyKey, status, transactionType, warehouseId, createdAt, updatedAt, serverId",

  syncQueue: "localId, status, nextAttemptAt, createdAt, updatedAt, [status+nextAttemptAt]",

  syncConflicts: "id, localId, conflictType, createdAt, resolvedAt",

  offlineSettings: "key, version, updatedAt",

  stockQuotas: "id, productId, warehouseId, allocationId, expiresAt, [warehouseId+productId]",

  syncMetadata: "key, updatedAt",
};
