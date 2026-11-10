<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";

import { notify } from "@/utils/notification";

import distributionService from "../services/distributionService";
import deliveryService from "../services/deliveryService";

import type { Delivery, DistributionOrder } from "../types/distribution";

interface DeliveryRow extends Delivery {
  order_number?: string;
  customer_name?: string;
  customer_id?: number | string;
}

const router = useRouter();

const loading = ref(false);

const deliveries = ref<DeliveryRow[]>([]);
const orders = ref<DistributionOrder[]>([]);

const search = ref("");
const statusFilter = ref("");

const filteredDeliveries = computed(() => {
  const keyword = search.value.trim().toLowerCase();

  return deliveries.value.filter((delivery) => {
    const matchesSearch =
      !keyword ||
      delivery.delivery_number.toLowerCase().includes(keyword) ||
      String(delivery.order_number ?? "")
        .toLowerCase()
        .includes(keyword) ||
      String(delivery.recipient_name ?? "")
        .toLowerCase()
        .includes(keyword) ||
      String(delivery.shipping_address ?? "")
        .toLowerCase()
        .includes(keyword);

    const matchesStatus = !statusFilter.value || delivery.status === statusFilter.value;

    return matchesSearch && matchesStatus;
  });
});

const summary = computed(() => ({
  total: deliveries.value.length,
  draft: deliveries.value.filter((item) => item.status === "draft").length,
  posted: deliveries.value.filter((item) => item.status === "posted").length,
  cancelled: deliveries.value.filter((item) => item.status === "cancelled").length,
}));

function formatDate(value: string | null | undefined): string {
  if (!value) return "-";

  const date = new Date(`${value.substring(0, 10)}T00:00:00`);

  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  }).format(date);
}

function statusLabel(status: string): string {
  const labels: Record<string, string> = {
    draft: "Draft",
    posted: "Sudah Diposting",
    cancelled: "Dibatalkan",
  };

  return labels[status] ?? status;
}

function statusClass(status: string): string {
  const classes: Record<string, string> = {
    draft: "bg-light text-secondary",
    posted: "bg-success-subtle text-success",
    cancelled: "bg-danger-subtle text-danger",
  };

  return classes[status] ?? "bg-light text-secondary";
}

async function loadDeliveries() {
  loading.value = true;

  try {
    const [deliveryResponse, orderResponse] = await Promise.all([
      deliveryService.getDeliveries({
        page: 1,
        per_page: 100,
      }),
      distributionService.getOrders(),
    ]);

    orders.value = orderResponse;

    const orderMap = new Map(orders.value.map((order) => [Number(order.id), order]));

    deliveries.value = deliveryResponse.items.map((delivery) => {
      const relatedOrder = orderMap.get(Number(delivery.distribution_order_id));

      return {
        ...delivery,
        order_number: (delivery as DeliveryRow).order_number ?? relatedOrder?.order_number,
        customer_name: (delivery as DeliveryRow).customer_name ?? relatedOrder?.customer_name,
        customer_id: (delivery as DeliveryRow).customer_id ?? relatedOrder?.customer_id,
      };
    });
  } catch (error: any) {
    notify.error(error?.response?.data?.message || error?.message || "Gagal memuat daftar pengiriman.");
  } finally {
    loading.value = false;
  }
}

function openDetail(delivery: DeliveryRow) {
  router.push({
    path: "/pharmacy/distribution/delivery-detail",
    query: {
      id: String(delivery.id),
      order_id: String(delivery.distribution_order_id),
    },
  });
}

function openOrder(delivery: DeliveryRow) {
  router.push({
    path: "/pharmacy/distribution/order-detail",
    query: {
      id: String(delivery.distribution_order_id),
    },
  });
}

function goToOrders() {
  router.push("/pharmacy/distribution/distribution-order");
}

onMounted(loadDeliveries);
</script>

<template>
  <div class="container-fluid">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <h4 class="mb-1">Dokumen Pengiriman</h4>
        <p class="text-muted mb-0">Daftar delivery dari pesanan distribusi.</p>
      </div>

      <button type="button" class="btn btn-light" @click="goToOrders">
        <i class="ti ti-shopping-cart me-1"></i>
        Daftar Pesanan
      </button>
    </div>

    <!-- Summary -->
    <div class="row g-3 mb-4">
      <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
          <div class="card-body">
            <p class="text-muted mb-1">Total Dokumen</p>
            <h4 class="mb-0">{{ summary.total }}</h4>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
          <div class="card-body">
            <p class="text-muted mb-1">Draft</p>
            <h4 class="mb-0 text-secondary">
              {{ summary.draft }}
            </h4>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
          <div class="card-body">
            <p class="text-muted mb-1">Sudah Diposting</p>
            <h4 class="mb-0 text-success">
              {{ summary.posted }}
            </h4>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
          <div class="card-body">
            <p class="text-muted mb-1">Dibatalkan</p>
            <h4 class="mb-0 text-danger">
              {{ summary.cancelled }}
            </h4>
          </div>
        </div>
      </div>
    </div>

    <!-- Delivery Table -->
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Daftar Pengiriman</h5>
      </div>

      <div class="card-body">
        <!-- Filters -->
        <div class="row g-3 mb-4">
          <div class="col-md-8">
            <input v-model="search" type="search" class="form-control" placeholder="Cari nomor delivery, nomor pesanan, atau penerima..." />
          </div>

          <div class="col-md-4">
            <select v-model="statusFilter" class="form-select">
              <option value="">Semua Status</option>
              <option value="draft">Draft</option>
              <option value="posted">Sudah Diposting</option>
              <option value="cancelled">Dibatalkan</option>
            </select>
          </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-primary"></div>
          <p class="text-muted mt-2 mb-0">Memuat daftar pengiriman...</p>
        </div>

        <div v-else class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>Nomor Pengiriman</th>
                <th>Nomor Pesanan</th>
                <th>Tanggal</th>
                <th>Penerima</th>
                <th>Alamat</th>
                <th>Status</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="delivery in filteredDeliveries" :key="String(delivery.id)">
                <td>
                  <div class="fw-semibold">
                    {{ delivery.delivery_number }}
                  </div>
                </td>

                <td>
                  <button type="button" class="btn btn-link p-0 text-decoration-none" @click="openOrder(delivery)">
                    {{ delivery.order_number || `Pesanan #${delivery.distribution_order_id}` }}
                  </button>
                </td>

                <td>
                  {{ formatDate(delivery.delivery_date) }}
                </td>

                <td>
                  {{ delivery.recipient_name || "-" }}
                </td>

                <td>
                  <span class="d-inline-block text-truncate" style="max-width: 180px" :title="delivery.shipping_address || ''">
                    {{ delivery.shipping_address || "-" }}
                  </span>
                </td>

                <td>
                  <span class="badge" :class="statusClass(delivery.status)">
                    {{ statusLabel(delivery.status) }}
                  </span>
                </td>

                <td class="text-center">
                  <button type="button" class="btn btn-sm btn-light" @click="openDetail(delivery)">
                    <i class="ti ti-eye me-1"></i>
                    Detail
                  </button>
                </td>
              </tr>

              <tr v-if="filteredDeliveries.length === 0">
                <td colspan="7" class="text-center text-muted py-5">
                  <i class="ti ti-truck-off fs-1 d-block mb-2"></i>
                  Tidak ada dokumen pengiriman.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
