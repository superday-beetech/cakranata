<script setup lang="ts">
import { onMounted, ref } from "vue";
import { inventoryService } from "../services/inventoryService";
import type { InventoryPagination, StockMovement } from "../types/inventory";
import { notify } from "@/utils/notification";

const rows = ref<StockMovement[]>([]);
const loading = ref(false);

const search = ref("");
const movementType = ref("");
const dateFrom = ref("");
const dateTo = ref("");

const page = ref(1);
const perPage = ref(10);

const pagination = ref<InventoryPagination>({
  page: 1,
  per_page: 10,
  total: 0,
  total_pages: 0,
  has_next: false,
  has_prev: false,
});

async function loadData(): Promise<void> {
  loading.value = true;

  try {
    const result = await inventoryService.getMovements({
      page: page.value,
      per_page: perPage.value,
      search: search.value.trim() || undefined,
      movement_type: movementType.value || undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
    });

    rows.value = result.items ?? [];
    pagination.value = result.pagination;
  } catch (error) {
    console.error("Gagal memuat mutasi stok:", error);
    notify.error("Gagal memuat riwayat mutasi stok.");
  } finally {
    loading.value = false;
  }
}

function searchData(): void {
  page.value = 1;
  loadData();
}

function resetFilters(): void {
  search.value = "";
  movementType.value = "";
  dateFrom.value = "";
  dateTo.value = "";
  page.value = 1;
  loadData();
}

function changePage(nextPage: number): void {
  if (nextPage < 1 || nextPage > pagination.value.total_pages || nextPage === page.value) {
    return;
  }

  page.value = nextPage;
  loadData();
}

function changePerPage(value: number | string): void {
  perPage.value = Number(value);
  page.value = 1;
  loadData();
}

function formatNumber(value: number | string): string {
  return new Intl.NumberFormat("id-ID", {
    maximumFractionDigits: 2,
  }).format(Number(value) || 0);
}

function formatCurrency(value: number | string): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(Number(value) || 0);
}

function formatDate(value: string): string {
  if (!value) return "-";

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(date);
}

onMounted(loadData);
</script>

<template>
  <div class="container-fluid">
    <div class="d-md-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="mb-1">Stock Movements</h4>
        <p class="text-muted mb-0">Riwayat seluruh transaksi stok masuk dan keluar.</p>
      </div>

      <button class="btn btn-primary mt-3 mt-md-0" :disabled="loading" @click="loadData">
        <i class="ti ti-refresh me-1"></i>
        Refresh
      </button>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="row g-3 align-items-end mb-3">
          <div class="col-md-4">
            <label class="form-label">Cari Mutasi</label>
            <input v-model="search" type="search" class="form-control" placeholder="SKU, produk, batch, atau catatan..." @keyup.enter="searchData" />
          </div>

          <div class="col-md-2">
            <label class="form-label">Jenis Mutasi</label>
            <select v-model="movementType" class="form-select">
              <option value="">Semua</option>
              <option value="in">Stok Masuk</option>
              <option value="out">Stok Keluar</option>
            </select>
          </div>

          <div class="col-md-2">
            <label class="form-label">Dari Tanggal</label>
            <input v-model="dateFrom" type="date" class="form-control" />
          </div>

          <div class="col-md-2">
            <label class="form-label">Sampai Tanggal</label>
            <input v-model="dateTo" type="date" class="form-control" />
          </div>

          <div class="col-md-2">
            <label class="form-label">Per Halaman</label>
            <select class="form-select" :value="perPage" @change="changePerPage(($event.target as HTMLSelectElement).value)">
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
            </select>
          </div>

          <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary" :disabled="loading" @click="searchData">
              <i class="ti ti-search me-1"></i>
              Terapkan Filter
            </button>

            <button class="btn btn-light" :disabled="loading" @click="resetFilters">Reset</button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>No.</th>
                <th>Tanggal</th>
                <th>Produk</th>
                <th>Batch</th>
                <th>Referensi</th>
                <th class="text-end">Masuk</th>
                <th class="text-end">Keluar</th>
                <th class="text-end">Biaya / Unit</th>
                <th>Catatan</th>
              </tr>
            </thead>

            <tbody>
              <tr v-if="loading">
                <td colspan="9" class="text-center py-5">
                  <div class="spinner-border spinner-border-sm me-2"></div>
                  Memuat mutasi stok...
                </td>
              </tr>

              <tr v-else-if="rows.length === 0">
                <td colspan="9" class="text-center text-muted py-5">Riwayat mutasi stok tidak ditemukan.</td>
              </tr>

              <tr v-for="(item, index) in rows" :key="item.id">
                <td>{{ (page - 1) * perPage + index + 1 }}</td>

                <td>{{ formatDate(item.movement_date) }}</td>

                <td>
                  <div class="fw-semibold">{{ item.product_name }}</div>
                  <small class="text-muted">{{ item.sku }}</small>
                </td>

                <td>{{ item.batch_number || "-" }}</td>

                <td>
                  <div>{{ item.reference_type }}</div>
                  <small class="text-muted"> #{{ item.reference_id }} </small>
                </td>

                <td class="text-end">
                  <span :class="Number(item.quantity_in) > 0 ? 'text-success fw-semibold' : 'text-muted'">
                    {{ formatNumber(item.quantity_in) }}
                  </span>
                </td>

                <td class="text-end">
                  <span :class="Number(item.quantity_out) > 0 ? 'text-danger fw-semibold' : 'text-muted'">
                    {{ formatNumber(item.quantity_out) }}
                  </span>
                </td>

                <td class="text-end">
                  {{ formatCurrency(item.unit_cost) }}
                </td>

                <td>
                  <span class="text-muted">
                    {{ item.notes || "-" }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="pagination.total > 0" class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-3">
          <small class="text-muted"> Total {{ pagination.total }} mutasi · Halaman {{ pagination.page }} dari {{ pagination.total_pages }} </small>

          <div class="btn-group">
            <button class="btn btn-outline-secondary btn-sm" :disabled="!pagination.has_prev || loading" @click="changePage(page - 1)">Sebelumnya</button>
            <button class="btn btn-outline-secondary btn-sm" :disabled="!pagination.has_next || loading" @click="changePage(page + 1)">Berikutnya</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
