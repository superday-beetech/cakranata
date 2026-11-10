<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";

import api from "@/api/client";
import { notify } from "@/utils/notification";

import distributionService from "../services/distributionService";
import deliveryService from "../services/deliveryService";

import type { Delivery, DeliveryAllocationPayload, DeliveryItem, DistributionOrderDetail } from "../types/distribution";

interface DeliveryWithItems extends Delivery {
  items: DeliveryItem[];
}

interface DeliveryDetailResponse {
  delivery: Delivery;
  order_items: DistributionOrderDetail["items"];
  items: DeliveryItem[];
}

interface ProductBatch {
  id: number;
  product_id: number;
  batch_number: string;
  expired_at: string | null;
  quantity_remaining: number | string;
  status: string;
  product_name?: string;
}

interface AllocationRow {
  batch_id: number | null;
  quantity: number | null;
}

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const posting = ref(false);

const orderDetail = ref<DistributionOrderDetail | null>(null);
const deliveryDetail = ref<DeliveryDetailResponse | null>(null);
const batches = ref<ProductBatch[]>([]);

const allocations = reactive<Record<number, AllocationRow[]>>({});

const deliveryId = computed(() => Number(route.query.id));
const orderId = computed(() => Number(route.query.order_id));

const order = computed(() => orderDetail.value?.order ?? null);
const orderItems = computed(() => deliveryDetail.value?.order_items ?? []);
const delivery = computed<DeliveryWithItems | null>(() => {
  if (!deliveryDetail.value) return null;

  return {
    ...deliveryDetail.value.delivery,
    items: deliveryDetail.value.items ?? [],
  };
});

const previousDeliveries = computed(() => {
  return (orderDetail.value as any)?.deliveries ?? [];
});

function getPreviouslyDispatched(distributionItemId: number): number {
  let total = 0;

  for (const doc of previousDeliveries.value) {
    // Abaikan delivery yang sedang dibuka.
    if (Number(doc.id) === deliveryId.value) continue;

    if (doc.status !== "posted") continue;

    for (const item of doc.items ?? []) {
      if (Number(item.distribution_item_id) === distributionItemId) {
        total += Number(item.quantity_dispatched || 0);
      }
    }
  }

  return total;
}

function getDispatchableQuantity(item: { id: number | string; quantity: number | string }): number {
  return Math.max(0, Number(item.quantity) - getPreviouslyDispatched(Number(item.id)));
}

const availableBatches = computed(() => {
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  return batches.value
    .filter((batch) => {
      const remaining = Number(batch.quantity_remaining || 0);

      if (remaining <= 0) return false;

      if (batch.status && batch.status !== "available") {
        return false;
      }

      if (batch.expired_at) {
        const expiry = new Date(`${batch.expired_at.substring(0, 10)}T00:00:00`);

        if (!Number.isNaN(expiry.getTime()) && expiry < today) {
          return false;
        }
      }

      return true;
    })
    .sort((a, b) => {
      // Batch dengan tanggal kedaluwarsa terdekat diprioritaskan.
      if (!a.expired_at) return 1;
      if (!b.expired_at) return -1;

      return a.expired_at.localeCompare(b.expired_at);
    });
});

const totalAllocated = computed(() => Object.values(allocations).reduce((total, rows) => total + rows.reduce((sum, row) => sum + Number(row.quantity || 0), 0), 0));

const canPost = computed(() => {
  return !!delivery.value && delivery.value.status === "draft" && totalAllocated.value > 0;
});

function extractItems(response: any): any[] {
  const candidates = [response?.data?.data?.items, response?.data?.items, response?.data?.data, response?.data];

  return candidates.find(Array.isArray) ?? [];
}

function formatCurrency(value: number | string): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(Number(value || 0));
}

function formatNumber(value: number | string): string {
  return new Intl.NumberFormat("id-ID", {
    maximumFractionDigits: 3,
  }).format(Number(value || 0));
}

