<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";

import distributionService from "../services/distributionService";
import type { DistributionOrder, DistributionOrderStatus } from "../types/distribution";

import { notify } from "@/utils/notification";

const router = useRouter();

const orders = ref<DistributionOrder[]>([]);
const loading = ref(false);
const search = ref("");
const statusFilter = ref("");

const statusOptions = [
  { value: "", label: "Semua Status" },
  { value: "draft", label: "Draft" },
  { value: "partially_delivered", label: "Sebagian Terkirim" },
  { value: "completed", label: "Selesai" },
  { value: "cancelled", label: "Dibatalkan" },
];

const filteredOrders = computed(() => {
  const keyword = search.value.trim().toLowerCase();

  return orders.value.filter((order) => {
    const matchesSearch = !keyword || order.order_number.toLowerCase().includes(keyword) || String(order.customer_id).includes(keyword) || (order.customer_name ?? "").toLowerCase().includes(keyword);

    const matchesStatus = !statusFilter.value || order.status === statusFilter.value;

    return matchesSearch && matchesStatus;
  });
});

const totalOrders = computed(() => orders.value.length);

const draftCount = computed(() => orders.value.filter((item) => item.status === "draft").length);

const partialCount = computed(() => orders.value.filter((item) => item.status === "partially_delivered").length);

const completedCount = computed(() => orders.value.filter((item) => item.status === "completed").length);

function formatCurrency(value: number | string): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(Number(value || 0));
}

function formatDate(value: string | null): string {
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
    partially_delivered: "Sebagian Terkirim",
    completed: "Selesai",
    cancelled: "Dibatalkan",
  };

  return labels[status] ?? status;
}

function statusClass(status: string): string {
  const classes: Record<string, string> = {
    draft: "bg-light text-secondary",
    partially_delivered: "bg-warning-subtle text-warning",
    completed: "bg-success-subtle text-success",
    cancelled: "bg-danger-subtle text-danger",
  };

  return classes[status] ?? "bg-light text-secondary";
}

async function loadOrders() {
  loading.value = true;

  try {
    orders.value = await distributionService.getOrders();
  } catch (error: any) {
    notify.error(error?.response?.data?.message || error?.message || "Gagal memuat pesanan distribusi.");
  } finally {
    loading.value = false;
  }
}

function openDetail(id: number | string) {
  router.push({
    path: "/pharmacy/distribution/distribution-order-detail",
    query: { id: String(id) },
  });
}

function createOrder() {
  router.push("/pharmacy/distribution/distribution-order-form");
}

onMounted(loadOrders);
</script>

<template>
  <div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <h4 class="mb-1">Pesanan Distribusi</h4>
        <p class="text-muted mb-0">Pengelolaan pesanan penjualan dan distribusi produk.</p>
      </div>

      <button type="button" class="btn btn-primary" @click="createOrder">
        <i class="ti ti-plus me-1"></i>
        Buat Pesanan
      </button>
    </div>

    <!-- Summary -->
    <div class="row g-3 mb-4">
      <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <p class="text-muted mb-1">Total Pesanan</p>
                <h4 class="mb-0">{{ totalOrders }}</h4>
              </div>
              <div class="rounded-circle bg-primary-subtle p-3">
                <i class="ti ti-clipboard-list text-primary fs-5"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <p class="text-muted mb-1">Draft</p>
                <h4 class="mb-0">{{ draftCount }}</h4>
              </div>
              <div class="rounded-circle bg-secondary-subtle p-3">
                <i class="ti ti-file-text text-secondary fs-5"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <p class="text-muted mb-1">Sebagian Terkirim</p>
                <h4 class="mb-0">{{ partialCount }}</h4>
              </div>
              <div class="rounded-circle bg-warning-subtle p-3">
                <i class="ti ti-truck text-warning fs-5"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <p class="text-muted mb-1">Selesai</p>
                <h4 class="mb-0">{{ completedCount }}</h4>
              </div>
              <div class="rounded-circle bg-success-subtle p-3">
                <i class="ti ti-circle-check text-success fs-5"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Order Table -->
    <div class="card">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <h5 class="mb-0">Daftar Pesanan</h5>

        <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="loading" @click="loadOrders">
          <i class="ti ti-refresh me-1"></i>
          Muat Ulang
        </button>
      </div>

      <div class="card-body">
        <!-- Filters -->
        <div class="row g-3 mb-4">
          <div class="col-md-7 col-lg-8">
            <input v-model="search" type="search" class="form-control" placeholder="Cari nomor pesanan atau ID pelanggan..." />
          </div>

          <div class="col-md-5 col-lg-4">
            <select v-model="statusFilter" class="form-select">
              <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
        </div>

        <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status"></div>
          <p class="text-muted mt-2 mb-0">Memuat pesanan distribusi...</p>
        </div>

        <div v-else-if="filteredOrders.length === 0" class="text-center py-5">
          <i class="ti ti-clipboard-off fs-1 text-muted"></i>
          <h6 class="mt-3">Belum Ada Pesanan</h6>
          <p class="text-muted mb-0">Tidak ada pesanan yang sesuai dengan pencarian atau filter.</p>
        </div>

        <div v-else class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>Nomor Pesanan</th>
                <th>Pelanggan</th>
                <th>Tanggal Pesanan</th>
                <th>Rencana Pengiriman</th>
                <th class="text-end">Total</th>
                <th>Status</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="order in filteredOrders" :key="String(order.id)">
                <td>
                  <span class="fw-semibold">
                    {{ order.order_number }}
                  </span>
                </td>

                <td>
                  {{ order.customer_name || `Pelanggan #${order.customer_id}` }}
                </td>

                <td>{{ formatDate(order.order_date) }}</td>

                <td>
                  {{ formatDate(order.requested_delivery_date) }}
                </td>

                <td class="text-end fw-semibold">
                  {{ formatCurrency(order.total_amount) }}
                </td>

                <td>
                  <span class="badge" :class="statusClass(order.status)">
                    {{ statusLabel(order.status) }}
                  </span>
                </td>

                <td class="text-center">
                  <button type="button" class="btn btn-sm btn-light" title="Lihat detail" @click="openDetail(order.id)">
                    <i class="ti ti-eye"></i>
                    Detail
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="!loading && filteredOrders.length > 0" class="text-muted small mt-3">Menampilkan {{ filteredOrders.length }} dari {{ totalOrders }} pesanan.</div>
      </div>
    </div>
  </div>
</template>
