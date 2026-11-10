<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";

import { organizationService } from "../api/organizationService";

import type { Organization, OrganizationUnit, OrganizationUnitPayload } from "../types/organization";

interface FlatUnit {
  unit: OrganizationUnit;
  depth: number;
}

const organizations = ref<Organization[]>([]);
const units = ref<OrganizationUnit[]>([]);

const selectedOrganizationId = ref<number | null>(null);

const loading = ref(false);
const saving = ref(false);
const errorMessage = ref("");
const successMessage = ref("");

const showInactive = ref(false);
const isEditing = ref(false);
const editingId = ref<number | null>(null);
const showForm = ref(false);

const form = ref<OrganizationUnitPayload>({
  code: "",
  name: "",
  unit_type: "department",
  parent_id: null,
});

const activeUnits = computed(() => units.value.filter((unit) => unit.status === "active"));

const availableParents = computed(() => activeUnits.value.filter((unit) => unit.id !== editingId.value));

/**
 * Membentuk daftar hierarki menjadi baris tabel.
 * Unit anak ditampilkan dengan indentasi.
 */
const flattenedUnits = computed<FlatUnit[]>(() => {
  const result: FlatUnit[] = [];
  const visited = new Set<number>();

  const childrenOf = (parentId: number | null) => units.value.filter((unit) => unit.parent_id === parentId).sort((a, b) => a.name.localeCompare(b.name));

  const walk = (parentId: number | null, depth: number) => {
    for (const unit of childrenOf(parentId)) {
      if (visited.has(unit.id)) continue;

      visited.add(unit.id);
      result.push({ unit, depth });

      walk(unit.id, depth + 1);
    }
  };

  walk(null, 0);

  // Tetap tampilkan data yang parent-nya tidak ditemukan,
  // agar data bermasalah tidak tersembunyi dari administrator.
  for (const unit of units.value) {
    if (!visited.has(unit.id)) {
      visited.add(unit.id);
      result.push({ unit, depth: 0 });
    }
  }

  return result;
});

function getErrorMessage(error: any): string {
  return error?.response?.data?.message || error?.message || "Terjadi kesalahan saat memproses permintaan.";
}

function resetForm() {
  form.value = {
    code: "",
    name: "",
    unit_type: "department",
    parent_id: null,
  };

  editingId.value = null;
  isEditing.value = false;
  showForm.value = false;
}

async function loadOrganizations() {
  try {
    errorMessage.value = "";

    const result = await organizationService.getAll();

    organizations.value = result.items;

    if (organizations.value.length > 0) {
      const defaultOrganization = organizations.value.find((item) => Number(item.is_default) === 1) ?? organizations.value[0];

      selectedOrganizationId.value = defaultOrganization.id;
    }
  } catch (error) {
    errorMessage.value = getErrorMessage(error);
  }
}

async function loadUnits() {
  if (!selectedOrganizationId.value) {
    units.value = [];
    return;
  }

  loading.value = true;
  errorMessage.value = "";

  try {
    units.value = await organizationService.getUnits(selectedOrganizationId.value, showInactive.value);
  } catch (error) {
    errorMessage.value = getErrorMessage(error);
  } finally {
    loading.value = false;
  }
}

function openCreateForm() {
  resetForm();
  showForm.value = true;
}

function openEditForm(unit: OrganizationUnit) {
  editingId.value = unit.id;
  isEditing.value = true;
  showForm.value = true;

  form.value = {
    code: unit.code,
    name: unit.name,
    unit_type: unit.unit_type,
    parent_id: unit.parent_id,
  };
}

async function saveUnit() {
  if (!selectedOrganizationId.value) return;

  saving.value = true;
  errorMessage.value = "";
  successMessage.value = "";

  try {
    const payload: OrganizationUnitPayload = {
      code: form.value.code.trim(),
      name: form.value.name.trim(),
      unit_type: form.value.unit_type.trim(),
      parent_id: form.value.parent_id,
    };

    if (isEditing.value && editingId.value) {
      await organizationService.updateUnit(selectedOrganizationId.value, editingId.value, payload);

      successMessage.value = "Unit organisasi berhasil diperbarui.";
    } else {
      await organizationService.createUnit(selectedOrganizationId.value, payload);

      successMessage.value = "Unit organisasi berhasil dibuat.";
    }

    resetForm();
    await loadUnits();
  } catch (error) {
    errorMessage.value = getErrorMessage(error);
  } finally {
    saving.value = false;
  }
}

async function deactivateUnit(unit: OrganizationUnit) {
  if (!selectedOrganizationId.value) return;

  if (!window.confirm(`Nonaktifkan unit "${unit.name}"?`)) {
    return;
  }

  errorMessage.value = "";
  successMessage.value = "";

  try {
    await organizationService.deactivateUnit(selectedOrganizationId.value, unit.id);

    successMessage.value = "Unit organisasi berhasil dinonaktifkan.";

    await loadUnits();
  } catch (error) {
    errorMessage.value = getErrorMessage(error);
  }
}

watch(selectedOrganizationId, async () => {
  resetForm();
  await loadUnits();
});

