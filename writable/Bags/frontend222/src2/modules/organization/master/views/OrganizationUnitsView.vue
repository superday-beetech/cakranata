<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";

import { notify } from "@/utils/notification";
import { useModalStore } from "@/composables/modal";

import organizationService from "../services/organizationService";
import organizationUnitService from "../services/organizationUnitService";

import OrganizationUnitFormModal from "../components/OrganizationUnitFormModal.vue";
import OrganizationUnitDetailModal from "../components/OrganizationUnitDetailModal.vue";
import OrganizationUnitConfirmModal from "../components/OrganizationUnitConfirmModal.vue";

import type { Organization } from "../types/organization";
import type { OrganizationUnit } from "../types/organizationUnit";

const modalStore = useModalStore();

const organizations = ref<Organization[]>([]);
const units = ref<OrganizationUnit[]>([]);

const selectedOrganizationId = ref<number | null>(null);

const loadingOrganizations = ref(false);
const loadingUnits = ref(false);

const search = ref("");

const unitTypeLabels: Record<string, string> = {
  head_office: "Kantor Pusat",
  branch: "Cabang",
  division: "Divisi",
  department: "Departemen",
  warehouse: "Gudang",
  operational_location: "Lokasi Operasional",
  other: "Lainnya",
};

const filteredUnits = computed(() => {
  const keyword = search.value.trim().toLowerCase();

  if (!keyword) return units.value;

  return units.value.filter((unit) => {
    const parent = getParentUnit(unit);

    return unit.code.toLowerCase().includes(keyword) || unit.name.toLowerCase().includes(keyword) || unit.unit_type.toLowerCase().includes(keyword) || parent?.name.toLowerCase().includes(keyword);
  });
});

function getErrorMessage(error: any): string {
  const message = error?.response?.data?.message;

  if (Array.isArray(message)) return message.join(", ");
  if (typeof message === "string") return message;

  return error?.message || "Terjadi kesalahan.";
}

function getParentUnit(unit: OrganizationUnit): OrganizationUnit | undefined {
  if (!unit.parent_id) return undefined;

  return units.value.find((item) => item.id === unit.parent_id);
}

function getUnitDepth(unit: OrganizationUnit): number {
  let depth = 0;
  let parentId = unit.parent_id;
  const visited = new Set<number>([unit.id]);

  while (parentId) {
    if (visited.has(parentId)) break;

    visited.add(parentId);

    const parent = units.value.find((item) => item.id === parentId);

    if (!parent) break;

    depth++;
    parentId = parent.parent_id;
  }

  return depth;
}

function formatUnitType(type: string): string {
  return unitTypeLabels[type] ?? type;
}

async function loadOrganizations(): Promise<void> {
  loadingOrganizations.value = true;

  try {
    const response = await organizationService.getAll({
      page: 1,
      per_page: 100,
    });

    const result = response.data;

    if (result.error || !result.data) {
      throw new Error(typeof result.message === "string" ? result.message : "Gagal mengambil data organisasi.");
    }

    const data = result.data;

    organizations.value = Array.isArray(data) ? data : (data.items ?? []);

    if (selectedOrganizationId.value && organizations.value.some((item) => item.id === selectedOrganizationId.value)) {
      return;
    }

    selectedOrganizationId.value = organizations.value.length ? organizations.value[0].id : null;
  } catch (error: any) {
    notify.error(getErrorMessage(error));
  } finally {
    loadingOrganizations.value = false;
  }
}

async function loadUnits(): Promise<void> {
  if (!selectedOrganizationId.value) {
    units.value = [];
    return;
  }

  loadingUnits.value = true;

  try {
    const response = await organizationUnitService.getAll(selectedOrganizationId.value);

    const result = response.data;

    if (result.error || result.data === undefined) {
      throw new Error(typeof result.message === "string" ? result.message : "Gagal mengambil data unit organisasi.");
    }

    const data = result.data;

    units.value = Array.isArray(data) ? data : (data.items ?? []);
  } catch (error: any) {
    units.value = [];
    notify.error(getErrorMessage(error));
  } finally {
    loadingUnits.value = false;
  }
}

function openCreateForm(): void {
  if (!selectedOrganizationId.value) {
    notify.warning("Pilih organisasi terlebih dahulu.");
    return;
  }

  modalStore.open({
    title: "Tambah Unit Organisasi",
    component: OrganizationUnitFormModal,
    props: {
      organizationId: selectedOrganizationId.value,
      unit: null,
      units: units.value,
    },
    size: "lg",
    centered: true,
    scrollable: true,
    onSelect: async () => {
      await loadUnits();
    },
  });
}

function openEditForm(unit: OrganizationUnit): void {
  if (!selectedOrganizationId.value) return;

  modalStore.open({
    title: "Ubah Unit Organisasi",
    component: OrganizationUnitFormModal,
    props: {
      organizationId: selectedOrganizationId.value,
      unit,
      units: units.value,
    },
    size: "lg",
    centered: true,
    scrollable: true,
    onSelect: async () => {
      await loadUnits();
    },
  });
}

