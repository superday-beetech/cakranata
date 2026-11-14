import { offlineDb } from "../database/offlineDatabase";

import type { ProductCache, StockSnapshot, SalesDraft, LocalTransaction, SyncQueueItem, SyncConflict, OfflineSetting, StockQuota, SyncMetadata, OfflineSalePayload, OfflineStockPolicySnapshot } from "../types/offline";

import type { CreateSalePayload, ProductSearchResult } from "@/modules/sales/types/sales";

// =============================================================
// HELPERS
// =============================================================

function now(): string {
  return new Date().toISOString();
}

function createId(): string {
  return crypto.randomUUID();
}

export function buildStockSnapshotId(warehouseId: number | string, productId: number | string): string {
  return JSON.stringify([warehouseId, productId]);
}

// =============================================================
// PRODUCT CACHE
// =============================================================

export async function cachePosProducts(products: ProductSearchResult[]): Promise<void> {
  const cachedAt = now();

  const rows: ProductCache[] = products.map((product) => ({
    id: product.id,
    barcode: product.barcode,
    name: product.name,
    sku: product.sku,
    unitName: product.unit_name ?? null,
    price: Number(product.selling_price),
    isActive: product.is_active,
    updatedAt: cachedAt,
    cachedAt,
    rawData: { ...product },
  }));

  await offlineDb.products.bulkPut(rows);
}

export async function searchCachedPosProducts(keyword: string, warehouseId?: number | string): Promise<ProductSearchResult[]> {
  const normalizedKeyword = keyword.trim().toLowerCase();

  const products = await offlineDb.products.toArray();

  const matchedProducts = products.filter((product) => {
    if (!product.isActive) return false;

    if (!normalizedKeyword) return true;

    return product.name.toLowerCase().includes(normalizedKeyword) || (product.sku ?? "").toLowerCase().includes(normalizedKeyword) || (product.barcode ?? "").toLowerCase().includes(normalizedKeyword);
  });

  return Promise.all(
    matchedProducts.map(async (product) => {
      const stock = warehouseId !== undefined ? await offlineDb.stockSnapshots.get(buildStockSnapshotId(warehouseId, product.id)) : undefined;

      return {
        id: Number(product.id),
        sku: product.sku ?? "",
        barcode: product.barcode ?? null,
        name: product.name,
        unit_name: product.unitName ?? undefined,
        selling_price: product.price ?? 0,
        available_stock: stock?.quantity,
        is_active: product.isActive,
      };
    }),
  );
}

// =============================================================
// STOCK CACHE
// =============================================================

export async function cacheStockSnapshot(snapshot: Omit<StockSnapshot, "id">): Promise<void> {
  const row: StockSnapshot = {
    ...snapshot,
    id: buildStockSnapshotId(snapshot.warehouseId, snapshot.productId),
  };

  await offlineDb.stockSnapshots.put(row);
}

export async function getCachedStock(warehouseId: number | string, productId: number | string): Promise<StockSnapshot | undefined> {
  return offlineDb.stockSnapshots.get(buildStockSnapshotId(warehouseId, productId));
}

// =============================================================
// SALES DRAFT
// =============================================================

export async function saveSalesDraft(draft: SalesDraft): Promise<void> {
  await offlineDb.salesDrafts.put({
    ...draft,
    updatedAt: now(),
  });
}

export async function getSalesDraft(id: string): Promise<SalesDraft | undefined> {
  return offlineDb.salesDrafts.get(id);
}

export async function getAllSalesDrafts(): Promise<SalesDraft[]> {
  return offlineDb.salesDrafts.orderBy("updatedAt").reverse().toArray();
}

export async function deleteSalesDraft(id: string): Promise<void> {
  await offlineDb.salesDrafts.delete(id);
}

// =============================================================
// OFFLINE SALE TRANSACTION
// =============================================================

export async function saveOfflineSale(payload: OfflineSalePayload, warehouseId: number | string, policySnapshot: OfflineStockPolicySnapshot): Promise<LocalTransaction> {
  const timestamp = now();
  const localId = createId();

  const transaction: LocalTransaction = {
    localId,
    idempotencyKey: createId(),
    transactionType: "sale",
    status: "pending",
    warehouseId,
    policySnapshot,
    payload,
    createdAt: timestamp,
    updatedAt: timestamp,
  };

  const queueItem: SyncQueueItem = {
    localId,
    status: "pending",
    attempts: 0,
    createdAt: timestamp,
    updatedAt: timestamp,
  };

  await offlineDb.transaction("rw", offlineDb.transactions, offlineDb.syncQueue, async () => {
    await offlineDb.transactions.add(transaction);
    await offlineDb.syncQueue.add(queueItem);
  });

  return transaction;
}

// =============================================================
// SYNC QUEUE
// =============================================================

export async function getPendingSyncQueue(): Promise<SyncQueueItem[]> {
  return offlineDb.syncQueue.where("status").equals("pending").sortBy("createdAt");
}

export async function getLocalTransaction(localId: string): Promise<LocalTransaction | undefined> {
  return offlineDb.transactions.get(localId);
}

export async function markTransactionSyncing(localId: string): Promise<void> {
  const timestamp = now();

  await offlineDb.transaction("rw", offlineDb.transactions, offlineDb.syncQueue, async () => {
    await offlineDb.transactions.update(localId, {
      status: "syncing",
      updatedAt: timestamp,
    });

    await offlineDb.syncQueue.update(localId, {
      status: "processing",
      attempts: (await offlineDb.syncQueue.get(localId))?.attempts ?? 0,
      lastAttemptAt: timestamp,
      updatedAt: timestamp,
    });
  });
}
