<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import api from "@/api/client";
import { notify } from "@/utils/notification";

// ======================================================
// API
// ======================================================

const API = "/backend/setting-api";

// ======================================================
// TYPES
// ======================================================

type SettingType = "string" | "integer" | "float" | "boolean" | "json";

interface SettingItem {
  property: string;
  type: SettingType;
  value: unknown;
}

interface SettingGroup {
  id: string;
  name: string;
  class: string;
  context: string | null;
  items: SettingItem[];
}

interface SettingRow {
  property: string;
  type: SettingType;
  value: string;
  isNew: boolean;
}

interface NewClassForm {
  class: string;
  property: string;
  type: SettingType;
  value: string;
  context: string;
}

interface SettingsIndexResponse {
  groups?: SettingGroup[];
}

// ======================================================
// STATE
// ======================================================

const groups = ref<SettingGroup[]>([]);
const selectedGroupId = ref("");

const rows = ref<SettingRow[]>([]);
const forget = ref<string[]>([]);

const loading = ref(false);
const saving = ref(false);
const addingClass = ref(false);

const showAddModal = ref(false);

const newClass = ref<NewClassForm>({
  class: "",
  property: "",
  type: "string",
  value: "",
  context: "",
});

// ======================================================
// COMPUTED
// ======================================================

const selectedGroup = computed(() => groups.value.find((group) => group.id === selectedGroupId.value) ?? null);

// ======================================================
// HELPERS
// ======================================================

function getErrorMessage(error: unknown, fallback = "Terjadi kesalahan."): string {
  if (error instanceof Error) {
    return error.message;
  }

  return fallback;
}

function displayValue(value: unknown, type: SettingType): string {
  if (type === "boolean") {
    return String(value === true || value === "true" || value === 1 || value === "1");
  }

  if (type === "json") {
    return typeof value === "string" ? value : JSON.stringify(value, null, 2);
  }

  return String(value ?? "");
}

function normalizeValue(value: string, type: SettingType): unknown {
  switch (type) {
    case "boolean":
      return value === "true" || value === "1";

    case "integer":
      return Number.parseInt(value, 10);

    case "float":
      return Number(value);

    case "json":
      return JSON.parse(value);

    default:
      return value;
  }
}

function validateProperty(property: string): void {
  if (!/^[A-Za-z_][A-Za-z0-9_]*$/.test(property)) {
    throw new Error(`Nama properti "${property}" tidak valid.`);
  }
}

function validateValue(property: string, value: string | boolean, type: SettingType): void {
  if (type === "json") {
    try {
      JSON.parse(String(value));
    } catch {
      throw new Error(`Format JSON pada "${property}" tidak valid.`);
    }
  }

  if (type === "integer" && !/^-?\d+$/.test(String(value))) {
    throw new Error(`Nilai "${property}" harus berupa bilangan bulat.`);
  }

  if (type === "float" && (String(value).trim() === "" || !Number.isFinite(Number(value)))) {
    throw new Error(`Nilai "${property}" harus berupa angka.`);
  }
}

// ======================================================
// GROUP MANAGEMENT
// ======================================================

function prepareGroup(group: SettingGroup): void {
  rows.value = group.items.map((item) => ({
    property: item.property,
    type: item.type,
    value: displayValue(item.value, item.type),
    isNew: false,
  }));

  forget.value = [];
}

function selectGroup(id: string): void {
  selectedGroupId.value = id;

  const group = groups.value.find((item) => item.id === id);

  if (!group) return;

  prepareGroup(group);

  localStorage.setItem("erp-settings-active-tab", id);
}

// ======================================================
// LOAD SETTINGS
// ======================================================

