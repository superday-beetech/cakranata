<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import reportService from "../services/reportService";
import type { StockSummaryFilter, StockSummaryItem, StockSummaryReport } from "../services/reportService";

const loading = ref(false);
const exporting = ref(false);
const errorMessage = ref("");

const items = ref<StockSummaryItem[]>([]);
const report = ref<StockSummaryReport | null>(null);

const keyword = ref("");
const dateFrom = ref("");
const dateTo = ref("");
const page = ref(1);
const perPage = ref(20);

const pagination = computed(() => report.value?.pagination);
const summary = computed(() => report.value?.summary);

const formatNumber = (value: number | string | null | undefined) =>
  Number(value || 0).toLocaleString("id-ID", {
    maximumFractionDigits: 3,
  });

const formatCurrency = (value: number | string | null | undefined) =>
  new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(Number(value || 0));

const formatDate = (value: string) => {
  if (!value) return "-";

  return new Date(`${value}T00:00:00`).toLocaleDateString("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
};

function getToday(): string {
  const now = new Date();
  const offset = now.getTimezoneOffset();

  return new Date(now.getTime() - offset * 60000).toISOString().slice(0, 10);
}

function getMonthStart(): string {
  return `${getToday().slice(0, 7)}-01`;
}

function getFilters(): StockSummaryFilter {
  return {
    keyword: keyword.value.trim() || undefined,
    date_from: dateFrom.value,
    date_to: dateTo.value,
    page: page.value,
    per_page: perPage.value,
  };
}

function getExportFilters(): StockSummaryFilter {
  return {
    keyword: keyword.value.trim() || undefined,
    date_from: dateFrom.value,
    date_to: dateTo.value,
  };
}

function validatePeriod(): boolean {
  if (!dateFrom.value || !dateTo.value) {
    errorMessage.value = "Tanggal mulai dan tanggal akhir wajib diisi.";
    return false;
  }

  if (dateFrom.value > dateTo.value) {
    errorMessage.value = "Tanggal mulai tidak boleh melebihi tanggal akhir.";
    return false;
  }

  return true;
}

async function loadData() {
  if (!validatePeriod()) return;

  loading.value = true;
  errorMessage.value = "";

  try {
    const result = await reportService.getStockSummary(getFilters());

    report.value = result;
    items.value = result.items || [];
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || error?.message || "Gagal memuat laporan ringkasan stok.";

    items.value = [];
    report.value = null;
  } finally {
    loading.value = false;
  }
}

function search() {
  page.value = 1;
  loadData();
}

function resetFilter() {
  keyword.value = "";
  dateFrom.value = getMonthStart();
  dateTo.value = getToday();
  page.value = 1;

  loadData();
}

function changePage(nextPage: number) {
  if (!pagination.value || nextPage < 1 || nextPage > pagination.value.total_pages) {
    return;
  }

  page.value = nextPage;
  loadData();
}

function changePerPage() {
  page.value = 1;
  loadData();
}

/**
 * Mengunduh Blob dari backend.
 */
function saveBlob(blob: Blob, fileName: string) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");

  link.href = url;
  link.download = fileName;

  document.body.appendChild(link);
  link.click();
  link.remove();

  window.setTimeout(() => {
    URL.revokeObjectURL(url);
  }, 1000);
}

function getFilename(extension: string): string {
  return `stock-summary-${dateFrom.value}-${dateTo.value}.${extension}`;
}

/**
 * Ekspor CSV melalui backend.
 */
async function exportCsv() {
  if (loading.value || exporting.value) return;
  if (!validatePeriod()) return;

  exporting.value = true;
  errorMessage.value = "";

  try {
    const blob = await reportService.downloadStockSummaryCsv(getExportFilters());

    saveBlob(blob, getFilename("csv"));
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || error?.message || "Gagal mengekspor CSV.";
  } finally {
    exporting.value = false;
  }
}

/**
 * Ekspor Excel melalui backend.
 */
async function exportExcel() {
  if (loading.value || exporting.value) return;
  if (!validatePeriod()) return;

  exporting.value = true;
  errorMessage.value = "";

  try {
    const blob = await reportService.downloadStockSummaryExcel(getExportFilters());

    saveBlob(blob, getFilename("xlsx"));
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || error?.message || "Gagal mengekspor Excel.";
  } finally {
    exporting.value = false;
  }
}

/**
 * Membuka PDF dari backend.
 * PDF dapat dicetak melalui PDF viewer browser.
 */
