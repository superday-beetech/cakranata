<script setup lang="ts">
import { onMounted, ref } from "vue";
import { inventoryService } from "../services/inventoryService";
import type { InventoryPagination, StockOverview } from "../types/inventory";
import { notify } from "@/utils/notification";

const rows = ref<StockOverview[]>([]);
const loading = ref(false);
const search = ref("");
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
    const result = await inventoryService.getStockOverview({
      page: page.value,
      per_page: perPage.value,
      search: search.value.trim() || undefined,
    });

    rows.value = result.items ?? [];
    pagination.value = result.pagination;
  } catch (error) {
    console.error("Gagal memuat ringkasan stok:", error);
    notify.error("Gagal memuat ringkasan stok.");
  } finally {
    loading.value = false;
  }
}

function searchData(): void {
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

onMounted(loadData);
</script>

<template>
  <div class="container-fluid">
    <div class="d-md-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="mb-1">Stock Overview</h4>
        <p class="text-muted mb-0">Ringkasan persediaan produk berdasarkan batch.</p>
      </div>

      <button type="button" class="btn btn-primary mt-3 mt-md-0" :disabled="loading" @click="loadData">
        <i class="ti ti-refresh me-1"></i>
        Refresh
      </button>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="row g-2 align-items-end mb-3">
          <div class="col-md-6">
            <label class="form-label">Cari Produk</label>
            <input v-model="search" type="search" class="form-control" placeholder="SKU, barcode, atau nama produk..." @keyup.enter="searchData" />
          </div>

          <div class="col-md-2">
            <label class="form-label">Per Halaman</label>
            <select class="form-select" :value="perPage" @change="changePerPage(($event.target as HTMLSelectElement).value)">
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
            </select>
          </div>

          <div class="col-md-2">
            <button type="button" class="btn btn-primary w-100" :disabled="loading" @click="searchData">
              <i class="ti ti-search me-1"></i>
              Cari
            </button>
          </div>

          <div class="col-md-2">
            <button
              type="button"
              class="btn btn-light w-100"
              :disabled="loading"
              @click="
                search = '';
                searchData();
              "
            >
              Reset
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th style="width: 50px">No.</th>
                <th>SKU</th>
                <th>Produk</th>
                <th class="text-end">Stok Tersedia</th>
                <th class="text-end">Stok Kedaluwarsa</th>
                <th class="text-center">Jumlah Batch</th>
              </tr>
            </thead>

            <tbody>
              <tr v-if="loading">
                <td colspan="6" class="text-center py-5">
                  <div class="spinner-border spinner-border-sm me-2"></div>
                  Memuat data stok...
                </td>
              </tr>

              <tr v-else-if="rows.length === 0">
                <td colspan="6" class="text-center text-muted py-5">Data stok tidak ditemukan.</td>
              </tr>

              <tr v-for="(item, index) in rows" :key="item.product_id">
                <td>
                  {{ (page - 1) * perPage + index + 1 }}
                </td>
                <td>
                  <span class="fw-semibold">{{ item.sku }}</span>
                  <div class="small text-muted">
                    {{ item.barcode || "-" }}
                  </div>
                </td>
                <td>{{ item.product_name }}</td>
                <td class="text-end fw-semibold">
                  {{ formatNumber(item.available_stock) }}
                </td>
                <td class="text-end">
                  <span :class="Number(item.expired_stock) > 0 ? 'text-danger fw-semibold' : 'text-muted'">
                    {{ formatNumber(item.expired_stock) }}
                  </span>
                </td>
                <td class="text-center">
                  {{ item.batch_count }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="pagination.total > 0" class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-3">
          <small class="text-muted"> Total {{ pagination.total }} produk · Halaman {{ pagination.page }} dari {{ pagination.total_pages }} </small>

          <div class="btn-group">
            <button class="btn btn-outline-secondary btn-sm" :disabled="!pagination.has_prev || loading" @click="changePage(page - 1)">Sebelumnya</button>
            <button class="btn btn-outline-secondary btn-sm" :disabled="!pagination.has_next || loading" @click="changePage(page + 1)">Berikutnya</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