function formatDate(value: string | null | undefined): string {
  if (!value) return "-";

  const date = new Date(`${value.substring(0, 10)}T00:00:00`);

  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat("id-ID", {
    day: "2-digit",
    month: "long",
    year: "numeric",
  }).format(date);
}

function deliveryStatusLabel(status: string): string {
  const labels: Record<string, string> = {
    draft: "Draft",
    posted: "Sudah Diposting",
    cancelled: "Dibatalkan",
  };

  return labels[status] ?? status;
}

function deliveryStatusClass(status: string): string {
  const classes: Record<string, string> = {
    draft: "bg-light text-secondary",
    posted: "bg-success-subtle text-success",
    cancelled: "bg-danger-subtle text-danger",
  };

  return classes[status] ?? "bg-light text-secondary";
}

function getBatchesForProduct(productId: number | string) {
  return availableBatches.value.filter((batch) => Number(batch.product_id) === Number(productId));
}

function getBatch(id: number | null) {
  if (!id) return undefined;

  return batches.value.find((batch) => Number(batch.id) === Number(id));
}

function addAllocation(distributionItemId: number) {
  if (!allocations[distributionItemId]) {
    allocations[distributionItemId] = [];
  }

  allocations[distributionItemId].push({
    batch_id: null,
    quantity: null,
  });
}

function removeAllocation(distributionItemId: number, index: number) {
  allocations[distributionItemId]?.splice(index, 1);
}

function getAllocatedForBatch(batchId: number, currentRow?: AllocationRow) {
  let total = 0;

  for (const rows of Object.values(allocations)) {
    for (const row of rows) {
      if (row === currentRow) continue;

      if (Number(row.batch_id) === Number(batchId)) {
        total += Number(row.quantity || 0);
      }
    }
  }

  return total;
}

function getBatchRemaining(batch: ProductBatch, currentRow: AllocationRow) {
  return Math.max(0, Number(batch.quantity_remaining) - getAllocatedForBatch(Number(batch.id), currentRow));
}

function getItemAllocated(distributionItemId: number) {
  return (allocations[distributionItemId] ?? []).reduce((total, row) => total + Number(row.quantity || 0), 0);
}

function getItemRemaining(item: { id: number | string; quantity: number | string }): number {
  return Math.max(0, getDispatchableQuantity(item) - getItemAllocated(Number(item.id)));
}

/**
 * Untuk dokumen yang sudah diposting, tampilkan kembali
 * batch dan kuantitas yang sudah tercatat di delivery_items.
 */
function initializePostedAllocations(items: DeliveryItem[]) {
  for (const item of items) {
    const distributionItemId = Number(item.distribution_item_id);

    if (!allocations[distributionItemId]) {
      allocations[distributionItemId] = [];
    }

    allocations[distributionItemId].push({
      batch_id: Number(item.batch_id),
      quantity: Number(item.quantity_dispatched),
    });
  }
}

async function loadBatches() {
  try {
    const response = await api.get("/pharmacy/inventory-api/batches", {
      params: {
        per_page: 1000,
      },
    });

    batches.value = extractItems(response).map((item: any) => ({
      id: Number(item.id),
      product_id: Number(item.product_id),
      batch_number: String(item.batch_number ?? ""),
      expired_at: item.expired_at ?? null,
      quantity_remaining: item.quantity_remaining ?? 0,
      status: String(item.status ?? ""),
      product_name: item.product_name ?? "",
    }));
  } catch (error: any) {
    notify.error(error?.response?.data?.message || error?.message || "Gagal memuat daftar batch.");
  }
}