async function printReport() {
  if (loading.value || exporting.value) return;
  if (!validatePeriod()) return;

  const printWindow = window.open("", "_blank");

  if (!printWindow) {
    errorMessage.value = "Jendela PDF diblokir browser. Izinkan pop-up untuk membuka laporan.";
    return;
  }

  exporting.value = true;
  errorMessage.value = "";

  try {
    printWindow.document.write(`
            <!DOCTYPE html>
            <html lang="id">
            <head>
                <meta charset="UTF-8">
                <title>Menyiapkan PDF</title>
            </head>
            <body style="font-family:Arial,sans-serif;padding:24px">
                Menyiapkan laporan PDF...
            </body>
            </html>
        `);

    printWindow.document.close();

    const blob = await reportService.downloadStockSummaryPdf(getExportFilters());

    const pdfUrl = URL.createObjectURL(blob);

    printWindow.location.href = pdfUrl;
  } catch (error: any) {
    printWindow.close();

    errorMessage.value = error?.response?.data?.message || error?.message || "Gagal menyiapkan PDF.";
  } finally {
    exporting.value = false;
  }
}

onMounted(() => {
  dateFrom.value = getMonthStart();
  dateTo.value = getToday();

  loadData();
});
</script>

<template>
  <div class="container-fluid">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
      <div>
        <h4 class="mb-1">Stock Summary</h4>
        <p class="text-muted mb-0">Laporan posisi dan mutasi stok per produk berdasarkan periode.</p>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-outline-secondary" :disabled="loading || exporting" @click="printReport">
          <i class="ti ti-printer me-1"></i>
          Cetak / PDF
        </button>

        <button class="btn btn-outline-success" :disabled="loading || exporting" @click="exportCsv">
          <i class="ti ti-file-type-csv me-1"></i>
          CSV
        </button>

        <button class="btn btn-outline-success" :disabled="loading || exporting" @click="exportExcel">
          <i class="ti ti-file-spreadsheet me-1"></i>
          Excel
        </button>
      </div>
    </div>

    <!-- Error -->
    <div v-if="errorMessage" class="alert alert-danger alert-dismissible" role="alert">
      {{ errorMessage }}

      <button type="button" class="btn-close" aria-label="Close" @click="errorMessage = ''"></button>
    </div>
    <!-- Summary -->
    <div v-if="summary" class="row g-3 mb-4">
      <div class="col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <small class="text-muted"> Jumlah Produk </small>

            <h4 class="mb-0">
              {{ formatNumber(summary.product_count) }}
            </h4>
          </div>
        </div>
      </div>

      <div class="col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <small class="text-muted"> Saldo Awal </small>

            <h4 class="mb-0">
              {{ formatNumber(summary.opening_stock) }}
            </h4>
          </div>
        </div>
      </div>

      <div class="col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <small class="text-muted"> Stok Masuk </small>

            <h4 class="mb-0 text-success">
              {{ formatNumber(summary.total_in) }}
            </h4>
          </div>
        </div>
      </div>

      <div class="col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <small class="text-muted"> Stok Keluar </small>

            <h4 class="mb-0 text-danger">
              {{ formatNumber(summary.total_out) }}
            </h4>
          </div>
        </div>
      </div>

      <div class="col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <small class="text-muted"> Saldo Akhir </small>

            <h4 class="mb-0">
              {{ formatNumber(summary.closing_stock) }}
            </h4>
          </div>
        </div>
      </div>

      <div class="col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <small class="text-muted"> Nilai Persediaan </small>

            <h5 class="mb-0">
              {{ formatCurrency(summary.stock_value) }}
            </h5>
          </div>
        </div>
      </div>
    </div>

    <!-- Expiry Summary -->
    <div v-if="summary" class="row g-3 mb-4">
      <div class="col-md-6">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <small class="text-muted"> Batch Kedaluwarsa </small>

            <h5 class="mb-0 text-danger">
              {{ formatNumber(summary.expired_batch_count) }}
            </h5>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <small class="text-muted"> Batch Kedaluwarsa dalam 30 Hari </small>

            <h5 class="mb-0 text-warning">
              {{ formatNumber(summary.expiring_batch_count) }}
            </h5>
          </div>
        </div>
      </div>
    </div>
    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <form class="row g-3 align-items-end" @submit.prevent="search">
          <div class="col-md-3">
            <label class="form-label"> Tanggal Mulai </label>

            <input v-model="dateFrom" type="date" class="form-control" required />
          </div>

          <div class="col-md-3">
            <label class="form-label"> Tanggal Akhir </label>

            <input v-model="dateTo" type="date" class="form-control" required />
          </div>

          <div class="col-md-4">
            <label class="form-label"> Cari Produk </label>

            <input v-model="keyword" type="search" class="form-control" placeholder="Nama, SKU, atau barcode..." />
          </div>

          <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary" :disabled="loading || exporting">
              <i class="ti ti-search me-1"></i>
              Terapkan
            </button>

            <button type="button" class="btn btn-light" :disabled="loading || exporting" @click="resetFilter">Reset</button>
          </div>
        </form>

        <div class="mt-3 small text-muted">
          Periode laporan:

          <strong>
            {{ formatDate(dateFrom) }}
            –
            {{ formatDate(dateTo) }}
          </strong>

          <span v-if="exporting" class="ms-2 text-primary">
            <span class="spinner-border spinner-border-sm me-1"></span>
            Menyiapkan file ekspor...
          </span>
        </div>
      </div>
    </div>

    <!-- Stock Table -->
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
          <h5 class="mb-0">Detail Stok per Produk</h5>

          <select v-model.number="perPage" class="form-select form-select-sm" style="width: 100px" :disabled="loading || exporting" @change="changePerPage">
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
        </div>

        <div class="table-responsive">
          <table class="table table-hover table-bordered border-3 align-middle">
            <thead class="">
              <tr>
                <th>No.</th>
                <th>Produk</th>
                <th>Kategori</th>
                <th class="text-end">Saldo Awal</th>
                <th class="text-end">Masuk</th>
                <th class="text-end">Keluar</th>
                <th class="text-end">Saldo Akhir</th>
                <th class="text-end">Harga Beli</th>
                <th class="text-end">Nilai Persediaan</th>
                <th class="text-center">Batch Expired</th>
              </tr>
            </thead>

            <tbody v-if="loading">
              <tr>
                <td colspan="10" class="text-center py-4">
                  <span class="spinner-border spinner-border-sm me-2"></span>

                  Memuat laporan...
                </td>
              </tr>
            </tbody>

            <tbody v-else-if="items.length">
              <tr v-for="(item, index) in items" :key="item.product_id">
                <td>
                  {{ (page - 1) * perPage + index + 1 }}
                </td>

                <td>
                  <div class="fw-semibold">
                    {{ item.product_name }}
                  </div>

                  <small class="text-muted">
                    {{ item.sku }}
                  </small>
                </td>

                <td>
                  {{ item.category_name || "-" }}
                </td>

                <td class="text-end">
                  {{ formatNumber(item.opening_stock) }}
                </td>

                <td class="text-end text-success">
                  {{ formatNumber(item.total_in) }}
                </td>

                <td class="text-end text-danger">
                  {{ formatNumber(item.total_out) }}
                </td>

                <td class="text-end fw-semibold">
                  {{ formatNumber(item.closing_stock) }}
                  {{ item.unit_abbreviation || "" }}
                </td>

                <td class="text-end">
                  {{ formatCurrency(item.purchase_price) }}
                </td>

                <td class="text-end">
                  {{ formatCurrency(item.stock_value) }}
                </td>

                <td class="text-center">
                  <span v-if="Number(item.expired_batch_count) > 0" class="badge bg-danger">
                    {{ item.expired_batch_count }}
                  </span>

                  <span v-else class="text-muted"> 0 </span>
                </td>
              </tr>
            </tbody>

            <tbody v-else>
              <tr>
                <td colspan="10" class="text-center text-muted py-4">Tidak ada data untuk periode ini.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2">
          <small class="text-muted"> Menampilkan {{ items.length }} dari {{ pagination?.total || 0 }} produk </small>

          <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary" :disabled="loading || exporting || page <= 1" @click="changePage(page - 1)">Sebelumnya</button>

            <span class="small">
              {{ pagination?.page || 1 }}
              /
              {{ pagination?.total_pages || 1 }}
            </span>

            <button class="btn btn-sm btn-outline-secondary" :disabled="loading || exporting || page >= (pagination?.total_pages || 1)" @click="changePage(page + 1)">Berikutnya</button>
          </div>
        </div>

        <!-- Report Notes -->
        <div class="small text-muted mt-3">
          <div>Nilai persediaan merupakan estimasi berdasarkan saldo akhir dikalikan harga beli pada master produk.</div>

          <div>Data saldo dihitung dari histori stock movements.</div>

          <div>Jumlah batch kedaluwarsa mengikuti kondisi batch saat ini, bukan rekonstruksi historis pada tanggal laporan.</div>
        </div>
      </div>
    </div>
  </div>
</template>