async function loadSettings() {
  loading.value = true;

  try {
    const response = await api.get<{
      status: number;
      error: boolean;
      message: string;
      data: SettingsIndexResponse;
    }>(`${API}`);

    groups.value = response.data.data.groups ?? [];

    if (groups.value.length > 0) {
      const remembered = localStorage.getItem("erp-settings-active-tab");

      const target = groups.value.find((group) => group.id === remembered) ?? groups.value[0];

      selectGroup(target.id);
    } else {
      selectedGroupId.value = "";
      rows.value = [];
      forget.value = [];
    }
  } catch (error: unknown) {
    notify.error(getErrorMessage(error, "Gagal memuat pengaturan."));
  } finally {
    loading.value = false;
  }
}

// ======================================================
// ROW MANAGEMENT
// ======================================================

function addRow(): void {
  rows.value.push({
    property: "",
    type: "string",
    value: "",
    isNew: true,
  });
}

function removeRow(index: number): void {
  const row = rows.value[index];

  if (!row) return;

  if (!row.isNew && row.property && !forget.value.includes(row.property)) {
    forget.value.push(row.property);
  }

  rows.value.splice(index, 1);
}

function restoreRow(property: string): void {
  forget.value = forget.value.filter((item) => item !== property);

  const original = selectedGroup.value?.items.find((item) => item.property === property);

  if (!original) return;

  rows.value.push({
    property: original.property,
    type: original.type,
    value: displayValue(original.value, original.type),
    isNew: false,
  });
}

// ======================================================
// VALIDATION
// ======================================================

function validateRows(): void {
  const names = new Set<string>();

  for (const row of rows.value) {
    const property = row.property.trim();

    // Lewati baris yang benar-benar kosong.
    if (!property && !String(row.value).trim()) {
      continue;
    }

    validateProperty(property);

    const normalized = property.toLowerCase();

    if (names.has(normalized)) {
      throw new Error(`Properti "${property}" ditulis lebih dari sekali.`);
    }

    names.add(normalized);

    validateValue(property, row.value, row.type);
  }

  for (const property of forget.value) {
    if (names.has(property.toLowerCase())) {
      throw new Error(`Properti "${property}" tidak dapat disimpan dan dihapus sekaligus.`);
    }
  }
}

// ======================================================
// NORMALIZE PAYLOAD
// ======================================================

function normalizeRows() {
  return rows.value
    .filter((row) => row.property.trim() !== "")
    .map((row) => ({
      property: row.property.trim(),
      type: row.type,
      value: normalizeValue(row.value, row.type),
    }));
}

// ======================================================
// SAVE SETTINGS
// ======================================================

async function saveSettings(): Promise<void> {
  const group = selectedGroup.value;

  if (!group || saving.value) return;

  try {
    validateRows();

    saving.value = true;

    await api.put(`${API}/bulk-save`, {
      group: group.class,
      context: group.context,
      rows: normalizeRows(),
      forget: forget.value,
    });

    notify.success("Pengaturan berhasil disimpan.");

    await loadSettings();
  } catch (error: unknown) {
    notify.error(getErrorMessage(error, "Gagal menyimpan pengaturan."));
  } finally {
    saving.value = false;
  }
}

// ======================================================
// ADD CLASS
// ======================================================

function openAddModal(): void {
  newClass.value = {
    class: "",
    property: "",
    type: "string",
    value: "",
    context: "",
  };

  showAddModal.value = true;
}

async function submitAddClass(): Promise<void> {
  if (addingClass.value) return;

  try {
    const form = newClass.value;

    const className = form.class.trim();
    const property = form.property.trim();

    if (!className) {
      throw new Error("Nama class wajib diisi.");
    }

    if (!property) {
      throw new Error("Nama properti wajib diisi.");
    }

    validateProperty(property);

    validateValue(property, form.value, form.type);

    addingClass.value = true;

    await api.post(`${API}/add-class`, {
      class: className,
      property,
      type: form.type,
      value: normalizeValue(form.value, form.type),
      context: form.context.trim() || null,
    });

    showAddModal.value = false;

    notify.success("Class berhasil ditambahkan.");

    await loadSettings();
  } catch (error: unknown) {
    notify.error(getErrorMessage(error, "Gagal menambahkan class."));
  } finally {
    addingClass.value = false;
  }
}