watch(showInactive, loadUnits);

onMounted(loadOrganizations);
</script>

<template>
  <div class="container-fluid">
    <div class="row">
      <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h4 class="mb-1">Organization Units</h4>
            <p class="text-muted mb-0">Pengelolaan struktur unit organisasi.</p>
          </div>

          <button type="button" class="btn btn-primary" :disabled="!selectedOrganizationId" @click="openCreateForm">
            <i class="ti ti-plus me-1"></i>
            Tambah Unit
          </button>
        </div>

        <div v-if="errorMessage" class="alert alert-danger alert-dismissible" role="alert">
          {{ errorMessage }}

          <button type="button" class="btn-close" @click="errorMessage = ''"></button>
        </div>

        <div v-if="successMessage" class="alert alert-success alert-dismissible" role="alert">
          {{ successMessage }}

          <button type="button" class="btn-close" @click="successMessage = ''"></button>
        </div>

        <div class="card">
          <div class="card-body">
            <div class="row align-items-end mb-4">
              <div class="col-md-6">
                <label class="form-label"> Organisasi </label>

                <select v-model.number="selectedOrganizationId" class="form-select">
                  <option :value="null">Pilih organisasi</option>

                  <option v-for="organization in organizations" :key="organization.id" :value="organization.id">
                    {{ organization.code }} -
                    {{ organization.name }}
                  </option>
                </select>
              </div>

              <div class="col-md-6">
                <div class="form-check mt-3">
                  <input id="showInactive" v-model="showInactive" class="form-check-input" type="checkbox" />

                  <label for="showInactive" class="form-check-label"> Tampilkan unit nonaktif </label>
                </div>
              </div>
            </div>

            <div v-if="showForm" class="border rounded p-3 mb-4">
              <h5 class="mb-3">
                {{ isEditing ? "Ubah Unit" : "Tambah Unit" }}
              </h5>

              <form @submit.prevent="saveUnit">
                <div class="row">
                  <div class="col-md-4 mb-3">
                    <label class="form-label"> Kode Unit </label>

                    <input v-model="form.code" type="text" class="form-control" maxlength="50" required :disabled="isEditing" />
                  </div>

                  <div class="col-md-4 mb-3">
                    <label class="form-label"> Nama Unit </label>

                    <input v-model="form.name" type="text" class="form-control" maxlength="150" required />
                  </div>

                  <div class="col-md-4 mb-3">
                    <label class="form-label"> Jenis Unit </label>

                    <input v-model="form.unit_type" type="text" class="form-control" maxlength="50" placeholder="department, branch, warehouse" required />
                  </div>

                  <div class="col-md-6 mb-3">
                    <label class="form-label"> Parent Unit </label>

                    <select v-model="form.parent_id" class="form-select">
                      <option :value="null">Tanpa parent (Unit utama)</option>

                      <option v-for="parent in availableParents" :key="parent.id" :value="parent.id">
                        {{ parent.code }} -
                        {{ parent.name }}
                      </option>
                    </select>
                  </div>
                </div>

                <div class="d-flex gap-2">
                  <button type="submit" class="btn btn-primary" :disabled="saving">
                    {{ saving ? "Menyimpan..." : "Simpan" }}
                  </button>

                  <button type="button" class="btn btn-light" :disabled="saving" @click="resetForm">Batal</button>
                </div>
              </form>
            </div>

            <div v-if="loading" class="text-center py-5">
              <div class="spinner-border text-primary" role="status"></div>

              <p class="text-muted mt-2 mb-0">Memuat unit organisasi...</p>
            </div>

            <div v-else-if="!selectedOrganizationId" class="text-center text-muted py-5">Pilih organisasi terlebih dahulu.</div>

            <div v-else-if="flattenedUnits.length === 0" class="text-center text-muted py-5">Belum ada unit organisasi.</div>

            <div v-else class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th>Kode</th>
                    <th>Nama Unit</th>
                    <th>Jenis</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                  </tr>
                </thead>

                <tbody>
                  <tr v-for="item in flattenedUnits" :key="item.unit.id">
                    <td>
                      {{ item.unit.code }}
                    </td>

                    <td>
                      <span
                        :style="{
                          paddingLeft: `${item.depth * 24}px`,
                        }"
                      >
                        <i v-if="item.depth > 0" class="ti ti-corner-down-right me-1 text-muted"></i>

                        {{ item.unit.name }}
                      </span>
                    </td>

                    <td>
                      {{ item.unit.unit_type }}
                    </td>

                    <td>
                      <span class="badge" :class="item.unit.status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'">
                        {{ item.unit.status }}
                      </span>
                    </td>

                    <td class="text-end">
                      <button type="button" class="btn btn-sm btn-light me-1" :disabled="item.unit.status !== 'active'" @click="openEditForm(item.unit)">
                        <i class="ti ti-edit"></i>
                      </button>

                      <button type="button" class="btn btn-sm btn-light text-danger" :disabled="item.unit.status !== 'active'" @click="deactivateUnit(item.unit)">
                        <i class="ti ti-ban"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
