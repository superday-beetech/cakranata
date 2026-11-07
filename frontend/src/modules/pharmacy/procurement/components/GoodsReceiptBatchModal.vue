<script setup lang="ts">
import { computed, reactive } from "vue";

import type { GoodsReceiptItem } from "../types/goodsReceipt";
import type { GoodsReceiptPostPayload, GoodsReceiptBatchRow } from "../services/goodsReceiptService";

const props = defineProps<{
  items: GoodsReceiptItem[];
  loading?: boolean;
}>();

const emit = defineEmits<{
  select: [payload: GoodsReceiptPostPayload];
  close: [];
}>();

const rows = reactive<Record<number, GoodsReceiptBatchRow[]>>({});

props.items.forEach((item) => {
  rows[item.id] = [
    {
      batch_number: "",
      quantity: Number(item.quantity_received),
      expired_at: null,
    },
  ];
});

const totals = computed(() => {
  const result: Record<number, number> = {};

  props.items.forEach((item) => {
    result[item.id] = (rows[item.id] ?? []).reduce((sum, batch) => sum + Number(batch.quantity || 0), 0);
  });

  return result;
});

function isQuantityValid(item: GoodsReceiptItem): boolean {
  const received = Number(item.quantity_received);
  const allocated = totals.value[item.id] ?? 0;

  return Math.abs(received - allocated) <= 0.0001;
}

const canSubmit = computed(() => {
  if (!props.items.length) return false;

  return props.items.every((item) => {
    const batches = rows[item.id] ?? [];

    if (!batches.length || !isQuantityValid(item)) {
      return false;
    }

    return batches.every((batch) => {
      return batch.batch_number.trim() !== "" && Number.isFinite(Number(batch.quantity)) && Number(batch.quantity) > 0;
    });
  });
});

function addBatch(itemId: number) {
  rows[itemId].push({
    batch_number: "",
    quantity: 0,
    expired_at: null,
  });
}

function removeBatch(itemId: number, index: number) {
  if (rows[itemId].length <= 1) return;

  rows[itemId].splice(index, 1);
}

function submit() {
  if (!canSubmit.value || props.loading) return;

  const batchBreakdown: Record<number, GoodsReceiptBatchRow[]> = {};

  props.items.forEach((item) => {
    batchBreakdown[item.id] = rows[item.id].map((batch) => ({
      batch_number: batch.batch_number.trim(),
      quantity: Number(batch.quantity),
      expired_at: batch.expired_at || null,
    }));
  });

  emit("select", {
    batch_breakdown: batchBreakdown,
  });
}
</script>

<template>
  <div>
    <div class="alert alert-info">
      <i class="ti ti-info-circle me-2"></i>
      Bagi jumlah penerimaan ke dalam satu atau beberapa batch. Total jumlah batch harus sama dengan jumlah yang diterima.
    </div>

    <div v-for="item in props.items" :key="item.id" class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <h6 class="mb-1">
            {{ item.product_name || `Produk #${item.product_id}` }}
          </h6>

          <small class="text-muted"> SKU: {{ item.sku || "-" }} </small>
        </div>

        <div class="text-end">
          <small class="text-muted d-block"> Jumlah diterima </small>

          <strong>
            {{ item.quantity_received }}
          </strong>
        </div>
      </div>

      <div class="card-body">
        <div v-for="(batch, index) in rows[item.id]" :key="index" class="row g-2 align-items-end mb-3">
          <div class="col-md-4">
            <label class="form-label"> Nomor Batch </label>

            <input v-model="batch.batch_number" type="text" class="form-control" placeholder="Contoh: BATCH-001" :disabled="loading" />
          </div>

          <div class="col-md-3">
            <label class="form-label"> Jumlah </label>

            <input v-model.number="batch.quantity" type="number" min="0.0001" step="0.0001" class="form-control" :disabled="loading" />
          </div>

          <div class="col-md-4">
            <label class="form-label"> Tanggal Kedaluwarsa </label>

            <input v-model="batch.expired_at" type="date" class="form-control" :disabled="loading" />
          </div>

          <div class="col-md-1">
            <button type="button" class="btn btn-light-danger w-100" :disabled="loading || rows[item.id].length <= 1" title="Hapus batch" @click="removeBatch(item.id, index)">
              <i class="ti ti-trash"></i>
            </button>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <button type="button" class="btn btn-sm btn-light-primary" :disabled="loading" @click="addBatch(item.id)">
            <i class="ti ti-plus me-1"></i>
            Tambah Batch
          </button>

          <div class="text-end">
            <small class="text-muted me-2"> Total alokasi: </small>

            <strong :class="isQuantityValid(item) ? 'text-success' : 'text-danger'">
              {{ totals[item.id] ?? 0 }}
              / {{ item.quantity_received }}
            </strong>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
      <button type="button" class="btn btn-light" :disabled="loading" @click="emit('close')">Batal</button>

      <button type="button" class="btn btn-primary" :disabled="!canSubmit || loading" @click="submit">
        <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>

        <i v-else class="ti ti-package-import me-1"></i>

        {{ loading ? "Memposting..." : "Posting Goods Receipt" }}
      </button>
    </div>
  </div>
</template>