async function loadDetail() {
  if (!Number.isInteger(deliveryId.value) || deliveryId.value <= 0 || !Number.isInteger(orderId.value) || orderId.value <= 0) {
    notify.error("ID pengiriman atau pesanan tidak valid.");
    goBack();
    return;
  }

  loading.value = true;

  try {
    const [orderResponse, deliveryResponse] = await Promise.all([distributionService.getOrderDetail(orderId.value), deliveryService.getDeliveryDetail(deliveryId.value)]);

    orderDetail.value = orderResponse;
    deliveryDetail.value = deliveryResponse;

    if (Number(deliveryResponse.delivery.distribution_order_id) !== orderId.value) {
      throw new Error("Dokumen pengiriman tidak terkait dengan pesanan tersebut.");
    }

    for (const item of orderItems.value) {
      const itemId = Number(item.id);

      allocations[itemId] = [];

      if (deliveryResponse.delivery.status === "draft") {
        allocations[itemId].push({
          batch_id: null,
          quantity: null,
        });
      }
    }

    if (deliveryResponse.delivery.status !== "draft") {
      initializePostedAllocations(deliveryResponse.items ?? []);
    }

    await loadBatches();
  } catch (error: any) {
    notify.error(error?.response?.data?.message || error?.message || "Gagal memuat detail pengiriman.");
  } finally {
    loading.value = false;
  }
}

function buildAllocationPayload(): DeliveryAllocationPayload {
  const payload: DeliveryAllocationPayload = {};

  for (const [itemId, rows] of Object.entries(allocations)) {
    const validRows = rows.filter((row) => Number(row.batch_id) > 0 && Number(row.quantity) > 0);

    if (validRows.length > 0) {
      payload[Number(itemId)] = validRows.map((row) => ({
        batch_id: Number(row.batch_id),
        quantity: Number(row.quantity),
      }));
    }
  }

  return payload;
}

