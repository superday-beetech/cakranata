<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import reportService, { type StockCardFilter, type StockCardReport } from "../services/reportService";
import { notify } from "@/utils/notification";

defineOptions({
  name: "StockCardView",
});

const loading = ref(false);
const exporting = ref(false);
const report = ref<StockCardReport | null>(null);

const filters = reactive({
  product_id: "",
  batch_id: "",
  date_from: "",
  date_to: "",
  page: 1,
  per_page: 10,
});

const hasProduct = computed(() => Number(filters.product_id) > 0);

const pagination = computed(() => report.value?.pagination);

const numberFormatter = new Intl.NumberFormat("id-ID", {
  minimumFractionDigits: 0,
  maximumFractionDigits: 3,
});

const currencyFormatter = new Intl.NumberFormat("id-ID", {
  style: "currency",
  currency: "IDR",
  maximumFractionDigits: 0,
});

function formatNumber(value: number | string | null | undefined): string {
  return numberFormatter.format(Number(value ?? 0));
}

function formatCurrency(value: number | string | null | undefined): string {
  return currencyFormatter.format(Number(value ?? 0));
}

function formatDate(value: string | null | undefined): string {
  if (!value) return "-";

  const date = new Date(value.replace(" ", "T"));

  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  }).format(date);
}

function formatDateTime(value: string | null | undefined): string {
  if (!value) return "-";

  const date = new Date(value.replace(" ", "T"));

  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(date);
}

function buildFilters(page = 1): StockCardFilter {
  const params: StockCardFilter = {
    product_id: Number(filters.product_id),
    page,
    per_page: filters.per_page,
  };

  if (filters.batch_id.trim()) {
    params.batch_id = Number(filters.batch_id);
  }

  if (filters.date_from) {
    params.date_from = filters.date_from;
  }

  if (filters.date_to) {
    params.date_to = filters.date_to;
  }

  return params;
}

async function loadReport(page = 1): Promise<void> {
  if (!hasProduct.value) {
    notify.warning("Masukkan ID produk terlebih dahulu.");
    return;
  }

  if (filters.batch_id.trim() && (!Number.isInteger(Number(filters.batch_id)) || Number(filters.batch_id) <= 0)) {
    notify.warning("ID batch harus berupa angka positif.");
    return;
  }

  if (filters.date_from && filters.date_to && filters.date_from > filters.date_to) {
    notify.warning("Tanggal awal tidak boleh melebihi tanggal akhir.");
    return;
  }

  loading.value = true;

  try {
    filters.page = page;
    report.value = await reportService.getStockCard(buildFilters(page));
  } catch (error: any) {
    report.value = null;
    notify.error(error?.response?.data?.message || error?.message || "Gagal memuat laporan kartu stok.");
  } finally {
    loading.value = false;
  }
}

function resetFilters(): void {
  filters.product_id = "";
  filters.batch_id = "";
  filters.date_from = "";
  filters.date_to = "";
  filters.page = 1;
  report.value = null;
}

async function downloadFile(type: "csv" | "excel" | "pdf"): Promise<void> {
  if (!hasProduct.value) {
    notify.warning("Pilih produk terlebih dahulu.");
    return;
  }

  exporting.value = true;

  try {
    const params = buildFilters(1);

    let blob: Blob;

    switch (type) {
      case "csv":
        blob = await reportService.downloadStockCardCsv(params);
        break;
      case "excel":
        blob = await reportService.downloadStockCardExcel(params);
        break;
      case "pdf":
        blob = await reportService.downloadStockCardPdf(params);
        break;
    }

    const extension = type === "excel" ? "xlsx" : type;
    const filename = `stock-card-${filters.product_id}.${extension}`;

    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");

    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();

    URL.revokeObjectURL(url);
    notify.success("Laporan berhasil diunduh.");
  } catch (error: any) {
    notify.error(error?.response?.data?.message || error?.message || "Gagal mengunduh laporan.");
  } finally {
    exporting.value = false;
  }
}

function changePage(page: number): void {
  if (!pagination.value || page < 1 || page > pagination.value.total_pages) {
    return;
  }

  loadReport(page);
}

