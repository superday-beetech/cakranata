<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue";

import { notify } from "@/utils/notification";
import { useModalStore } from "@/composables/useModal";

import organizationService from "../services/organizationService";

import OrganizationFormModal from "../components/OrganizationFormModal.vue";
import OrganizationDetailModal from "../components/OrganizationDetailModal.vue";
import OrganizationConfirmModal from "../components/OrganizationConfirmModal.vue";

import type { Organization, OrganizationPagination } from "../types/organization";

const modalStore = useModalStore();

const organizations = ref<Organization[]>([]);
const loading = ref(false);

const search = ref("");
const currentPage = ref(1);
const perPage = ref(10);

const pagination = ref<OrganizationPagination>({
  current_page: 1,
  per_page: 10,
  total: 0,
  total_pages: 0,
});

const organizationTypeLabels: Record<string, string> = {
  business: "Perusahaan / Badan Usaha",
  cooperative: "Koperasi",
  foundation: "Yayasan",
  government: "Instansi Pemerintah",
  community: "Komunitas / Kelompok",
  non_profit: "Organisasi Nirlaba",
  other: "Lainnya",
};

function formatOrganizationType(type: string): string {
  return organizationTypeLabels[type] ?? type;
}

const visiblePages = computed<(number | string)[]>(() => {
  const total = pagination.value.total_pages;
  const current = currentPage.value;

  if (total <= 7) {
    return Array.from({ length: total }, (_, index) => index + 1);
  }

  if (current <= 4) {
    return [1, 2, 3, 4, 5, "...", total - 1, total];
  }

  if (current >= total - 3) {
    return [1, 2, "...", total - 4, total - 3, total - 2, total - 1, total];
  }

  return [1, 2, "...", current - 1, current, current + 1, "...", total - 1, total];
});

const rowNumber = (index: number): number => {
  const page = Number(pagination.value.current_page) || 1;
  const limit = Number(pagination.value.per_page) || 10;

  return (page - 1) * limit + index + 1;
};

let searchTimeout: ReturnType<typeof setTimeout> | undefined;

function getErrorMessage(error: any): string {
  const message = error?.response?.data?.message;

  if (Array.isArray(message)) return message.join(", ");
  if (typeof message === "string") return message;

  return error?.message || "Terjadi kesalahan.";
}

async function loadOrganizations(page = currentPage.value): Promise<void> {
  loading.value = true;

  try {
    const response = await organizationService.getAll({
      page,
      per_page: perPage.value,
      q: search.value.trim() || undefined,
    });

    const result = response.data;

    if (result.error || !result.data) {
      throw new Error(typeof result.message === "string" ? result.message : "Gagal mengambil data organisasi.");
    }

    organizations.value = result.data.items ?? [];

    pagination.value = {
      current_page: Number(result.data.pagination?.current_page) || 1,
      per_page: Number(result.data.pagination?.per_page) || perPage.value,
      total: Number(result.data.pagination?.total) || 0,
      total_pages: Number(result.data.pagination?.total_pages) || 0,
    };

    currentPage.value = pagination.value.current_page;
  } catch (error: any) {
    notify.error(getErrorMessage(error));
  } finally {
    loading.value = false;
  }
}

function openCreateForm(): void {
  modalStore.open({
    title: "Tambah Organisasi",
    component: OrganizationFormModal,
    props: {
      organization: null,
    },
    size: "lg",
    centered: true,
    scrollable: true,
    onSelect: async () => {
      await loadOrganizations(1);
    },
  });
}

function openEditForm(organization: Organization): void {
  modalStore.open({
    title: "Ubah Organisasi",
    component: OrganizationFormModal,
    props: {
      organization,
    },
    size: "lg",
    centered: true,
    scrollable: true,
    onSelect: async () => {
      await loadOrganizations(currentPage.value);
    },
  });
}

async function openDetail(organization: Organization): Promise<void> {
  try {
    const response = await organizationService.getById(organization.id);

    const result = response.data;

    if (result.error || !result.data) {
      throw new Error(typeof result.message === "string" ? result.message : "Gagal mengambil detail organisasi.");
    }

    modalStore.open({
      title: "Detail Organisasi",
      component: OrganizationDetailModal,
      props: {
        organization: result.data,
      },
      size: "lg",
      centered: true,
      scrollable: true,
      onSelect: (value) => {
        openEditForm(value as Organization);
      },
    });
  } catch (error: any) {
    notify.error(getErrorMessage(error));
  }
}

function openArchiveConfirmation(organization: Organization): void {
  modalStore.open({
    title: "Arsipkan Organisasi",
    component: OrganizationConfirmModal,
    props: {
      organization,
    },
    size: "default",
    centered: true,
    scrollable: false,
    onSelect: async () => {
      const nextPage = organizations.value.length === 1 && currentPage.value > 1 ? currentPage.value - 1 : currentPage.value;

      await loadOrganizations(nextPage);
    },
  });
}

function changePage(page: number): void {
  if (loading.value || page < 1 || page > pagination.value.total_pages || page === currentPage.value) {
    return;
  }

  loadOrganizations(page);
}

function formatDate(value?: string | null): string {
  if (!value) return "-";

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat("id-ID", {
    dateStyle: "medium",
  }).format(date);
}

