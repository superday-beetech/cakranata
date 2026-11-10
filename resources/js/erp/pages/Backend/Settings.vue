<script setup>
import { computed, onMounted, ref } from "vue";
import { api } from "../../services/api";
import { notify } from "../../services/notification";

const API = "/backend/settings";

const groups = ref([]);
const selectedGroupId = ref("");
const loading = ref(false);
const saving = ref(false);
const addingClass = ref(false);

const showAddModal = ref(false);

const newClass = ref({
  class: "",
  property: "",
  type: "string",
  value: "",
  context: "",
});

const selectedGroup = computed(() => groups.value.find((group) => group.id === selectedGroupId.value) ?? null);

const rows = ref([]);
const forget = ref([]);

function clone(value) {
  return JSON.parse(JSON.stringify(value));
}

function displayValue(value, type) {
  if (type === "boolean") {
    return Boolean(value);
  }

  if (type === "json") {
    return JSON.stringify(value ?? null, null, 2);
  }

  return value === null || value === undefined ? "" : String(value);
}

function prepareGroup(group) {
  rows.value = group.items.map((item) => ({
    property: item.property,
    type: item.type,
    value: displayValue(item.value, item.type),
    isNew: false,
  }));

  forget.value = [];
}

function selectGroup(id) {
  selectedGroupId.value = id;

  const group = groups.value.find((item) => item.id === id);

  if (group) {
    prepareGroup(group);
    localStorage.setItem("erp-settings-active-tab", id);
  }
}

async function loadSettings() {
  loading.value = true;

  try {
    const response = await api.get(`${API}/get-index`);

    groups.value = response.groups ?? [];

    if (groups.value.length > 0) {
      const remembered = localStorage.getItem("erp-settings-active-tab");

      const target = groups.value.find((group) => group.id === remembered) ?? groups.value[0];

      selectGroup(target.id);
    } else {
      selectedGroupId.value = "";
      rows.value = [];
    }
    notify.success("Pengaturan berhasil dimuat.");
  } catch (error) {
    notify.error(error.message);
  } finally {
    loading.value = false;
  }
}

function addRow() {
  rows.value.push({
    property: "",
    type: "string",
    value: "",
    isNew: true,
  });
}

function removeRow(index) {
  const row = rows.value[index];

  if (!row.isNew && row.property) {
    if (!forget.value.includes(row.property)) {
      forget.value.push(row.property);
    }
  }

  rows.value.splice(index, 1);
}

function restoreRow(property) {
  forget.value = forget.value.filter((item) => item !== property);

  const group = selectedGroup.value;
  const original = group?.items.find((item) => item.property === property);

  if (!original) return;

  rows.value.push({
    property: original.property,
    type: original.type,
    value: displayValue(original.value, original.type),
    isNew: false,
  });
}

function normalizeRows() {
  return rows.value.map((row) => {
    let value = row.value;

    if (row.type === "boolean") {
      value = Boolean(value);
    }

    return {
      property: row.property.trim(),
      type: row.type,
      value,
    };
  });
}

function validateRows() {
  const names = new Set();

  for (const row of rows.value) {
    const property = row.property.trim();

    if (!property && !String(row.value ?? "").trim()) {
      continue;
    }

    if (!/^[A-Za-z_][A-Za-z0-9_]*$/.test(property)) {
      throw new Error(`Nama properti "${property}" tidak valid.`);
    }

    const normalized = property.toLowerCase();

    if (names.has(normalized)) {
      throw new Error(`Properti "${property}" ditulis lebih dari sekali.`);
    }

    names.add(normalized);

    if (row.type === "json") {
      try {
        JSON.parse(row.value);
      } catch {
        throw new Error(`Format JSON pada "${property}" tidak valid.`);
      }
    }

    if (row.type === "integer" && !/^-?\d+$/.test(String(row.value))) {
      throw new Error(`Nilai "${property}" harus berupa bilangan bulat.`);
    }

    if (row.type === "float" && !Number.isFinite(Number(row.value))) {
      throw new Error(`Nilai "${property}" harus berupa angka.`);
    }
  }

  for (const property of forget.value) {
    if (names.has(property.toLowerCase())) {
      throw new Error(`Properti "${property}" tidak dapat disimpan dan dihapus sekaligus.`);
    }
  }
}

