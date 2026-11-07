<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { notify } from "@/utils/notification";
import { inventoryService } from "../services/inventoryService";
import type { StockOpname, InventoryPagination, CreateStockOpnamePayload } from "../types/inventory";

const router = useRouter();

const loading = ref(false);
const saving = ref(false);
const items = ref<StockOpname[]>([]);
const pagination = ref<InventoryPagination | null>(null);

const filters = ref({
  page: 1,
  per_page: 10,
  search: "",
  status: "",
});

const showForm = ref(false);

const form = ref<CreateStockOpnamePayload>({
  opname_number: "",
  opname_date: new Date().toISOString().slice(0, 10),
  notes: "",
});

async function loadData() {
  loading.value = true;

  try {
    const result = await inventoryService.getOpnames(filters.value);
    items.value = result.items;
    pagination.value = result.pagination;
  } catch (error: any) {
    notify.error(error?.response?.data?.message ?? "Gagal memuat stock opname.");
  } finally {
    loading.value = false;
  }
}

function resetForm() {
  form.value = {
    opname_number: "",
    opname_date: new Date().toISOString().slice(0, 10),
    notes: "",
  };
}

async function createOpname() {
  if (!form.value.opname_number.trim() || !form.value.opname_date) {
    notify.warning("Nomor dan tanggal stock opname wajib diisi.");
    return;
  }

  saving.value = true;

  try {
    const result = await inventoryService.createStockOpname(form.value);

    notify.success("Stock opname berhasil dibuat.");
    showForm.value = false;
    resetForm();

    await loadData();

    router.push({
      path: "/pharmacy/inventory/stock-opname-detail",
      query: { id: result.id },
    });
  } catch (error: any) {
    notify.error(error?.response?.data?.message ?? "Gagal membuat stock opname.");
  } finally {
    saving.value = false;
  }
}

function openDetail(id: number) {
  router.push({
    path: "/pharmacy/inventory/stock-opname-detail",
    query: { id },
  });
}

function changePage(page: number) {
  if (!pagination.value || page < 1 || page > pagination.value.total_pages) {
    return;
  }

  filters.value.page = page;
  loadData();
}

function formatDate(value: string) {
  if (!value) return "-";

  return new Date(value).toLocaleDateString("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
}

onMounted(loadData);
</script>

<template>
  <div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
      <div>
        <h4 class="mb-1">Stock Opname</h4>
        <p class="text-muted mb-0">Penghitungan dan penyesuaian stok fisik.</p>
      </div>

      <button class="btn btn-primary" type="button" @click="showForm = !showForm">
        <i class="ti ti-plus me-1"></i>
        Buat Stock Opname
      </button>
    </div>

    <div v-if="showForm" class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0">Form Stock Opname</h5>
      </div>

      <div class="card-body">
        <form @submit.prevent="createOpname">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Nomor Opname</label>
              <input v-model="form.opname_number" type="text" class="form-control" placeholder="Contoh: SO-2026-001" required />
            </div>

            <div class="col-md-4">
              <label class="form-label">Tanggal Opname</label>
              <input v-model="form.opname_date" type="date" class="form-control" required />
            </div>

            <div class="col-md-4">
              <label class="form-label">Catatan</label>
              <input v-model="form.notes" type="text" class="form-control" placeholder="Opsional" />
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 mt-3">
            <button type="button" class="btn btn-light" @click="showForm = false">Batal</button>

            <button type="submit" class="btn btn-primary" :disabled="saving">
              <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
              Buat Opname
            </button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <input
              v-model="filters.search"
              type="search"
              class="form-control"
              placeholder="Cari nomor opname..."
              @keyup.enter="
                filters.page = 1;
                loadData();
              "
            />
          </div>

          <div class="col-md-3">
            <select
              v-model="filters.status"
              class="form-select"
              @change="
                filters.page = 1;
                loadData();
              "
            >
              <option value="">Semua Status</option>
              <option value="draft">Draft</option>
              <option value="posted">Posted</option>
            </select>
          </div>

          <div class="col-md-3">
            <button
              class="btn btn-outline-primary w-100"
              type="button"
              @click="
                filters.page = 1;
                loadData();
              "
            >
              <i class="ti ti-search me-1"></i>
              Cari
            </button>
          </div>
        </div>

        <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-primary"></div>
          <p class="text-muted mt-2 mb-0">Memuat data...</p>
        </div>

        <div v-else class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Nomor Opname</th>
                <th>Tanggal</th>
                <th>Status</th>
                <th>Catatan</th>
                <th class="text-end">Aksi</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="item in items" :key="item.id">
                <td class="fw-semibold">{{ item.opname_number }}</td>
                <td>{{ formatDate(item.opname_date) }}</td>
                <td>
                  <span class="badge" :class="item.status === 'posted' ? 'bg-success' : 'bg-warning text-dark'">
                    {{ item.status }}
                  </span>
                </td>
                <td>{{ item.notes || "-" }}</td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary" type="button" @click="openDetail(Number(item.id))">Detail</button>
                </td>
              </tr>

              <tr v-if="items.length === 0">
                <td colspan="5" class="text-center text-muted py-4">Belum ada data stock opname.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="pagination && pagination.total_pages > 1" class="d-flex justify-content-between align-items-center mt-3">
          <small class="text-muted"> Halaman {{ pagination.page }} dari {{ pagination.total_pages }} ({{ pagination.total }} data) </small>

          <div class="btn-group">
            <button class="btn btn-sm btn-outline-secondary" :disabled="!pagination.has_prev" @click="changePage(pagination.page - 1)">Sebelumnya</button>

            <button class="btn btn-sm btn-outline-secondary" :disabled="!pagination.has_next" @click="changePage(pagination.page + 1)">Berikutnya</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
