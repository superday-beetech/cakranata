<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";

import organizationService from "../services/organizationService";
import organizationUnitService from "../services/organizationUnitService";
import organizationMembershipService from "../services/organizationMembershipService";

import OrganizationMembershipFormModal from "../components/OrganizationMembershipFormModal.vue";
import OrganizationMembershipDetailModal from "../components/OrganizationMembershipDetailModal.vue";
import OrganizationMembershipConfirmModal from "../components/OrganizationMembershipConfirmModal.vue";

import type { Organization } from "../types/organization";
import type { OrganizationUnit } from "../types/organizationUnit";
import type { OrganizationMembership } from "../types/organizationMembership";

import { notify } from "@/utils/notification";
import { useModalStore } from "@/composables/modal";

interface UserOption {
  id: number;
  username: string;
}

const modalStore = useModalStore();

// ======================================================
// STATE
// ======================================================

const organizations = ref<Organization[]>([]);
const units = ref<OrganizationUnit[]>([]);
const users = ref<UserOption[]>([]);
const memberships = ref<OrganizationMembership[]>([]);

const selectedOrganizationId = ref<number | null>(null);

const loadingOrganizations = ref(false);
const loadingMemberships = ref(false);
const loadingUsers = ref(false);
const loadingUnits = ref(false);

const search = ref("");
const statusFilter = ref("");

const currentPage = ref(1);
const perPage = ref(10);

const pagination = ref({
  current_page: 1,
  per_page: 10,
  total: 0,
  total_pages: 1,
});

let searchTimer: ReturnType<typeof setTimeout> | undefined;

// ======================================================
// COMPUTED
// ======================================================

const visiblePages = computed(() => {
  const total = pagination.value.total_pages;
  const current = pagination.value.current_page;

  const start = Math.max(1, current - 2);
  const end = Math.min(total, current + 2);

  const pages: number[] = [];

  for (let page = start; page <= end; page++) {
    pages.push(page);
  }

  return pages;
});

const rowNumber = (index: number) => {
  const page = Number(pagination.value.current_page) || 1;
  const limit = Number(pagination.value.per_page) || 10;

  return (page - 1) * limit + index + 1;
};

const selectedOrganization = computed(() => organizations.value.find((organization) => organization.id === selectedOrganizationId.value));

// ======================================================
// HELPERS
// ======================================================

function getErrorMessage(error: any): string {
  return error?.response?.data?.messages?.error || error?.response?.data?.message || error?.message || "Terjadi kesalahan pada server.";
}

function getItems<T>(data: any): T[] {
  if (Array.isArray(data)) {
    return data;
  }

  if (Array.isArray(data?.items)) {
    return data.items;
  }

  return [];
}

