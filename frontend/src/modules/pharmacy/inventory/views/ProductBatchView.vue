<script setup lang="ts">
import { onMounted, ref } from "vue";
import { inventoryService } from "../services/inventoryService";
import type { InventoryPagination, ProductBatch } from "../types/inventory";
import { notify } from "@/utils/notification";

const rows = ref<ProductBatch[]>([]);
const loading = ref(false);
const detailLoading = ref(false);

const search = ref("");
const status = ref("");
const page = ref(1);
const perPage = ref(10);

const selectedBatch = ref<ProductBatch | null>(null);

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
    const result = await inventoryService.getBatches({
      page: page.value,
      per_page: perPage.value,
      search: search.value.trim() || undefined,
      status: status.value || undefined,
    });

    rows.value = result.items ?? [];
    pagination.value = result.pagination;
  } catch (error) {
    console.error("Gagal memuat batch:", error);
    notify.error("Gagal memuat data batch.");
  } finally {
    loading.value = false;
  }
}

async function showDetail(id: number): Promise<void> {
  detailLoading.value = true;
  selectedBatch.value = null;

  try {
    selectedBatch.value = await inventoryService.getBatchDetail(id);
  } catch (error) {
    console.error("Gagal memuat detail batch:", error);
    notify.error("Gagal memuat detail batch.");
  } finally {
    detailLoading.value = false;
  }
}

function searchData(): void {
  page.value = 1;
  selectedBatch.value = null;
  loadData();
}

function changePage(nextPage: number): void {
  if (nextPage < 1 || nextPage > pagination.value.total_pages || nextPage === page.value) {
    return;
  }

  page.value = nextPage;
  selectedBatch.value = null;
  loadData();
}

function changePerPage(value: number | string): void {
  perPage.value = Number(value);
  page.value = 1;
  selectedBatch.value = null;
  loadData();
}

function formatNumber(value: number): string {
  return new Intl.NumberFormat("id-ID", {
    maximumFractionDigits: 2,
  }).format(Number(value) || 0);
}

function formatCurrency(value: number): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(Number(value) || 0);
}

function formatDate(value: string | null): string {
  if (!value) return "-";

  const date = new Date(`${value.slice(0, 10)}T00:00:00`);

  return new Intl.DateTimeFormat("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  }).format(date);
}

function isExpired(value: string | null): boolean {
  if (!value) return false;

  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const expiry = new Date(`${value.slice(0, 10)}T00:00:00`);

  return expiry < today;
}

onMounted(loadData);
</script>

<template>
  <div class="container-fluid">
    <div class="d-md-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="mb-1">Product Batches</h4>
        <p class="text-muted mb-0">Daftar batch, tanggal kedaluwarsa, dan sisa persediaan.</p>
      </div>

      <button class="btn btn-primary mt-3 mt-md-0" :disabled="loading" @click="loadData">
        <i class="ti ti-refresh me-1"></i>
        Refresh
      </button>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="row g-2 align-items-end mb-3">
          <div class="col-md-5">
            <label class="form-label">Cari Batch / Produk</label>
            <input v-model="search" type="search" class="form-control" placeholder="Nomor batch, SKU, atau nama produk..." @keyup.enter="searchData" />
          </div>

          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select v-model="status" class="form-select">
              <option value="">Semua Status</option>
              <option value="available">Available</option>
            </select>
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
            <button class="btn btn-primary w-100" :disabled="loading" @click="searchData">
              <i class="ti ti-search me-1"></i>
              Cari
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>No.</th>
                <th>Produk</th>
                <th>Nomor Batch</th>
                <th>Kedaluwarsa</th>
                <th class="text-end">Diterima</th>
                <th class="text-end">Sisa Stok</th>
                <th class="text-end">Biaya / Unit</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>

            <tbody>
              <tr v-if="loading">
                <td colspan="9" class="text-center py-5">
                  <div class="spinner-border spinner-border-sm me-2"></div>
                  Memuat batch...
                </td>
              </tr>

              <tr v-else-if="rows.length === 0">
                <td colspan="9" class="text-center text-muted py-5">Data batch tidak ditemukan.</td>
              </tr>

              <tr v-for="(item, index) in rows" :key="item.id">
                <td>{{ (page - 1) * perPage + index + 1 }}</td>

                <td>
                  <div class="fw-semibold">{{ item.product_name }}</div>
                  <small class="text-muted">{{ item.sku }}</small>
                </td>

                <td>{{ item.batch_number }}</td>

                <td>
                  <span :class="isExpired(item.expired_at) ? 'text-danger fw-semibold' : ''">
                    {{ formatDate(item.expired_at) }}
                  </span>
                </td>

                <td class="text-end">
                  {{ formatNumber(item.received_quantity) }}
                </td>

                <td class="text-end fw-semibold">
                  {{ formatNumber(item.quantity_remaining) }}
                </td>

                <td class="text-end">
                  {{ formatCurrency(item.unit_cost) }}
                </td>

                <td>
                  <span class="badge" :class="item.status === 'available' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'">
                    {{ item.status }}
                  </span>
                </td>

                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary" :disabled="detailLoading" @click="showDetail(item.id)">Detail</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="pagination.total > 0" class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-3">
          <small class="text-muted"> Total {{ pagination.total }} batch · Halaman {{ pagination.page }} dari {{ pagination.total_pages }} </small>

          <div class="btn-group">
            <button class="btn btn-outline-secondary btn-sm" :disabled="!pagination.has_prev || loading" @click="changePage(page - 1)">Sebelumnya</button>
            <button class="btn btn-outline-secondary btn-sm" :disabled="!pagination.has_next || loading" @click="changePage(page + 1)">Berikutnya</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="detailLoading || selectedBatch" class="card mt-4">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0">Detail Product Batch</h5>

        <button class="btn btn-sm btn-light" @click="selectedBatch = null">Tutup</button>
      </div>

      <div v-if="detailLoading" class="card-body text-center py-4">
        <div class="spinner-border spinner-border-sm me-2"></div>
        Memuat detail batch...
      </div>

      <div v-else-if="selectedBatch" class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <small class="text-muted d-block">Produk</small>
            <strong>{{ selectedBatch.product_name }}</strong>
          </div>

          <div class="col-md-4">
            <small class="text-muted d-block">SKU</small>
            <strong>{{ selectedBatch.sku }}</strong>
          </div>

          <div class="col-md-4">
            <small class="text-muted d-block">Nomor Batch</small>
            <strong>{{ selectedBatch.batch_number }}</strong>
          </div>

          <div class="col-md-4">
            <small class="text-muted d-block">Tanggal Kedaluwarsa</small>
            <strong>{{ formatDate(selectedBatch.expired_at) }}</strong>
          </div>

          <div class="col-md-4">
            <small class="text-muted d-block">Jumlah Diterima</small>
            <strong>{{ formatNumber(selectedBatch.received_quantity) }}</strong>
          </div>

          <div class="col-md-4">
            <small class="text-muted d-block">Sisa Stok</small>
            <strong>{{ formatNumber(selectedBatch.quantity_remaining) }}</strong>
          </div>

          <div class="col-md-4">
            <small class="text-muted d-block">Biaya per Unit</small>
            <strong>{{ formatCurrency(selectedBatch.unit_cost) }}</strong>
          </div>

          <div class="col-md-4">
            <small class="text-muted d-block">Status</small>
            <strong>{{ selectedBatch.status }}</strong>
          </div>

          <div class="col-md-4">
            <small class="text-muted d-block">Goods Receipt Item ID</small>
            <strong>{{ selectedBatch.goods_receipt_item_id }}</strong>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