watch(search, () => {
  if (searchTimeout) clearTimeout(searchTimeout);

  searchTimeout = setTimeout(() => {
    loadOrganizations(1);
  }, 350);
});

watch(perPage, () => {
  loadOrganizations(1);
});

onMounted(() => {
  loadOrganizations(1);
});

onUnmounted(() => {
  if (searchTimeout) clearTimeout(searchTimeout);
});
</script>

<template>
  <div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <h4 class="mb-1">Organisasi</h4>
        <p class="text-muted mb-0">Kelola master data organisasi dalam sistem.</p>
      </div>

      <button type="button" class="btn btn-primary" @click="openCreateForm">
        <i class="ti ti-plus me-1"></i>
        Tambah Organisasi
      </button>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
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

          <div style="width: 280px">
            <label class="form-label small mb-1"> Pencarian </label>

            <input v-model="search" type="search" class="form-control form-control-sm" placeholder="Kode, nama, atau slug..." />
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th style="width: 60px">No.</th>
                <th>Organisasi</th>
                <th>Kode</th>
                <th>Jenis</th>
                <th>Timezone</th>
                <th>Mata Uang</th>
                <th>Status</th>
                <th>Dibuat</th>
                <th class="text-center" style="width: 130px">Aksi</th>
              </tr>
            </thead>

            <tbody>
              <tr v-if="loading">
                <td colspan="9" class="text-center py-4">
                  <span class="spinner-border spinner-border-sm me-2"></span>
                  Memuat data...
                </td>
              </tr>

              <tr v-else-if="organizations.length === 0">
                <td colspan="9" class="text-center text-muted py-4">Data organisasi tidak ditemukan.</td>
              </tr>

              <tr v-for="(organization, index) in organizations" :key="organization.id">
                <td>{{ rowNumber(index) }}</td>

                <td>
                  <div class="fw-semibold">
                    {{ organization.name }}
                  </div>

                  <small class="text-muted">
                    {{ organization.legal_name || organization.slug }}
                  </small>
                </td>

                <td>
                  <span class="fw-semibold">
                    {{ organization.code }}
                  </span>
                </td>

                <td>
                  {{ formatOrganizationType(organization.organization_type) }}
                </td>

                <td>{{ organization.timezone }}</td>

                <td>{{ organization.currency }}</td>

                <td>
                  <span
                    class="badge"
                    :class="{
                      'bg-success': organization.status === 'active',
                      'bg-secondary': organization.status === 'inactive',
                      'bg-warning text-dark': organization.status === 'suspended',
                    }"
                  >
                    {{ organization.status }}
                  </span>
                </td>

                <td>
                  {{ formatDate(organization.created_at) }}
                </td>

                <td class="text-center">
                  <div class="d-flex justify-content-center gap-1">
                    <button type="button" class="btn btn-sm btn-light" title="Detail" @click="openDetail(organization)">
                      <i class="ti ti-eye"></i>
                    </button>

                    <button type="button" class="btn btn-sm btn-light" title="Ubah" @click="openEditForm(organization)">
                      <i class="ti ti-edit"></i>
                    </button>

                    <button type="button" class="btn btn-sm btn-light text-danger" title="Arsipkan" @click="openArchiveConfirmation(organization)">
                      <i class="ti ti-archive"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
          <div class="text-muted small">
            Menampilkan
            {{ organizations.length ? (pagination.current_page - 1) * pagination.per_page + 1 : 0 }}
            sampai
            {{ (pagination.current_page - 1) * pagination.per_page + organizations.length }}
            dari {{ pagination.total }} data
          </div>

          <nav v-if="pagination.total_pages > 0" aria-label="Navigasi halaman">
            <ul class="pagination pagination-sm mb-0">
              <li class="page-item" :class="{ disabled: currentPage === 1 }">
                <button class="page-link" type="button" :disabled="currentPage === 1" @click="changePage(1)">Pertama</button>
              </li>

              <li class="page-item" :class="{ disabled: currentPage === 1 }">
                <button class="page-link" type="button" :disabled="currentPage === 1" @click="changePage(currentPage - 1)">Sebelumnya</button>
              </li>

              <li
                v-for="(page, index) in visiblePages"
                :key="`${page}-${index}`"
                class="page-item"
                :class="{
                  active: page === currentPage,
                  disabled: page === '...',
                }"
              >
                <span v-if="page === '...'" class="page-link"> ... </span>

                <button v-else class="page-link" type="button" @click="changePage(Number(page))">
                  {{ page }}
                </button>
              </li>

              <li
                class="page-item"
                :class="{
                  disabled: currentPage === pagination.total_pages,
                }"
              >
                <button class="page-link" type="button" :disabled="currentPage === pagination.total_pages" @click="changePage(currentPage + 1)">Selanjutnya</button>
              </li>

              <li
                class="page-item"
                :class="{
                  disabled: currentPage === pagination.total_pages,
                }"
              >
                <button class="page-link" type="button" :disabled="currentPage === pagination.total_pages" @click="changePage(pagination.total_pages)">Akhir</button>
              </li>
            </ul>
          </nav>
        </div>
      </div>
    </div>
  </div>
</template>