function formatDate(value?: string | null): string {
  if (!value) return "-";

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat("id-ID", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
}

// ======================================================
// ORGANIZATIONS
// ======================================================

async function loadOrganizations() {
  loadingOrganizations.value = true;

  try {
    const response = await organizationService.getAll({
      page: 1,
      per_page: 100,
    });

    const result = response.data;

    if (result.error) {
      notify.error(String(result.message) || "Gagal memuat organisasi.");
      return;
    }

    organizations.value = getItems<Organization>(result.data);

    if (!selectedOrganizationId.value && organizations.value.length > 0) {
      selectedOrganizationId.value = organizations.value[0].id;
    }
  } catch (error: any) {
    notify.error(getErrorMessage(error));
  } finally {
    loadingOrganizations.value = false;
  }
}

// ======================================================
// MEMBERSHIPS
// ======================================================

async function loadMemberships() {
  if (!selectedOrganizationId.value) {
    memberships.value = [];
    return;
  }

  loadingMemberships.value = true;

  try {
    const response = await organizationMembershipService.getAll(selectedOrganizationId.value, {
      page: currentPage.value,
      per_page: perPage.value,
      q: search.value || undefined,
      status: statusFilter.value || undefined,
    });

    const result = response.data;

    if (result.error) {
      notify.error(result.message || "Gagal memuat membership.");
      return;
    }

    const data = result.data;

    memberships.value = getItems<OrganizationMembership>(data);

    if (data?.pagination) {
      pagination.value = {
        current_page: Number(data.pagination.current_page) || 1,
        per_page: Number(data.pagination.per_page) || perPage.value,
        total: Number(data.pagination.total) || 0,
        total_pages: Number(data.pagination.total_pages) || 1,
      };
    } else {
      pagination.value = {
        current_page: 1,
        per_page: perPage.value,
        total: memberships.value.length,
        total_pages: 1,
      };
    }
  } catch (error: any) {
    notify.error(getErrorMessage(error));
  } finally {
    loadingMemberships.value = false;
  }
}

// ======================================================
// USERS
// ======================================================

async function loadUsers() {
  loadingUsers.value = true;

  try {
    const response = await organizationMembershipService.getUsers({
      page: 1,
      per_page: 100,
    });

    const result = response.data;

    if (result.error) {
      notify.error(result.message || "Gagal memuat daftar user Shield.");
      return;
    }

    users.value = getItems<UserOption>(result.data);
  } catch (error: any) {
    notify.error(getErrorMessage(error));
  } finally {
    loadingUsers.value = false;
  }
}

// ======================================================
// ORGANIZATION UNITS
// ======================================================

async function loadUnits() {
  if (!selectedOrganizationId.value) {
    units.value = [];
    return;
  }

  loadingUnits.value = true;

  try {
    const response = await organizationUnitService.getAll(selectedOrganizationId.value);

    const result = response.data;

    if (result.error) {
      notify.error(result.message || "Gagal memuat unit organisasi.");
      return;
    }

    units.value = getItems<OrganizationUnit>(result.data);
  } catch (error: any) {
    notify.error(getErrorMessage(error));
  } finally {
    loadingUnits.value = false;
  }
}

// ======================================================
// MODALS
// ======================================================

async function openCreateForm() {
  if (!selectedOrganizationId.value) {
    notify.warning("Pilih organisasi terlebih dahulu.");
    return;
  }

  await Promise.all([loadUsers(), loadUnits()]);

  modalStore.open({
    title: "Tambah Membership",
    component: OrganizationMembershipFormModal,
    size: "lg",
    centered: true,
    props: {
      organizationId: Number(selectedOrganizationId.value),
      membership: null,
      users: users.value,
      units: units.value,
    },
    onSelect: () => {
      loadMemberships();
    },
  });
}

async function openEditForm(membership: OrganizationMembership) {
  if (!selectedOrganizationId.value) return;

  await Promise.all([loadUsers(), loadUnits()]);

  modalStore.open({
    title: "Ubah Membership",
    component: OrganizationMembershipFormModal,
    size: "lg",
    centered: true,
    props: {
      organizationId: Number(selectedOrganizationId.value),
      membership,
      users: users.value,
      units: units.value,
    },
    onSelect: () => {
      loadMemberships();
    },
  });
}

function openDetail(membership: OrganizationMembership) {
  modalStore.open({
    title: "Detail Membership",
    component: OrganizationMembershipDetailModal,
    size: "lg",
    centered: true,
    props: {
      membership,
    },
    onSelect: (value) => {
      openEditForm(value as OrganizationMembership);
    },
  });
}

function openConfirm(membership: OrganizationMembership) {
  if (!selectedOrganizationId.value) return;

  modalStore.open({
    title: "Konfirmasi Membership",
    component: OrganizationMembershipConfirmModal,
    size: "default",
    centered: true,
    props: {
      //   organizationId: selectedOrganizationId.value,
      organizationId: Number(selectedOrganizationId.value),
      membership,
    },
    onSelect: () => {
      loadMemberships();
    },
  });
}

// ======================================================
// PAGINATION & FILTER
// ======================================================

function changePage(page: number) {
  if (page < 1 || page > pagination.value.total_pages || page === currentPage.value) {
    return;
  }

  currentPage.value = page;
  loadMemberships();
}

function changePerPage() {
  currentPage.value = 1;
  loadMemberships();
}

function applyFilter() {
  currentPage.value = 1;
  loadMemberships();
}

watch(selectedOrganizationId, async () => {
  currentPage.value = 1;

  await Promise.all([loadUnits(), loadMemberships()]);
});

watch(search, () => {
  if (searchTimer) {
    clearTimeout(searchTimer);
  }

  searchTimer = setTimeout(() => {
    currentPage.value = 1;
    loadMemberships();
  }, 400);
});

watch(statusFilter, () => {
  currentPage.value = 1;
  loadMemberships();
});

// ======================================================
// LIFECYCLE
// ======================================================

onMounted(() => {
  loadOrganizations();
});
</script>

<template>
  <div class="container-fluid">
    <!-- Header -->
    <div class="row">
      <div class="col-12">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
          <div>
            <h4 class="mb-1">Organization Membership</h4>
            <p class="text-muted mb-0">Kelola keanggotaan user dalam organisasi.</p>
          </div>

          <button type="button" class="btn btn-primary" :disabled="!selectedOrganizationId" @click="openCreateForm">
            <i class="ti ti-plus me-1"></i>
            Tambah Membership
          </button>
        </div>
      </div>
    </div>

    <!-- Organization selector -->
    <div class="card">
      <div class="card-body">
        <div class="row align-items-end">
          <div class="col-md-6 col-lg-5">
            <label class="form-label"> Organisasi </label>

            <select v-model.number="selectedOrganizationId" class="form-select" :disabled="loadingOrganizations">
              <option :value="null">Pilih organisasi</option>

              <option v-for="organization in organizations" :key="organization.id" :value="organization.id">
                {{ organization.name }}
              </option>
            </select>
          </div>

          <div class="col-md-6 col-lg-7 mt-3 mt-md-0">
            <div v-if="selectedOrganization" class="text-muted small">
              Organisasi aktif:
              <strong class="text-dark">
                {{ selectedOrganization.name }}
              </strong>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Membership table -->
    <div class="card">
      <div class="card-body">
        <!-- Filters -->
        <div class="row align-items-end justify-content-between mb-4">
          <div class="col-md-5 col-lg-4">
            <label class="form-label"> Cari Membership </label>

            <div class="input-group">
              <span class="input-group-text">
                <i class="ti ti-search"></i>
              </span>

              <input v-model="search" type="text" class="form-control" placeholder="Cari berdasarkan user ID atau role..." :disabled="!selectedOrganizationId" />
            </div>
          </div>

          <div class="col-md-4 col-lg-3 mt-3 mt-md-0">
            <label class="form-label"> Status </label>

            <select v-model="statusFilter" class="form-select" :disabled="!selectedOrganizationId">
              <option value="">Semua Status</option>
              <option value="active">Aktif</option>
              <option value="inactive">Tidak Aktif</option>
            </select>
          </div>
        </div>

        <!-- Loading -->
        <div v-if="loadingMemberships" class="text-center py-5">
          <div class="spinner-border text-primary" role="status"></div>

          <div class="text-muted mt-2">Memuat data membership...</div>
        </div>

        <!-- No organization -->
        <div v-else-if="!selectedOrganizationId" class="text-center py-5">
          <i class="ti ti-building fs-1 text-muted"></i>
          <h5 class="mt-3">Pilih Organisasi</h5>
          <p class="text-muted mb-0">Pilih organisasi untuk melihat daftar membership.</p>
        </div>

        <!-- Empty -->
        <div v-else-if="memberships.length === 0" class="text-center py-5">
          <i class="ti ti-users fs-1 text-muted"></i>
          <h5 class="mt-3">Belum Ada Membership</h5>
          <p class="text-muted mb-3">Belum ada user yang terdaftar pada organisasi ini.</p>

          <button type="button" class="btn btn-primary" @click="openCreateForm">
            <i class="ti ti-plus me-1"></i>
            Tambah Membership
          </button>
        </div>

        <!-- Table -->
        <div v-else class="table-responsive">
          <table class="table table-hover align-middle text-nowrap">
            <thead>
              <tr>
                <th width="60">No.</th>
                <th>User ID</th>
                <th>Unit Organisasi</th>
                <th>Role</th>
                <th>Default</th>
                <th>Status</th>
                <th width="130" class="text-center">Aksi</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="(membership, index) in memberships" :key="membership.id">
                <td>
                  {{ rowNumber(index) }}
                </td>

                <td>
                  <div class="fw-semibold">
                    {{ membership.username || `User #${membership.user_id}` }}
                  </div>

                  <small class="text-muted"> ID: {{ membership.user_id }} </small>
                </td>

                <td>
                  {{ membership.unit_name || (membership.unit_id ? `Unit #${membership.unit_id}` : "Belum ditempatkan") }}
                </td>

                <td>
                  {{ membership.membership_role }}
                </td>

                <td>
                  <span v-if="Boolean(Number(membership.is_default))" class="badge bg-light-success text-success"> Default </span>

                  <span v-else class="text-muted"> - </span>
                </td>

                <td>
                  <span class="badge" :class="membership.status === 'active' ? 'bg-light-success text-success' : 'bg-light-secondary text-secondary'">
                    {{ membership.status === "active" ? "Aktif" : "Tidak Aktif" }}
                  </span>
                </td>

                <td class="text-center">
                  <div class="d-flex justify-content-center gap-1">
                    <button type="button" class="btn btn-sm btn-light-info" title="Detail" @click="openDetail(membership)">
                      <i class="ti ti-eye"></i>
                    </button>

                    <button type="button" class="btn btn-sm btn-light-primary" title="Ubah" @click="openEditForm(membership)">
                      <i class="ti ti-edit"></i>
                    </button>

                    <button v-if="membership.status === 'active'" type="button" class="btn btn-sm btn-light-warning" title="Nonaktifkan" @click="openConfirm(membership)">
                      <i class="ti ti-user-off"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="!loadingMemberships && memberships.length > 0" class="d-flex flex-wrap justify-content-between align-items-center mt-4 gap-3">
          <div class="d-flex align-items-center gap-2 text-muted small">
            <span>Baris per halaman</span>

            <select v-model.number="perPage" class="form-select form-select-sm" style="width: 75px" @change="changePerPage">
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>

            <span> Total {{ pagination.total }} data </span>
          </div>

          <nav>
            <ul class="pagination pagination-sm mb-0">
              <li
                class="page-item"
                :class="{
                  disabled: currentPage <= 1,
                }"
              >
                <button type="button" class="page-link" @click="changePage(currentPage - 1)">
                  <i class="ti ti-chevron-left"></i>
                </button>
              </li>

              <li
                v-for="page in visiblePages"
                :key="page"
                class="page-item"
                :class="{
                  active: page === currentPage,
                }"
              >
                <button type="button" class="page-link" @click="changePage(page)">
                  {{ page }}
                </button>
              </li>

              <li
                class="page-item"
                :class="{
                  disabled: currentPage >= pagination.total_pages,
                }"
              >
                <button type="button" class="page-link" @click="changePage(currentPage + 1)">
                  <i class="ti ti-chevron-right"></i>
                </button>
              </li>
            </ul>
          </nav>
        </div>
      </div>
    </div>
  </div>
</template>
