import Dexie, { type Table } from "dexie";

import { OFFLINE_DATABASE_NAME, OFFLINE_DATABASE_VERSION, OFFLINE_SCHEMA } from "./offlineSchema";

import type { ProductCache, StockSnapshot, SalesDraft, LocalTransaction, SyncQueueItem, SyncConflict, OfflineSetting, StockQuota, SyncMetadata } from "../types/offline";

class OfflineDatabase extends Dexie {
  products!: Table<ProductCache, number | string>;

  stockSnapshots!: Table<StockSnapshot, string>;

  salesDrafts!: Table<SalesDraft, string>;

  transactions!: Table<LocalTransaction, string>;

  syncQueue!: Table<SyncQueueItem, string>;

  syncConflicts!: Table<SyncConflict, string>;

  offlineSettings!: Table<OfflineSetting, string>;

  stockQuotas!: Table<StockQuota, string>;

  syncMetadata!: Table<SyncMetadata, string>;

  constructor() {
    super(OFFLINE_DATABASE_NAME);

    this.version(OFFLINE_DATABASE_VERSION).stores(OFFLINE_SCHEMA);
  }
}

export const offlineDb = new OfflineDatabase();

export default offlineDb;