function movementLabel(type: string): string {
  const labels: Record<string, string> = {
    purchase: "Pembelian",
    purchase_return: "Retur Pembelian",
    sales: "Penjualan",
    sales_return: "Retur Penjualan",
    adjustment_in: "Penyesuaian Masuk",
    adjustment_out: "Penyesuaian Keluar",
    distribution: "Distribusi",
    stock_opname: "Stok Opname",
  };

  return labels[type] || type;
}

function movementBadge(type: string): string {
  const incoming = ["purchase", "sales_return", "adjustment_in"];

  return incoming.includes(type) ? "bg-success" : "bg-danger";
}

onMounted(() => {
  // Laporan dimuat setelah produk dipilih.
});
</script>

<template>
  <div class="container-fluid">
    <!-- Page Header -->
    <div class="row align-items-center mb-4">
      <div class="col-md-8">
        <h4 class="mb-1">Kartu Stok</h4>
        <p class="text-muted mb-0">Riwayat pergerakan dan saldo stok produk.</p>
      </div>
    </div>

    <!-- Filter -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0">Filter Laporan</h5>
      </div>

      <div class="card-body">
        <form @submit.prevent="loadReport(1)">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label"> ID Produk <span class="text-danger">*</span> </label>
              <input v-model="filters.product_id" type="number" min="1" class="form-control" placeholder="Masukkan ID produk" required />
            </div>

            <div class="col-md-3">
              <label class="form-label">ID Batch</label>
              <input v-model="filters.batch_id" type="number" min="1" class="form-control" placeholder="Semua batch" />
            </div>

            <div class="col-md-3">
              <label class="form-label">Tanggal Awal</label>
              <input v-model="filters.date_from" type="date" class="form-control" />
            </div>

            <div class="col-md-3">
              <label class="form-label">Tanggal Akhir</label>
              <input v-model="filters.date_to" type="date" class="form-control" />
            </div>

            <div class="col-12 d-flex flex-wrap gap-2">
              <button type="submit" class="btn btn-primary" :disabled="loading">
                <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="ti ti-search me-1"></i>
                Tampilkan
              </button>

              <button type="button" class="btn btn-light" :disabled="loading" @click="resetFilters">
                <i class="ti ti-refresh me-1"></i>
                Reset
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Report Content -->
    <template v-if="report">
      <!-- Product Information -->
      <div class="card mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div>
            <h5 class="mb-1">
              {{ report.product.name }}
            </h5>
            <div class="text-muted small">
              SKU: {{ report.product.sku }}
              <span class="mx-1">·</span>
              ID: {{ report.product.id }}
            </div>
            <div class="text-muted small">
              Satuan:
              {{ report.product.unit_abbreviation || report.product.unit_name || "-" }}
            </div>
          </div>

          <div class="text-md-end">
            <div class="text-muted small">Periode Laporan</div>
            <strong>
              {{ filters.date_from || "Awal" }}
              –
              {{ filters.date_to || "Sekarang" }}
            </strong>
          </div>
        </div>
      </div>

      <!-- Summary Cards -->
      <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
          <div class="card h-100 border-start border-primary border-4">
            <div class="card-body">
              <div class="text-muted small mb-2">Saldo Awal</div>
              <h4 class="mb-0">
                {{ formatNumber(report.summary.opening_stock) }}
              </h4>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6">
          <div class="card h-100 border-start border-success border-4">
            <div class="card-body">
              <div class="text-muted small mb-2">Total Masuk</div>
              <h4 class="text-success mb-0">+{{ formatNumber(report.summary.total_in) }}</h4>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6">
          <div class="card h-100 border-start border-danger border-4">
            <div class="card-body">
              <div class="text-muted small mb-2">Total Keluar</div>
              <h4 class="text-danger mb-0">-{{ formatNumber(report.summary.total_out) }}</h4>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6">
          <div class="card h-100 border-start border-info border-4">
            <div class="card-body">
              <div class="text-muted small mb-2">Saldo Akhir</div>
              <h4 class="text-info mb-0">
                {{ formatNumber(report.summary.closing_stock) }}
              </h4>
            </div>
          </div>
        </div>
      </div>

      <!-- Movement Table -->
      <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div>
            <h5 class="mb-1">Riwayat Mutasi Stok</h5>
            <small class="text-muted"> {{ pagination?.total || 0 }} transaksi </small>
          </div>

          <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-sm btn-outline-success" :disabled="exporting" @click="downloadFile('csv')">
              <i class="ti ti-file-type-csv me-1"></i>
              CSV
            </button>

            <button class="btn btn-sm btn-outline-success" :disabled="exporting" @click="downloadFile('excel')">
              <i class="ti ti-file-spreadsheet me-1"></i>
              Excel
            </button>

            <button class="btn btn-sm btn-outline-danger" :disabled="exporting" @click="downloadFile('pdf')">
              <i class="ti ti-file-type-pdf me-1"></i>
              PDF
            </button>
          </div>
        </div>

        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th>No.</th>
                  <th>Tanggal</th>
                  <th>Batch</th>
                  <th>Jenis Mutasi</th>
                  <th>Referensi</th>
                  <th class="text-end">Masuk</th>
                  <th class="text-end">Keluar</th>
                  <th class="text-end">Saldo</th>
                  <th class="text-end">Biaya Satuan</th>
                  <th>Keterangan</th>
                </tr>
              </thead>

              <tbody>
                <tr v-for="(item, index) in report.items" :key="item.id">
                  <td>
                    {{ ((pagination?.page || 1) - 1) * (pagination?.per_page || 10) + index + 1 }}
                  </td>

                  <td class="text-nowrap">
                    {{ formatDateTime(item.movement_date) }}
                  </td>

                  <td>
                    <div>{{ item.batch_number || "-" }}</div>
                    <small class="text-muted">
                      {{ formatDate(item.expired_at) }}
                    </small>
                  </td>

                  <td>
                    <span class="badge" :class="movementBadge(item.movement_type)">
                      {{ movementLabel(item.movement_type) }}
                    </span>
                  </td>

                  <td>
                    {{ item.reference_type || "-" }}
                    <small v-if="item.reference_id" class="d-block text-muted"> #{{ item.reference_id }} </small>
                  </td>

                  <td class="text-end text-success">
                    {{ Number(item.quantity_in) > 0 ? "+" + formatNumber(item.quantity_in) : "-" }}
                  </td>

                  <td class="text-end text-danger">
                    {{ Number(item.quantity_out) > 0 ? "-" + formatNumber(item.quantity_out) : "-" }}
                  </td>

                  <td class="text-end fw-semibold">
                    {{ formatNumber(item.balance) }}
                  </td>

                  <td class="text-end text-nowrap">
                    {{ formatCurrency(item.unit_cost) }}
                  </td>

                  <td>{{ item.notes || "-" }}</td>
                </tr>

                <tr v-if="report.items.length === 0">
                  <td colspan="10" class="text-center py-5">
                    <i class="ti ti-inbox fs-7 d-block mb-2"></i>
                    Tidak ada mutasi stok pada periode ini.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="pagination && pagination.total > 0" class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
            <div class="text-muted small">
              Menampilkan
              {{ (pagination.page - 1) * pagination.per_page + 1 }}
              –
              {{ Math.min(pagination.page * pagination.per_page, pagination.total) }}
              dari {{ pagination.total }} transaksi
            </div>

            <div class="d-flex align-items-center gap-2">
              <button class="btn btn-sm btn-outline-secondary" :disabled="loading || pagination.page <= 1" @click="changePage(pagination.page - 1)">Sebelumnya</button>

              <span class="small">
                {{ pagination.page }} /
                {{ pagination.total_pages }}
              </span>

              <button class="btn btn-sm btn-outline-secondary" :disabled="loading || pagination.page >= pagination.total_pages" @click="changePage(pagination.page + 1)">Berikutnya</button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Empty State -->
    <div v-else-if="!loading" class="card">
      <div class="card-body text-center py-5">
        <i class="ti ti-report-analytics fs-7 text-muted d-block mb-3"></i>
        <h5>Belum Ada Laporan</h5>
        <p class="text-muted mb-0">Masukkan ID produk dan tampilkan laporan kartu stok.</p>
      </div>
    </div>
  </div>
</template>
