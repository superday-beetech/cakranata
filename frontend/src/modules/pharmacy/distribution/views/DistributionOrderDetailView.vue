<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";

import distributionService from "../services/distributionService";
import type { DistributionOrderDetail, Delivery, DeliveryItem } from "../types/distribution";

import { notify } from "@/utils/notification";

interface DeliveryWithItems extends Delivery {
  items?: DeliveryItem[];
}

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const detail = ref<DistributionOrderDetail | null>(null);

const orderId = computed(() => Number(route.query.id));

const deliveries = computed<DeliveryWithItems[]>(() => {
  return (detail.value?.deliveries ?? []) as DeliveryWithItems[];
});

const orderItems = computed(() => detail.value?.items ?? []);

const totalOrderedQuantity = computed(() => {
  return orderItems.value.reduce((total, item) => total + Number(item.quantity || 0), 0);
});

const totalDeliveredQuantity = computed(() => {
  return deliveries.value
    .filter((delivery) => delivery.status === "posted")
    .reduce((total, delivery) => {
      const items = delivery.items ?? [];

      return total + items.reduce((sum, item) => sum + Number(item.quantity_dispatched || 0), 0);
    }, 0);
});

const remainingQuantity = computed(() => {
  return Math.max(0, totalOrderedQuantity.value - totalDeliveredQuantity.value);
});

const canCreateDelivery = computed(() => {
  const status = detail.value?.order.status;

  return status !== "completed" && status !== "cancelled" && remainingQuantity.value > 0;
});

function formatCurrency(value: number | string | null | undefined): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(Number(value || 0));
}

