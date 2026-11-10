<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { salesService } from "../services/salesService";
import type { Sale, SaleStatus } from "../types/sales";

const sales = ref<Sale[]>([]);
const selectedSale = ref<Sale | null>(null);

const search = ref("");
const status = ref("");
const page = ref(1);
const perPage = ref(10);

const total = ref(0);
const lastPage = ref(1);

const loading = ref(false);
const errorMessage = ref("");

let searchTimeout: ReturnType<typeof setTimeout> | undefined;

const statuses = [
  { value: "", label: "Semua Status" },
  { value: "draft", label: "Draft" },
  { value: "posted", label: "Diposting" },
  { value: "cancelled", label: "Dibatalkan" },
];

const paginationText = computed(() => {
  if (total.value === 0) return "Tidak ada transaksi";

  const start = (page.value - 1) * perPage.value + 1;
  const end = Math.min(page.value * perPage.value, total.value);

  return `Menampilkan ${start}–${end} dari ${total.value} transaksi`;
});

function formatCurrency(value: number | string): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(Number(value) || 0);
}

function formatDate(value?: string): string {
  if (!value) return "-";

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat("id-ID", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
}

function statusClass(value: SaleStatus): string {
  const classes: Record<SaleStatus, string> = {
    draft: "bg-light-warning text-warning",
    held: "bg-light-info text-info",
    posted: "bg-light-success text-success",
    cancelled: "bg-light-danger text-danger",
  };

  return classes[value] ?? "bg-light-secondary text-secondary";
}

function statusLabel(value: SaleStatus): string {
  const labels: Record<SaleStatus, string> = {
    draft: "Draft",
    held: "Tertahan",
    posted: "Diposting",
    cancelled: "Dibatalkan",
  };

  return labels[value] ?? value;
}

async function loadSales() {
  loading.value = true;
  errorMessage.value = "";

  try {
    const response = await salesService.getSales({
      page: page.value,
      per_page: perPage.value,
      search: search.value || undefined,
      status: status.value || undefined,
    });

    sales.value = response.data;
    total.value = response.pagination.total;
    lastPage.value = response.pagination.last_page;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "Gagal mengambil data penjualan.";
  } finally {
    loading.value = false;
  }
}

async function showDetail(id: number) {
  try {
    errorMessage.value = "";
    selectedSale.value = await salesService.getSale(id);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "Gagal mengambil detail transaksi.";
  }
}

function closeDetail() {
  selectedSale.value = null;
}

function changePage(nextPage: number) {
  if (nextPage < 1 || nextPage > lastPage.value) return;

  page.value = nextPage;
}

watch([search, status, perPage], () => {
  page.value = 1;
  loadSales();
});

watch(page, loadSales);

onMounted(loadSales);
</script>

<template>
  <div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">Penjualan POS</h4>
        <p class="text-muted mb-0">Riwayat transaksi penjualan langsung</p>
      </div>

      <RouterLink to="/pharmacy/pos" class="btn btn-primary">
        <i class="ti ti-plus me-1" />
        Transaksi Baru
      </RouterLink>
    </div>

    <!-- Error -->
    <div v-if="errorMessage" class="alert alert-danger" role="alert">
      {{ errorMessage }}
    </div>

    <!-- Filters -->
    <div class="card">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-6">
            <label class="form-label">Cari Transaksi</label>
            <input v-model="search" type="search" class="form-control" placeholder="Nomor transaksi atau pelanggan..." />
          </div>

          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select v-model="status" class="form-select">
              <option v-for="item in statuses" :key="item.value" :value="item.value">
                {{ item.label }}
              </option>
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label">Data per halaman</label>
            <select v-model.number="perPage" class="form-select">
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="card mt-4">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table align-middle text-nowrap">
            <thead>
              <tr>
                <th>No.</th>
                <th>Nomor Transaksi</th>
                <th>Tanggal</th>
                <th>Pelanggan</th>
                <th class="text-end">Total</th>
                <th class="text-end">Dibayar</th>
                <th>Status</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>

            <tbody>
              <tr v-if="loading">
                <td colspan="8" class="text-center py-5">
                  <div class="spinner-border spinner-border-sm text-primary" />
                  <span class="ms-2">Memuat transaksi...</span>
                </td>
              </tr>

              <tr v-else-if="sales.length === 0">
                <td colspan="8" class="text-center text-muted py-5">Belum ada transaksi penjualan.</td>
              </tr>

              <tr v-for="(sale, index) in sales" :key="sale.id">
                <td>
                  {{ (page - 1) * perPage + index + 1 }}
                </td>

                <td class="fw-semibold">
                  {{ sale.sale_number }}
                </td>

                <td>
                  {{ formatDate(sale.sale_date) }}
                </td>

                <td>
                  {{ sale.customer_name || "Pelanggan umum" }}
                </td>

                <td class="text-end">
                  {{ formatCurrency(sale.total_amount) }}
                </td>

                <td class="text-end">
                  {{ formatCurrency(sale.paid_amount) }}
                </td>

                <td>
                  <span class="badge" :class="statusClass(sale.status)">
                    {{ statusLabel(sale.status) }}
                  </span>
                </td>

                <td class="text-center">
                  <button type="button" class="btn btn-sm btn-light-primary" @click="showDetail(sale.id)">
                    <i class="ti ti-eye me-1" />
                    Detail
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
          <small class="text-muted">
            {{ paginationText }}
          </small>

          <div class="btn-group">
            <button type="button" class="btn btn-outline-primary" :disabled="page <= 1 || loading" @click="changePage(page - 1)">
              <i class="ti ti-chevron-left" />
            </button>

            <button type="button" class="btn btn-outline-primary" disabled>{{ page }} / {{ lastPage }}</button>

            <button type="button" class="btn btn-outline-primary" :disabled="page >= lastPage || loading" @click="changePage(page + 1)">
              <i class="ti ti-chevron-right" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Detail -->
    <div v-if="selectedSale" class="card mt-4 border-primary">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <h5 class="mb-1">Detail {{ selectedSale.sale_number }}</h5>
            <small class="text-muted">
              {{ formatDate(selectedSale.sale_date) }}
            </small>
          </div>

          <button type="button" class="btn btn-sm btn-light" @click="closeDetail">
            <i class="ti ti-x" />
          </button>
        </div>

        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <small class="text-muted">Pelanggan</small>
            <div class="fw-semibold">
              {{ selectedSale.customer_name || "Pelanggan umum" }}
            </div>
          </div>

          <div class="col-md-4">
            <small class="text-muted">Status</small>
            <div>
              <span class="badge" :class="statusClass(selectedSale.status)">
                {{ statusLabel(selectedSale.status) }}
              </span>
            </div>
          </div>

          <div class="col-md-4">
            <small class="text-muted">Pembayaran</small>
            <div class="fw-semibold">
              {{ selectedSale.payment_status }}
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>Produk</th>
                <th>Batch</th>
                <th class="text-end">Qty</th>
                <th class="text-end">Harga</th>
                <th class="text-end">Diskon</th>
                <th class="text-end">Subtotal</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="item in selectedSale.items || []" :key="item.id">
                <td>
                  <div class="fw-semibold">
                    {{ item.product_name }}
                  </div>
                  <small class="text-muted">
                    {{ item.sku }}
                  </small>
                </td>

                <td>
                  {{ item.batch_number || "-" }}
                </td>

                <td class="text-end">
                  {{ item.quantity }}
                </td>

                <td class="text-end">
                  {{ formatCurrency(item.unit_price) }}
                </td>

                <td class="text-end">
                  {{ formatCurrency(item.discount_amount) }}
                </td>

                <td class="text-end">
                  {{ formatCurrency(item.subtotal) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="row justify-content-end">
          <div class="col-md-5 col-lg-4">
            <div class="d-flex justify-content-between mb-2">
              <span>Subtotal</span>
              <strong>{{ formatCurrency(selectedSale.subtotal) }}</strong>
            </div>

            <div class="d-flex justify-content-between mb-2">
              <span>Diskon</span>
              <strong>{{ formatCurrency(selectedSale.discount_amount) }}</strong>
            </div>

            <div class="d-flex justify-content-between mb-2">
              <span>Pajak</span>
              <strong>{{ formatCurrency(selectedSale.tax_amount) }}</strong>
            </div>

            <hr />

            <div class="d-flex justify-content-between">
              <span class="fw-semibold">Total</span>
              <h5 class="text-primary">
                {{ formatCurrency(selectedSale.total_amount) }}
              </h5>
            </div>

            <div class="d-flex justify-content-between">
              <span>Dibayar</span>
              <strong>{{ formatCurrency(selectedSale.paid_amount) }}</strong>
            </div>
          </div>
        </div>

        <div v-if="selectedSale.notes" class="mt-3">
          <small class="text-muted">Catatan</small>
          <p class="mb-0">{{ selectedSale.notes }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
