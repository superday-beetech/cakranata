<script setup lang="ts">
import { onMounted, ref } from "vue";

import GoodsReceiptBatchModal from "./GoodsReceiptBatchModal.vue";
import goodsReceiptService, { type GoodsReceiptPostPayload } from "../services/goodsReceiptService";

import type { GoodsReceipt } from "../types/goodsReceipt";

import { notify } from "@/utils/notification";

const props = defineProps<{
  receiptId: number;
}>();

const emit = defineEmits<{
  select: [id: number];
  close: [];
}>();

const receipt = ref<GoodsReceipt | null>(null);
const loading = ref(false);
const posting = ref(false);
const showBatchForm = ref(false);

const formatNumber = (value: number) => new Intl.NumberFormat("id-ID").format(Number(value) || 0);

async function loadDetail() {
  loading.value = true;

  try {
    receipt.value = (await goodsReceiptService.getById(props.receiptId)) ?? null;
  } catch (error: any) {
    notify.error(error?.response?.data?.message || error?.message || "Gagal memuat detail Goods Receipt.");
  } finally {
    loading.value = false;
  }
}

function openBatchForm() {
  if (!receipt.value || receipt.value.status !== "draft" || !receipt.value.items?.length) {
    return;
  }

  showBatchForm.value = true;
}

async function handleBatchPosting(payload: GoodsReceiptPostPayload) {
  if (!receipt.value || receipt.value.status !== "draft" || posting.value) {
    return;
  }

  posting.value = true;

  try {
    await goodsReceiptService.post(props.receiptId, payload);

    notify.success("Goods Receipt berhasil diposting.");

    showBatchForm.value = false;

    await loadDetail();

    emit("select", props.receiptId);
  } catch (error: any) {
    notify.error(error?.response?.data?.message || error?.message || "Gagal mem-posting Goods Receipt.");
  } finally {
    posting.value = false;
  }
}

onMounted(loadDetail);
</script>

<template>
  <div>
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border"></div>

      <div class="mt-2">Memuat detail penerimaan...</div>
    </div>

    <div v-else-if="receipt">
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <small class="text-muted"> Nomor Penerimaan </small>

          <div class="fw-semibold">
            {{ receipt.receipt_number }}
          </div>
        </div>

        <div class="col-md-6">
          <small class="text-muted"> Status </small>

          <div>
            <span class="badge" :class="receipt.status === 'posted' ? 'bg-success' : receipt.status === 'draft' ? 'bg-warning text-dark' : 'bg-secondary'">
              {{ receipt.status }}
            </span>
          </div>
        </div>

        <div class="col-md-6">
          <small class="text-muted"> Nomor Purchase Order </small>

          <div class="fw-semibold">
            {{ receipt.order_number || "-" }}
          </div>
        </div>

        <div class="col-md-6">
          <small class="text-muted"> Supplier </small>

          <div class="fw-semibold">
            {{ receipt.supplier_name || "-" }}
          </div>
        </div>

        <div class="col-md-6">
          <small class="text-muted"> Tanggal Penerimaan </small>

          <div>
            {{ receipt.received_date }}
          </div>
        </div>

        <div class="col-md-6">
          <small class="text-muted"> Nomor Surat Jalan </small>

          <div>
            {{ receipt.supplier_delivery_number || "-" }}
          </div>
        </div>
      </div>

      <h6 class="fw-semibold mb-3">Detail Barang</h6>

      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th>Produk</th>
              <th class="text-end">Jumlah Diterima</th>
              <th class="text-end">Harga Satuan</th>
              <th>Catatan</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="item in receipt.items || []" :key="item.id">
              <td>
                <div class="fw-semibold">
                  {{ item.product_name || `Produk #${item.product_id}` }}
                </div>

                <small class="text-muted">
                  {{ item.sku || "" }}
                </small>
              </td>

              <td class="text-end">
                {{ formatNumber(item.quantity_received) }}
              </td>

              <td class="text-end">
                {{ formatNumber(item.unit_cost) }}
              </td>

              <td>
                {{ item.notes || "-" }}
              </td>
            </tr>

            <tr v-if="!receipt.items?.length">
              <td colspan="4" class="text-center text-muted">Tidak ada detail barang.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mb-3">
        <small class="text-muted"> Catatan </small>

        <div>
          {{ receipt.notes || "-" }}
        </div>
      </div>

      <!-- Form Pembagian Batch -->
      <div v-if="showBatchForm && receipt.status === 'draft'" class="border rounded p-3 mb-3">
        <h6 class="fw-semibold mb-3">Pembagian Batch</h6>

        <GoodsReceiptBatchModal :items="receipt.items || []" :loading="posting" @select="handleBatchPosting" @close="showBatchForm = false" />
      </div>

      <!-- Footer -->
      <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-light" :disabled="posting" @click="emit('close')">Tutup</button>

        <button v-if="receipt.status === 'draft' && !showBatchForm" type="button" class="btn btn-success" :disabled="posting || !receipt.items?.length" @click="openBatchForm">
          <i class="ti ti-package-import me-1"></i>
          Posting Penerimaan
        </button>
      </div>
    </div>

    <div v-else class="alert alert-warning">Data Goods Receipt tidak ditemukan.</div>
  </div>
</template>