async function saveSettings() {
  if (!selectedGroup.value) return;

  try {
    validateRows();

    saving.value = true;

    await api.put(`${API}/bulk-save`, {
      group: selectedGroup.value.class,
      context: selectedGroup.value.context,
      rows: normalizeRows(),
      forget: forget.value,
    });

    notify.success("Pengaturan berhasil disimpan.");

    await loadSettings();
  } catch (error) {
    notify.error(error.message);
  } finally {
    saving.value = false;
  }
}

function openAddModal() {
  newClass.value = {
    class: "",
    property: "",
    type: "string",
    value: "",
    context: "",
  };

  showAddModal.value = true;
}

async function submitAddClass() {
  try {
    const form = newClass.value;

    if (!form.class.trim()) {
      throw new Error("Nama class wajib diisi.");
    }

    if (!/^[A-Za-z_][A-Za-z0-9_]*$/.test(form.property.trim())) {
      throw new Error("Nama properti tidak valid.");
    }

    if (form.type === "json") {
      JSON.parse(form.value);
    }

    if (form.type === "integer" && !/^-?\d+$/.test(String(form.value))) {
      throw new Error("Nilai harus berupa bilangan bulat.");
    }

    if (form.type === "float" && !Number.isFinite(Number(form.value))) {
      throw new Error("Nilai harus berupa angka.");
    }

    addingClass.value = true;

    await api.post(`${API}/add-class`, {
      class: form.class.trim(),
      property: form.property.trim(),
      type: form.type,
      value: form.value,
      context: form.context.trim() || null,
    });

    showAddModal.value = false;
    notify.success("Class berhasil ditambahkan.");

    await loadSettings();
  } catch (error) {
    notify.success(error.message);
  } finally {
    addingClass.value = false;
  }
}

async function deleteOverride(property) {
  if (!selectedGroup.value) return;

  if (!window.confirm(`Hapus override "${property}"? Nilai default konfigurasi akan digunakan kembali.`)) {
    return;
  }

  try {
    await api.delete(`${API}/delete`, {
      group: selectedGroup.value.class,
      property,
      context: selectedGroup.value.context,
    });

    notify.success("Override berhasil dihapus.");

    await loadSettings();
  } catch (error) {
    notify.error(error.message);
  }
}

onMounted(loadSettings);
</script>