// ======================================================
// DELETE OVERRIDE
// ======================================================

async function deleteOverride(property: string): Promise<void> {
  const group = selectedGroup.value;

  if (!group) return;

  const confirmed = window.confirm(`Hapus override "${property}"? Nilai default konfigurasi akan digunakan kembali.`);

  if (!confirmed) return;

  try {
    await api.delete(`${API}/delete`, {
      data: {
        group: group.class,
        property,
        context: group.context,
      },
    });

    notify.success("Override berhasil dihapus.");

    await loadSettings();
  } catch (error: unknown) {
    notify.error(getErrorMessage(error, "Gagal menghapus override."));
  }
}

// ======================================================
// LIFECYCLE
// ======================================================

onMounted(() => {
  loadSettings();
});
</script>
<template>
  <div class="settings-page">
    <!-- Page header -->
    <div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
      <div>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-1 small">
            <li class="breadcrumb-item text-muted">Sistem</li>
            <li class="breadcrumb-item active" aria-current="page">Pengaturan Aplikasi</li>
          </ol>
        </nav>
        <h4 class="fw-semibold mb-1">Pengaturan Aplikasi</h4>
        <p class="text-muted mb-0">Kelola konfigurasi aplikasi berdasarkan class dan context.</p>
      </div>

      <button type="button" class="btn btn-primary" @click="openAddModal">
        <i class="ti ti-plus me-1"></i>
        Tambah Class
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="card border-0 shadow-sm">
      <div class="card-body text-center py-5">
        <div class="spinner-border text-primary"></div>
        <p class="text-muted mt-2 mb-0">Memuat pengaturan...</p>
      </div>
    </div>

    <template v-else>
      <!-- Empty state -->
      <div v-if="groups.length === 0" class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
          <div class="empty-icon mx-auto mb-3">
            <i class="ti ti-settings fs-7"></i>
          </div>
          <h5 class="mb-1">Belum ada pengaturan</h5>
          <p class="text-muted mb-3">Tambahkan class untuk mulai mengelola pengaturan.</p>
          <button type="button" class="btn btn-primary btn-sm" @click="openAddModal">
            <i class="ti ti-plus me-1"></i>
            Tambah Class
          </button>
        </div>
      </div>

      <div v-else class="row g-3">
        <!-- Sidebar: daftar class -->
        <div class="col-12 col-lg-3">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
              <div class="d-flex align-items-center justify-content-between">
                <span class="section-label">Class Pengaturan</span>
                <span class="badge rounded-pill bg-light text-secondary border">{{ groups.length }}</span>
              </div>
            </div>

            <div class="list-group list-group-flush group-list">
              <button v-for="group in groups" :key="group.id" type="button" class="list-group-item list-group-item-action group-item" :class="{ active: selectedGroupId === group.id }" @click="selectGroup(group.id)">
                <div class="d-flex align-items-start justify-content-between gap-2">
                  <div class="text-truncate">
                    <div class="fw-semibold text-truncate">{{ group.name }}</div>
                    <small v-if="group.context" class="group-context d-block text-truncate"> <i class="ti ti-tag me-1"></i>{{ group.context }} </small>
                  </div>
                  <span class="badge rounded-pill count-badge">{{ group.items.length }}</span>
                </div>
              </button>
            </div>
          </div>
        </div>

        <!-- Detail: editor properti -->
        <div class="col-12 col-lg-9">
          <div class="card border-0 shadow-sm">
            <!-- Header -->
            <div v-if="selectedGroup" class="card-header bg-white border-bottom py-3">
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                  <h5 class="mb-1 fw-semibold">{{ selectedGroup.name }}</h5>
                  <div class="d-flex align-items-center flex-wrap gap-2">
                    <code class="class-code">{{ selectedGroup.class }}</code>
                    <span v-if="selectedGroup.context" class="badge bg-light text-secondary border fw-normal"> <i class="ti ti-tag me-1"></i>{{ selectedGroup.context }} </span>
                  </div>
                </div>

                <button type="button" class="btn btn-outline-primary btn-sm" @click="addRow">
                  <i class="ti ti-plus me-1"></i>
                  Tambah Properti
                </button>
              </div>
            </div>

            <div class="card-body p-0">
              <!-- Override yang akan dihapus -->
              <div v-if="forget.length" class="forget-box mx-3 mt-3">
                <div class="d-flex align-items-center mb-2">
                  <i class="ti ti-alert-triangle me-2"></i>
                  <strong class="small">Override yang akan dihapus saat disimpan</strong>
                </div>

                <div class="d-flex flex-wrap gap-2">
                  <span v-for="property in forget" :key="property" class="badge forget-chip">
                    {{ property }}
                    <button type="button" class="btn-close ms-1" style="font-size: 8px" title="Batalkan" @click="restoreRow(property)"></button>
                  </span>
                </div>
              </div>

              <!-- Tabel -->
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 settings-table">
                  <thead>
                    <tr>
                      <th style="width: 24%">Property</th>
                      <th style="width: 15%">Type</th>
                      <th>Value</th>
                      <th class="text-center" style="width: 70px">Aksi</th>
                    </tr>
                  </thead>

                  <tbody>
                    <tr v-for="(row, index) in rows" :key="index">
                      <td>
                        <input v-model="row.property" type="text" class="form-control form-control-sm font-monospace" :readonly="!row.isNew" :class="{ 'bg-light': !row.isNew }" placeholder="propertyName" />
                      </td>

                      <td>
                        <select v-model="row.type" class="form-select form-select-sm">
                          <option value="string">String</option>
                          <option value="integer">Integer</option>
                          <option value="float">Float</option>
                          <option value="boolean">Boolean</option>
                          <option value="json">JSON</option>
                        </select>
                      </td>

                      <td>
                        <div v-if="row.type === 'boolean'" class="form-check form-switch mb-0">
                          <input :checked="row.value === 'true'" @change="row.value = ($event.target as HTMLInputElement).checked ? 'true' : 'false'" class="form-check-input" type="checkbox" role="switch" />
                          <label class="form-check-label small">
                            {{ row.value === "true" ? "True" : "False" }}
                          </label>
                        </div>

                        <textarea v-else-if="row.type === 'json'" v-model="row.value" class="form-control form-control-sm font-monospace" rows="3" placeholder='{"key":"value"}'></textarea>

                        <input v-else v-model="row.value" type="text" class="form-control form-control-sm" :placeholder="row.type" />
                      </td>

                      <td class="text-center">
                        <button type="button" class="btn btn-icon btn-sm btn-light-danger" title="Hapus" @click="removeRow(index)">
                          <i class="ti ti-trash"></i>
                        </button>
                      </td>
                    </tr>

                    <tr v-if="rows.length === 0">
                      <td colspan="4" class="text-center text-muted py-5">
                        <i class="ti ti-inbox fs-6 d-block mb-1"></i>
                        Tidak ada properti pada kelompok ini.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Footer aksi -->
            <div class="card-footer bg-white border-top d-flex align-items-center justify-content-between py-3">
              <small class="text-muted">
                {{ rows.length }} properti
                <span v-if="forget.length"> · {{ forget.length }} akan dihapus</span>
              </small>

              <button type="button" class="btn btn-primary" :disabled="saving" @click="saveSettings">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="ti ti-device-floppy me-1"></i>
                {{ saving ? "Menyimpan..." : "Simpan Perubahan" }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Modal Tambah Class -->
    <div v-if="showAddModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(15, 23, 42, 0.55)" @click.self="showAddModal = false">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
          <div class="modal-header border-bottom">
            <div>
              <h5 class="modal-title fw-semibold mb-0">Tambah Class Pengaturan</h5>
              <small class="text-muted">Buat class baru beserta satu properti awal.</small>
            </div>
            <button type="button" class="btn-close" @click="showAddModal = false"></button>
          </div>

          <form @submit.prevent="submitAddClass">
            <div class="modal-body">
              <div class="section-label mb-3">Informasi Class</div>

              <div class="mb-3">
                <label class="form-label">Nama Class <span class="text-danger">*</span></label>
                <input v-model="newClass.class" type="text" class="form-control font-monospace" placeholder="Config\App" required />
                <div class="form-text">Contoh: Config\App atau Config\Email</div>
              </div>

              <div class="mb-4">
                <label class="form-label">Context</label>
                <input v-model="newClass.context" type="text" class="form-control font-monospace" placeholder="module:backend" />
              </div>

              <div class="section-label mb-3 pt-3 border-top">Properti Awal</div>

              <div class="row g-3">
                <div class="col-md-7">
                  <label class="form-label">Nama Properti <span class="text-danger">*</span></label>
                  <input v-model="newClass.property" type="text" class="form-control font-monospace" placeholder="siteName" required />
                </div>

                <div class="col-md-5">
                  <label class="form-label">Tipe Data</label>
                  <select v-model="newClass.type" class="form-select">
                    <option value="string">String</option>
                    <option value="integer">Integer</option>
                    <option value="float">Float</option>
                    <option value="boolean">Boolean</option>
                    <option value="json">JSON</option>
                  </select>
                </div>

                <div class="col-12">
                  <label class="form-label">Nilai Awal</label>

                  <div v-if="newClass.type === 'boolean'" class="form-check form-switch">
                    <input v-model="newClass.value" class="form-check-input" type="checkbox" role="switch" />
                    <label class="form-check-label">
                      {{ newClass.value ? "True" : "False" }}
                    </label>
                  </div>

                  <textarea v-else-if="newClass.type === 'json'" v-model="newClass.value" class="form-control font-monospace" rows="4" placeholder='{"key":"value"}'></textarea>

                  <input v-else v-model="newClass.value" type="text" class="form-control" />
                </div>
              </div>
            </div>

            <div class="modal-footer border-top">
              <button type="button" class="btn btn-light" @click="showAddModal = false">Batal</button>

              <button type="submit" class="btn btn-primary" :disabled="addingClass">
                <span v-if="addingClass" class="spinner-border spinner-border-sm me-1"></span>
                Simpan Class
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.settings-page {
  --st-border: #e5e7eb;
  --st-muted-bg: #f8fafc;
}

.section-label {
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #64748b;
}

.empty-icon {
  width: 64px;
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: var(--st-muted-bg);
  color: #94a3b8;
}

/* Sidebar class list */
.group-list {
  max-height: calc(100vh - 280px);
  overflow-y: auto;
}
.group-item {
  border-left: 3px solid transparent;
  padding: 0.75rem 1rem;
}
.group-item.active {
  background: rgba(var(--bs-primary-rgb), 0.07);
  color: inherit;
  border-left-color: var(--bs-primary);
  border-color: var(--st-border);
}
.group-context {
  color: #94a3b8;
  font-size: 0.72rem;
}
.count-badge {
  background: #eef2f7;
  color: #475569;
  font-weight: 600;
}
.group-item.active .count-badge {
  background: var(--bs-primary);
  color: #fff;
}

/* Header detail */
.class-code {
  background: var(--st-muted-bg);
  border: 1px solid var(--st-border);
  border-radius: 4px;
  padding: 1px 8px;
  font-size: 0.8rem;
  color: #334155;
}

/* Override warning */
.forget-box {
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 6px;
  padding: 0.75rem 1rem;
  color: #92400e;
}
.forget-chip {
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fcd34d;
  font-weight: 500;
}

/* Tabel */
.settings-table {
  margin-top: 0.75rem;
}
.settings-table thead th {
  background: var(--st-muted-bg);
  border-top: 1px solid var(--st-border);
  border-bottom: 1px solid var(--st-border);
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #64748b;
  padding: 0.6rem 1rem;
}
.settings-table tbody td {
  padding: 0.6rem 1rem;
  border-color: #f1f5f9;
  vertical-align: middle;
}
.btn-icon {
  width: 32px;
  height: 32px;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
</style>