function formatNumber(value: number | string | null | undefined): string {
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

function orderStatusLabel(status: string): string {
  const labels: Record<string, string> = {
    draft: "Draft",
    partially_delivered: "Sebagian Terkirim",
    completed: "Selesai",
    cancelled: "Dibatalkan",
  };

  return labels[status] ?? status;
}

function orderStatusClass(status: string): string {
  const classes: Record<string, string> = {
    draft: "bg-light text-secondary",
    partially_delivered: "bg-warning-subtle text-warning",
    completed: "bg-success-subtle text-success",
    cancelled: "bg-danger-subtle text-danger",
  };

  return classes[status] ?? "bg-light text-secondary";
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

async function loadDetail() {
  if (!Number.isInteger(orderId.value) || orderId.value <= 0) {
    detail.value = null;
    notify.error("ID pesanan tidak valid.");
    router.replace("/pharmacy/distribution/distribution-order");
    return;
  }

  loading.value = true;

  try {
    detail.value = await distributionService.getOrderDetail(orderId.value);
  } catch (error: any) {
    detail.value = null;

    notify.error(error?.response?.data?.message || error?.message || "Gagal memuat detail pesanan.");
  } finally {
    loading.value = false;
  }
}

function createDelivery() {
  if (!detail.value || !canCreateDelivery.value) return;

  router.push({
    path: "/pharmacy/distribution/delivery-form",
    query: {
      order_id: String(detail.value.order.id),
    },
  });
}

function openDelivery(delivery: Delivery) {
  if (!detail.value) return;

  router.push({
    path: "/pharmacy/distribution/delivery-detail",
    query: {
      id: String(delivery.id),
      order_id: String(detail.value.order.id),
    },
  });
}

function goBack() {
  router.push("/pharmacy/distribution/distribution-order");
}

watch(
  () => route.query.id,
  (newId, oldId) => {
    if (newId !== oldId) {
      loadDetail();
    }
  },
);

onMounted(loadDetail);
</script>

<template>
  <div class="container-fluid">
    <!-- Loading -->
    <div v-if="loading" class="card">
      <div class="card-body text-center py-5">
        <div class="spinner-border text-primary"></div>
        <p class="text-muted mt-2 mb-0">Memuat detail pesanan...</p>
      </div>
    </div>

    <template v-else-if="detail">
      <!-- Header -->
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
          <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
            <h4 class="mb-0">
              {{ detail.order.order_number }}
            </h4>

            <span class="badge" :class="orderStatusClass(detail.order.status)">
              {{ orderStatusLabel(detail.order.status) }}
            </span>
          </div>

          <p class="text-muted mb-0">Detail pesanan distribusi</p>
        </div>

        <div class="d-flex gap-2">
          <button type="button" class="btn btn-light" @click="goBack">
            <i class="ti ti-arrow-left me-1"></i>
            Kembali
          </button>

          <button type="button" class="btn btn-primary" :disabled="!canCreateDelivery" @click="createDelivery">
            <i class="ti ti-truck-delivery me-1"></i>
            Buat Pengiriman
          </button>
        </div>
      </div>

      <!-- Summary -->
      <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
          <div class="card h-100">
            <div class="card-body">
              <p class="text-muted mb-1">Total Pesanan</p>

              <h4 class="mb-0">
                {{ formatNumber(totalOrderedQuantity) }}
              </h4>
            </div>
          </div>
        </div>

        <div class="col-sm-6 col-xl-3">
          <div class="card h-100">
            <div class="card-body">
              <p class="text-muted mb-1">Total Terkirim</p>

              <h4 class="mb-0 text-success">
                {{ formatNumber(totalDeliveredQuantity) }}
              </h4>
            </div>
          </div>
        </div>

        <div class="col-sm-6 col-xl-3">
          <div class="card h-100">
            <div class="card-body">
              <p class="text-muted mb-1">Sisa Pengiriman</p>

              <h4 class="mb-0 text-warning">
                {{ formatNumber(remainingQuantity) }}
              </h4>
            </div>
          </div>
        </div>

        <div class="col-sm-6 col-xl-3">
          <div class="card h-100">
            <div class="card-body">
              <p class="text-muted mb-1">Nilai Pesanan</p>

              <h5 class="mb-0">
                {{ formatCurrency(detail.order.total_amount) }}
              </h5>
            </div>
          </div>
        </div>
      </div>

      <!-- Order Information -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Informasi Pesanan</h5>
        </div>

        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="text-muted small"> Nomor Pesanan </label>

              <div class="fw-semibold">
                {{ detail.order.order_number }}
              </div>
            </div>

            <div class="col-md-6">
              <label class="text-muted small"> Pelanggan </label>

              <div class="fw-semibold">
                {{ detail.order.customer_name || `Pelanggan #${detail.order.customer_id}` }}
              </div>
            </div>

            <div class="col-md-6">
              <label class="text-muted small"> Tanggal Pesanan </label>

              <div>
                {{ formatDate(detail.order.order_date) }}
              </div>
            </div>

            <div class="col-md-6">
              <label class="text-muted small"> Rencana Pengiriman </label>

              <div>
                {{ formatDate(detail.order.requested_delivery_date) }}
              </div>
            </div>

            <div class="col-12">
              <label class="text-muted small"> Catatan </label>

              <div>
                {{ detail.order.notes || "-" }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Product Items -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Detail Produk Pesanan</h5>
        </div>

        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>Produk</th>
                  <th class="text-end">Kuantitas</th>
                  <th class="text-end">Harga Satuan</th>
                  <th class="text-end">Diskon</th>
                  <th class="text-end">Subtotal</th>
                </tr>
              </thead>

              <tbody>
                <tr v-for="item in orderItems" :key="String(item.id)">
                  <td>
                    <div class="fw-semibold">
                      {{ item.product_name || `Produk #${item.product_id}` }}
                    </div>

                    <small class="text-muted">
                      {{ item.sku || "-" }}
                    </small>
                  </td>

                  <td class="text-end">
                    {{ formatNumber(item.quantity) }}
                  </td>

                  <td class="text-end">
                    {{ formatCurrency(item.unit_price) }}
                  </td>

                  <td class="text-end">
                    {{ formatCurrency(item.discount_amount) }}
                  </td>

                  <td class="text-end fw-semibold">
                    {{ formatCurrency(item.subtotal) }}
                  </td>
                </tr>

                <tr v-if="orderItems.length === 0">
                  <td colspan="5" class="text-center text-muted py-4">Belum ada detail produk.</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Order Totals -->
          <div class="row justify-content-end">
            <div class="col-md-6 col-lg-4">
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Subtotal</span>

                <span>
                  {{ formatCurrency(detail.order.subtotal) }}
                </span>
              </div>

              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Diskon</span>

                <span>
                  {{ formatCurrency(detail.order.discount_amount) }}
                </span>
              </div>

              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Pajak</span>

                <span>
                  {{ formatCurrency(detail.order.tax_amount) }}
                </span>
              </div>

              <hr />

              <div class="d-flex justify-content-between">
                <strong>Total</strong>

                <strong class="text-primary">
                  {{ formatCurrency(detail.order.total_amount) }}
                </strong>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Delivery History -->
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Riwayat Pengiriman</h5>

          <span class="badge bg-light text-secondary"> {{ deliveries.length }} Dokumen </span>
        </div>

        <div class="card-body">
          <div v-if="deliveries.length === 0" class="text-center py-4">
            <i class="ti ti-truck-off fs-1 text-muted"></i>

            <p class="text-muted mt-2 mb-0">Belum ada dokumen pengiriman untuk pesanan ini.</p>
          </div>

          <div v-else class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>Nomor Pengiriman</th>
                  <th>Tanggal</th>
                  <th>Penerima</th>
                  <th>Status</th>
                  <th class="text-center">Aksi</th>
                </tr>
              </thead>

              <tbody>
                <tr v-for="delivery in deliveries" :key="String(delivery.id)">
                  <td class="fw-semibold">
                    {{ delivery.delivery_number }}
                  </td>

                  <td>
                    {{ formatDate(delivery.delivery_date) }}
                  </td>

                  <td>
                    {{ delivery.recipient_name || "-" }}
                  </td>

                  <td>
                    <span class="badge" :class="deliveryStatusClass(delivery.status)">
                      {{ deliveryStatusLabel(delivery.status) }}
                    </span>
                  </td>

                  <td class="text-center">
                    <button type="button" class="btn btn-sm btn-light" @click="openDelivery(delivery)">
                      <i class="ti ti-eye me-1"></i>
                      Detail
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>

    <!-- Empty State -->
    <div v-else class="card">
      <div class="card-body text-center py-5">
        <i class="ti ti-file-off fs-1 text-muted"></i>

        <h6 class="mt-3">Data pesanan tidak tersedia</h6>

        <button type="button" class="btn btn-light mt-2" @click="goBack">Kembali ke Daftar Pesanan</button>
      </div>
    </div>
  </div>
</template>