<template>
  <div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="mb-1">Pengaturan Aplikasi</h4>
        <p class="text-muted mb-0">Kelola konfigurasi aplikasi berdasarkan class dan context.</p>
      </div>

      <button type="button" class="btn btn-primary" @click="openAddModal">
        <i class="ti ti-plus me-1"></i>
        Tambah Class
      </button>
    </div>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary"></div>
      <p class="text-muted mt-2">Memuat pengaturan...</p>
    </div>

    <template v-else>
      <div v-if="groups.length === 0" class="card">
        <div class="card-body text-center py-5">
          <i class="ti ti-settings fs-8 text-muted"></i>
          <h5 class="mt-3">Belum ada pengaturan</h5>
          <p class="text-muted">Tambahkan class untuk mulai mengelola pengaturan.</p>
        </div>
      </div>

      <template v-else>
        <div class="card">
          <div class="card-body pb-0">
            <ul class="nav nav-tabs" role="tablist">
              <li v-for="group in groups" :key="group.id" class="nav-item">
                <button type="button" class="nav-link" :class="{ active: selectedGroupId === group.id }" @click="selectGroup(group.id)">
                  {{ group.name }}

                  <span class="badge bg-light text-dark ms-1">
                    {{ group.items.length }}
                  </span>

                  <small v-if="group.context" class="d-block text-muted">
                    {{ group.context }}
                  </small>
                </button>
              </li>
            </ul>
          </div>

          <div class="card-body">
            <div v-if="selectedGroup" class="d-flex align-items-center justify-content-between mb-3">
              <div>
                <h5 class="mb-1">{{ selectedGroup.name }}</h5>
                <small class="text-muted">
                  {{ selectedGroup.class }}
                  <span v-if="selectedGroup.context"> · {{ selectedGroup.context }} </span>
                </small>
              </div>

              <button type="button" class="btn btn-outline-primary btn-sm" @click="addRow">
                <i class="ti ti-plus me-1"></i>
                Tambah Properti
              </button>
            </div>

            <div v-if="forget.length" class="alert alert-warning">
              <strong>Override yang akan dihapus:</strong>

              <div class="d-flex flex-wrap gap-2 mt-2">
                <span v-for="property in forget" :key="property" class="badge bg-warning text-dark">
                  {{ property }}

                  <button type="button" class="btn-close ms-1" style="font-size: 8px" @click="restoreRow(property)"></button>
                </span>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered align-middle table-sm">
                <thead class="table-light">
                  <tr>
                    <th style="width: 22%">Property</th>
                    <th style="width: 15%">Type</th>
                    <th>Value</th>
                    <th style="width: 75px">Aksi</th>
                  </tr>
                </thead>

                <tbody>
                  <tr v-for="(row, index) in rows" :key="index">
                    <td>
                      <input v-model="row.property" type="text" class="form-control" :readonly="!row.isNew" placeholder="propertyName" />
                    </td>

                    <td>
                      <select v-model="row.type" class="form-select">
                        <option value="string">String</option>
                        <option value="integer">Integer</option>
                        <option value="float">Float</option>
                        <option value="boolean">Boolean</option>
                        <option value="json">JSON</option>
                      </select>
                    </td>

                    <td>
                      <div v-if="row.type === 'boolean'" class="form-check form-switch">
                        <input v-model="row.value" class="form-check-input" type="checkbox" role="switch" />

                        <label class="form-check-label">
                          {{ row.value ? "True" : "False" }}
                        </label>
                      </div>

                      <textarea v-else-if="row.type === 'json'" v-model="row.value" class="form-control font-monospace" rows="3" placeholder='{"key":"value"}'></textarea>

                      <input v-else v-model="row.value" type="text" class="form-control" :placeholder="row.type" />
                    </td>

                    <td class="text-center">
                      <button type="button" class="btn btn-light-danger btn-sm" title="Hapus" @click="removeRow(index)">
                        <i class="ti ti-trash"></i>
                      </button>
                    </td>
                  </tr>

                  <tr v-if="rows.length === 0">
                    <td colspan="4" class="text-center text-muted py-4">Tidak ada properti pada kelompok ini.</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="d-flex justify-content-end mt-3">
              <button type="button" class="btn btn-primary" :disabled="saving" @click="saveSettings">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>

                <i v-else class="ti ti-device-floppy me-1"></i>

                {{ saving ? "Menyimpan..." : "Simpan Perubahan" }}
              </button>
            </div>
          </div>
        </div>
      </template>
    </template>

    <!-- Modal Tambah Class -->
    <div v-if="showAddModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0, 0, 0, 0.5)" @click.self="showAddModal = false">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Tambah Class Pengaturan</h5>

            <button type="button" class="btn-close" @click="showAddModal = false"></button>
          </div>

          <form @submit.prevent="submitAddClass">
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Nama Class</label>
                <input v-model="newClass.class" type="text" class="form-control" placeholder="Config\App" required />
                <small class="text-muted"> Contoh: Config\App atau Config\Email </small>
              </div>

              <div class="mb-3">
                <label class="form-label">Context</label>
                <input v-model="newClass.context" type="text" class="form-control" placeholder="module:backend" />
              </div>

              <hr />

              <div class="mb-3">
                <label class="form-label">Properti Awal</label>
                <input v-model="newClass.property" type="text" class="form-control" placeholder="siteName" required />
              </div>

              <div class="mb-3">
                <label class="form-label">Tipe Data</label>
                <select v-model="newClass.type" class="form-select">
                  <option value="string">String</option>
                  <option value="integer">Integer</option>
                  <option value="float">Float</option>
                  <option value="boolean">Boolean</option>
                  <option value="json">JSON</option>
                </select>
              </div>

              <div class="mb-3">
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

            <div class="modal-footer">
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