async function openDetail(unit: OrganizationUnit): Promise<void> {
  if (!selectedOrganizationId.value) return;

  try {
    const response = await organizationUnitService.getUnit(selectedOrganizationId.value, unit.id);

    const result = response.data;

    if (result.error || !result.data) {
      throw new Error(typeof result.message === "string" ? result.message : "Gagal mengambil detail unit organisasi.");
    }

    modalStore.open({
      title: "Detail Unit Organisasi",
      component: OrganizationUnitDetailModal,
      props: {
        organizationUnit: result.data,
        units: units.value,
      },
      size: "lg",
      centered: true,
      scrollable: true,
      onSelect: (value) => {
        openEditForm(value as OrganizationUnit);
      },
    });
  } catch (error: any) {
    notify.error(getErrorMessage(error));
  }
}

function openDeactivateConfirmation(unit: OrganizationUnit): void {
  if (!selectedOrganizationId.value) return;

  modalStore.open({
    title: "Nonaktifkan Unit Organisasi",
    component: OrganizationUnitConfirmModal,
    props: {
      organizationId: selectedOrganizationId.value,
      unit,
    },
    size: "default",
    centered: true,
    scrollable: false,
    onSelect: async () => {
      await loadUnits();
    },
  });
}

watch(selectedOrganizationId, async (newValue) => {
  search.value = "";
  units.value = [];

  if (newValue) {
    await loadUnits();
  }
});

onMounted(() => {
  loadOrganizations();
});
</script>

<template>
  <div class="container-fluid">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <h4 class="mb-1">Unit Organisasi</h4>
        <p class="text-muted mb-0">Kelola struktur unit dan hierarki organisasi.</p>
      </div>

      <button type="button" class="btn btn-primary" :disabled="!selectedOrganizationId" @click="openCreateForm">
        <i class="ti ti-plus me-1"></i>
        Tambah Unit
      </button>
    </div>

    <div class="card">
      <div class="card-body">
        <!-- Filter -->
        <div class="row align-items-end justify-content-between g-3 mb-4">
          <div class="col-md-6">
            <label for="organization-filter" class="form-label"> Organisasi </label>

            <select id="organization-filter" v-model.number="selectedOrganizationId" class="form-select" :disabled="loadingOrganizations">
              <option :value="null" disabled>Pilih organisasi</option>

              <option v-for="organization in organizations" :key="organization.id" :value="organization.id">{{ organization.name }} ({{ organization.code }})</option>
            </select>
          </div>

          <div class="col-md-4">
            <label for="unit-search" class="form-label"> Pencarian </label>

            <input id="unit-search" v-model="search" type="search" class="form-control" placeholder="Kode atau nama unit..." :disabled="!selectedOrganizationId" />
          </div>
        </div>

        <!-- Loading organisasi -->
        <div v-if="loadingOrganizations" class="text-center text-muted py-5">
          <span class="spinner-border spinner-border-sm me-2"></span>
          Memuat organisasi...
        </div>

        <!-- Belum ada organisasi -->
        <div v-else-if="organizations.length === 0" class="text-center text-muted py-5">
          <i class="ti ti-building-community fs-7 d-block mb-2"></i>
          <h6>Belum ada organisasi</h6>
          <p class="mb-0">Tambahkan organisasi terlebih dahulu sebelum membuat unit.</p>
        </div>

        <!-- Tabel unit -->
        <template v-else>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th style="width: 60px">No.</th>
                  <th>Nama Unit</th>
                  <th>Kode</th>
                  <th>Jenis Unit</th>
                  <th>Unit Induk</th>
                  <th>Status</th>
                  <th class="text-center" style="width: 130px">Aksi</th>
                </tr>
              </thead>

              <tbody>
                <tr v-if="loadingUnits">
                  <td colspan="7" class="text-center py-4">
                    <span class="spinner-border spinner-border-sm me-2"></span>
                    Memuat unit organisasi...
                  </td>
                </tr>

                <tr v-else-if="filteredUnits.length === 0">
                  <td colspan="7" class="text-center text-muted py-4">
                    {{ search ? "Unit organisasi tidak ditemukan." : "Belum ada unit organisasi." }}
                  </td>
                </tr>

                <tr v-for="(unit, index) in filteredUnits" v-else :key="unit.id">
                  <td>{{ index + 1 }}</td>

                  <td>
                    <div
                      class="d-flex align-items-center"
                      :style="{
                        paddingLeft: `${getUnitDepth(unit) * 22}px`,
                      }"
                    >
                      <i class="ti ti-building-community text-primary me-2"></i>

                      <div class="fw-semibold">
                        {{ unit.name }}
                      </div>
                    </div>
                  </td>

                  <td>
                    <span class="fw-semibold">{{ unit.code }}</span>
                  </td>

                  <td>{{ formatUnitType(unit.unit_type) }}</td>

                  <td>
                    <template v-if="getParentUnit(unit)">
                      {{ getParentUnit(unit)?.name }}
                    </template>
                    <span v-else class="text-muted">-</span>
                  </td>

                  <td>
                    <span class="badge" :class="unit.status === 'active' ? 'bg-success' : 'bg-secondary'">
                      {{ unit.status === "active" ? "Aktif" : "Tidak Aktif" }}
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

                      <button v-if="unit.status === 'active'" type="button" class="btn btn-sm btn-light text-danger" title="Nonaktifkan" @click="openDeactivateConfirmation(unit)">
                        <i class="ti ti-ban"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted"> Menampilkan {{ filteredUnits.length }} dari {{ units.length }} unit </small>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>