function validateAllocations(): boolean {
  for (const item of orderItems.value) {
    const itemId = Number(item.id);
    const rows = allocations[itemId] ?? [];

    let itemTotal = 0;

    for (const row of rows) {
      if (!row.batch_id && !row.quantity) continue;

      if (!row.batch_id || !row.quantity || Number(row.quantity) <= 0) {
        notify.warning("Pilih batch dan isi kuantitas pada setiap alokasi.");
        return false;
      }

      const batch = getBatch(row.batch_id);

      if (!batch) {
        notify.warning("Batch yang dipilih tidak ditemukan.");
        return false;
      }

      if (Number(batch.product_id) !== Number(item.product_id)) {
        notify.warning("Batch tidak sesuai dengan produk pesanan.");
        return false;
      }

      if (Number(row.quantity) > getBatchRemaining(batch, row)) {
        notify.warning(`Kuantitas melebihi stok tersedia pada batch ${batch.batch_number}.`);
        return false;
      }

      itemTotal += Number(row.quantity);
    }

    if (itemTotal > getDispatchableQuantity(item)) {
      notify.warning(`Total alokasi melebihi kuantitas pesanan untuk ${item.product_name || `produk #${item.product_id}`}.`);
      return false;
    }
  }

  return true;
}

async function postDelivery() {
  if (!delivery.value || !canPost.value) return;

  if (!validateAllocations()) return;

  const payload = buildAllocationPayload();

  if (Object.keys(payload).length === 0) {
    notify.warning("Belum ada alokasi batch untuk dikirim.");
    return;
  }

  const confirmed = window.confirm("Posting pengiriman sekarang? Stok batch akan dikurangi sesuai alokasi.");

  if (!confirmed) return;

  posting.value = true;

  try {
    await deliveryService.postDelivery(deliveryId.value, payload);

    notify.success("Pengiriman berhasil diposting.");

    router.push({
      path: "/pharmacy/distribution/distribution-order-detail",
      query: {
        id: String(orderId.value),
      },
    });
  } catch (error: any) {
    notify.error(error?.response?.data?.message || error?.message || "Gagal memposting pengiriman.");
  } finally {
    posting.value = false;
  }
}

function goBack() {
  if (orderId.value > 0) {
    router.push({
      path: "/pharmacy/distribution/distribution-order-detail",
      query: {
        id: String(orderId.value),
      },
    });

    return;
  }

  router.push("/pharmacy/distribution/distribution-orders");
}

onMounted(loadDetail);
</script>

<template>
  <div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <h4 class="mb-1">Detail Pengiriman</h4>
        <p class="text-muted mb-0">Alokasi batch dan kuantitas pengiriman.</p>
      </div>

      <button type="button" class="btn btn-light" @click="goBack">
        <i class="ti ti-arrow-left me-1"></i>
        Kembali
      </button>
    </div>

    <div v-if="loading" class="card">
      <div class="card-body text-center py-5">
        <div class="spinner-border text-primary"></div>
        <p class="text-muted mt-2 mb-0">Memuat detail pengiriman...</p>
      </div>
    </div>

    <template v-else-if="delivery && order">
      <!-- Delivery Information -->
      <div class="card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <h5 class="mb-0">Informasi Dokumen Pengiriman</h5>

          <span class="badge" :class="deliveryStatusClass(delivery.status)">
            {{ deliveryStatusLabel(delivery.status) }}
          </span>
        </div>

        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="text-muted small"> Nomor Pengiriman </label>
              <div class="fw-semibold">
                {{ delivery.delivery_number }}
              </div>
            </div>

            <div class="col-md-4">
              <label class="text-muted small"> Nomor Pesanan </label>
              <div class="fw-semibold">
                {{ order.order_number }}
              </div>
            </div>

            <div class="col-md-4">
              <label class="text-muted small"> Tanggal Pengiriman </label>
              <div>
                {{ formatDate(delivery.delivery_date) }}
              </div>
            </div>

            <div class="col-md-6">
              <label class="text-muted small"> Penerima </label>
              <div>
                {{ delivery.recipient_name || "-" }}
              </div>
            </div>

            <div class="col-md-6">
              <label class="text-muted small"> Nomor Telepon </label>
              <div>
                {{ delivery.recipient_phone || "-" }}
              </div>
            </div>

            <div class="col-12">
              <label class="text-muted small"> Alamat Pengiriman </label>
              <div>
                {{ delivery.shipping_address || "-" }}
              </div>
            </div>

            <div class="col-12">
              <label class="text-muted small"> Catatan </label>
              <div>
                {{ delivery.notes || "-" }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Allocation -->
      <div class="card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div>
            <h5 class="mb-0">Alokasi Batch</h5>
            <small class="text-muted"> Pilih batch untuk setiap produk yang akan dikirim. </small>
          </div>

          <span class="badge bg-light text-secondary"> Total alokasi: {{ formatNumber(totalAllocated) }} </span>
        </div>

        <div class="card-body">
          <div v-if="delivery.status !== 'draft'" class="alert alert-info mb-0">Dokumen ini sudah diproses. Alokasi batch tidak dapat diubah.</div>

          <template v-else>
            <div v-for="item in orderItems" :key="String(item.id)" class="border rounded p-3 mb-3">
              <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                <div>
                  <h6 class="mb-1">
                    {{ item.product_name || `Produk #${item.product_id}` }}
                  </h6>
                  <small class="text-muted">
                    {{ item.sku || "-" }}
                  </small>
                </div>

                <div class="text-end">
                  <div class="small text-muted">Kuantitas pesanan</div>
                  <strong>{{ formatNumber(item.quantity) }}</strong>

                  <div class="small text-muted mt-1">
                    Belum dikirim:
                    <strong class="text-primary">
                      {{ formatNumber(getDispatchableQuantity(item)) }}
                    </strong>
                  </div>
                </div>
              </div>

              <div class="row g-2 align-items-end">
                <div class="col-md-5">
                  <label class="form-label small">Batch</label>
                </div>

                <div class="col-md-3">
                  <label class="form-label small"> Kuantitas Kirim </label>
                </div>

                <div class="col-md-3">
                  <label class="form-label small"> Stok Tersedia </label>
                </div>

                <div class="col-md-1"></div>
              </div>

              <div v-for="(row, index) in allocations[Number(item.id)]" :key="index" class="row g-2 align-items-end mb-2">
                <div class="col-md-5">
                  <select v-model.number="row.batch_id" class="form-select" :disabled="posting">
                    <option :value="null">Pilih batch</option>

                    <option v-for="batch in getBatchesForProduct(item.product_id)" :key="batch.id" :value="batch.id" :disabled="getBatchRemaining(batch, row) <= 0 && Number(row.batch_id) !== Number(batch.id)">
                      {{ batch.batch_number }}
                      — Sisa
                      {{ formatNumber(getBatchRemaining(batch, row)) }}
                      — Exp:
                      {{ formatDate(batch.expired_at) }}
                    </option>
                  </select>
                </div>

                <div class="col-md-3">
                  <input
                    v-model.number="row.quantity"
                    type="number"
                    class="form-control"
                    min="0.001"
                    step="0.001"
                    placeholder="0"
                    :max="row.batch_id && getBatch(Number(row.batch_id)) ? getBatchRemaining(getBatch(Number(row.batch_id))!, row) : undefined"
                    :disabled="posting"
                  />
                </div>

                <div class="col-md-3">
                  <input type="text" class="form-control" readonly :value="row.batch_id && getBatch(Number(row.batch_id)) ? formatNumber(getBatchRemaining(getBatch(Number(row.batch_id))!, row)) : '-'" />
                </div>

                <div class="col-md-1">
                  <button type="button" class="btn btn-light text-danger w-100" title="Hapus alokasi" :disabled="posting" @click="removeAllocation(Number(item.id), index)">
                    <i class="ti ti-trash"></i>
                  </button>
                </div>
              </div>

              <div class="d-flex flex-wrap justify-content-between align-items-center mt-3">
                <button type="button" class="btn btn-sm btn-light" :disabled="posting" @click="addAllocation(Number(item.id))">
                  <i class="ti ti-plus me-1"></i>
                  Tambah Batch
                </button>

                <div class="small text-muted">
                  Dialokasikan:
                  <strong>
                    {{ formatNumber(getItemAllocated(Number(item.id))) }}
                  </strong>
                  /
                  {{ formatNumber(item.quantity) }}
                  · Sisa:
                  <strong>
                    {{ formatNumber(getItemRemaining(item)) }}
                  </strong>
                </div>
              </div>

              <div v-if="getBatchesForProduct(item.product_id).length === 0" class="alert alert-warning mt-3 mb-0">Tidak ada batch dengan stok tersedia dan belum kedaluwarsa untuk produk ini.</div>
            </div>

            <div v-if="orderItems.length === 0" class="text-center text-muted py-4">Tidak ada item pada pesanan ini.</div>
          </template>
        </div>
      </div>

      <!-- Footer Actions -->
      <div class="card">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div>
            <div class="text-muted small">Total kuantitas yang dialokasikan</div>
            <h5 class="mb-0">
              {{ formatNumber(totalAllocated) }}
            </h5>
          </div>

          <div class="d-flex gap-2">
            <button type="button" class="btn btn-light" @click="goBack">Kembali</button>

            <button v-if="delivery.status === 'draft'" type="button" class="btn btn-primary" :disabled="posting || !canPost" @click="postDelivery">
              <span v-if="posting" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="ti ti-check me-1"></i>
              {{ posting ? "Memproses..." : "Posting Pengiriman" }}
            </button>
          </div>
        </div>
      </div>
    </template>

    <div v-else class="card">
      <div class="card-body text-center py-5">
        <i class="ti ti-file-off fs-1 text-muted"></i>
        <h6 class="mt-3">Dokumen pengiriman tidak ditemukan</h6>
        <button type="button" class="btn btn-light mt-2" @click="goBack">Kembali</button>
      </div>
    </div>
  </div>
</template>
