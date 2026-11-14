<script setup lang="ts">
import { onMounted, ref, watch } from "vue";

import { notify } from "@/utils/notification";
import { useModalStore } from "@/composables/useModal";

import unitService, { type Unit, type UnitPagination } from "../services/unitService";

import UnitFormModal from "../components/UnitFormModal.vue";
import UnitDetailModal from "../components/UnitDetailModal.vue";

const modalStore = useModalStore();

const units = ref<Unit[]>([]);
const loading = ref(false);

const search = ref("");
const currentPage = ref(1);
const perPage = ref(10);

const pagination = ref<UnitPagination>({
  current_page: 1,
  per_page: 10,
  total: 0,
  total_pages: 0,
});

let searchTimeout: ReturnType<typeof setTimeout> | undefined;

function getErrorMessage(error: any): string {
  const message = error?.response?.data?.message;

  if (Array.isArray(message)) return message.join(", ");
  if (typeof message === "string") return message;

  return error?.message || "Terjadi kesalahan.";
}

async function loadUnits(page = currentPage.value): Promise<void> {
  loading.value = true;

  try {
    const response = await unitService.getAll({
      page,
      per_page: perPage.value,
      search: search.value.trim(),
    });

    const result = response.data;

    if (result.error || !result.data) {
      throw new Error(typeof result.message === "string" ? result.message : "Gagal mengambil data satuan.");
    }

    units.value = result.data.items;
    pagination.value = result.data.pagination;
    currentPage.value = result.data.pagination.current_page;
  } catch (error: any) {
    notify.error(getErrorMessage(error));
  } finally {
    loading.value = false;
  }
}

function openCreateForm(): void {
  modalStore.open({
    title: "Tambah Satuan",
    component: UnitFormModal,
    props: {
      unit: null,
    },
    size: "default",
    centered: true,
    onSelect: async () => {
      await loadUnits(1);
    },
  });
}

function openEditForm(unit: Unit): void {
  modalStore.open({
    title: "Ubah Satuan",
    component: UnitFormModal,
    props: {
      unit,
    },
    size: "default",
    centered: true,
    onSelect: async () => {
      await loadUnits(currentPage.value);
    },
  });
}

async function openDetail(unit: Unit): Promise<void> {
  try {
    const response = await unitService.getById(unit.id);
    const result = response.data;

    if (result.error || !result.data) {
      throw new Error(typeof result.message === "string" ? result.message : "Gagal mengambil detail satuan.");
    }

    modalStore.open({
      title: "Detail Satuan",
      component: UnitDetailModal,
      props: {
        unit: result.data,
      },
      size: "default",
      centered: true,
      onSelect: (value) => {
        openEditForm(value as Unit);
      },
    });
  } catch (error: any) {
    notify.error(getErrorMessage(error));
  }
}

async function deleteUnit(unit: Unit): Promise<void> {
  const confirmed = window.confirm(`Hapus satuan "${unit.name}" (${unit.abbreviation})?`);

  if (!confirmed) return;

  try {
    const response = await unitService.delete(unit.id);
    const result = response.data;

    if (result.error) {
      throw new Error(typeof result.message === "string" ? result.message : "Gagal menghapus satuan.");
    }

    notify.success("Satuan berhasil dihapus.");

    const nextPage = units.value.length === 1 && currentPage.value > 1 ? currentPage.value - 1 : currentPage.value;

    await loadUnits(nextPage);
  } catch (error: any) {
    notify.error(getErrorMessage(error));
  }
}

function changePage(page: number): void {
  if (page < 1 || page > pagination.value.total_pages || page === currentPage.value) {
    return;
  }

  loadUnits(page);
}

watch(search, () => {
  if (searchTimeout) clearTimeout(searchTimeout);

  searchTimeout = setTimeout(() => {
    loadUnits(1);
  }, 350);
});

watch(perPage, () => {
  loadUnits(1);
});

onMounted(() => {
  loadUnits(1);
});
</script>

<template>
  <div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">Satuan</h4>
        <p class="text-muted mb-0">Kelola satuan produk apotek.</p>
      </div>

      <button type="button" class="btn btn-primary" @click="openCreateForm">
        <i class="ti ti-plus me-1"></i>
        Tambah Satuan
      </button>
    </div>

    <!-- Table Card -->
    <div class="card">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <div class="d-flex align-items-center gap-2">
            <label class="text-muted">Tampilkan</label>

            <select v-model.number="perPage" class="form-select form-select-sm" style="width: 80px">
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>

            <span class="text-muted">data</span>
          </div>

          <div style="width: 260px">
            <input v-model="search" type="search" class="form-control form-control-sm" placeholder="Cari satuan..." />
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th style="width: 70px">No.</th>
                <th>Nama Satuan</th>
                <th>Singkatan</th>
                <th style="width: 160px" class="text-center">Aksi</th>
              </tr>
            </thead>

            <tbody>
              <tr v-if="loading">
                <td colspan="4" class="text-center py-4">
                  <span class="spinner-border spinner-border-sm me-2"></span>
                  Memuat data...
                </td>
              </tr>

              <tr v-else-if="units.length === 0">
                <td colspan="4" class="text-center text-muted py-4">Data satuan tidak ditemukan.</td>
              </tr>

              <tr v-for="(unit, index) in units" :key="unit.id">
                <td>
                  {{ (currentPage - 1) * perPage + index + 1 }}
                </td>

                <td class="fw-semibold">
                  {{ unit.name }}
                </td>

                <td>
                  <span class="badge bg-light text-dark">
                    {{ unit.abbreviation }}
                  </span>
                </td>

                <td class="text-center">
                  <div class="d-flex justify-content-center gap-1">
                    <button type="button" class="btn btn-sm btn-light" title="Detail" @click="openDetail(unit)">
                      <i class="ti ti-eye"></i>
                    </button>

                    <button type="button" class="btn btn-sm btn-light" title="Ubah" @click="openEditForm(unit)">
                      <i class="ti ti-edit"></i>
                    </button>

                    <button type="button" class="btn btn-sm btn-light text-danger" title="Hapus" @click="deleteUnit(unit)">
                      <i class="ti ti-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
          <div class="text-muted small">
            Menampilkan
            {{ units.length ? (currentPage - 1) * perPage + 1 : 0 }}
            sampai
            {{ (currentPage - 1) * perPage + units.length }}
            dari {{ pagination.total }} data
          </div>

          <nav v-if="pagination.total_pages > 1">
            <ul class="pagination pagination-sm mb-0">
              <li class="page-item" :class="{ disabled: currentPage <= 1 }">
                <button class="page-link" type="button" @click="changePage(currentPage - 1)">Sebelumnya</button>
              </li>

              <li v-for="page in pagination.total_pages" :key="page" class="page-item" :class="{ active: page === currentPage }">
                <button class="page-link" type="button" @click="changePage(page)">
                  {{ page }}
                </button>
              </li>

              <li
                class="page-item"
                :class="{
                  disabled: currentPage >= pagination.total_pages,
                }"
              >
                <button class="page-link" type="button" @click="changePage(currentPage + 1)">Berikutnya</button>
              </li>
            </ul>
          </nav>
        </div>
      </div>
    </div>
  </div>
</template>
