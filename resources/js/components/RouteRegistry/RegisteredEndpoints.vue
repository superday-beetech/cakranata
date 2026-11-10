<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from "vue";
import { useRegistryApi } from "./composables/useRegistryApi.js";

const props = defineProps({
  apiBase: {
    type: String,
    required: true,
  },
});

const api = useRegistryApi(props.apiBase);

const STORAGE_KEY = "rr_registered_endpoint_search";

const endpoints = {
  registered: "/registered",
  saveVerbs: "/save-verbs",
  updateStatus: "/update-status",
  updateIdentity: "/update-endpoint-identity",
};

const availableVerbs = ["GET", "POST", "PUT", "PATCH", "DELETE"];

const pageSizes = [10, 25, 50, 100];

// ==================================================
// STATE
// ==================================================

const rows = ref([]);
const loading = ref(false);
const errorMessage = ref("");

const search = ref(localStorage.getItem(STORAGE_KEY) ?? "");
const searchInput = ref("");
const page = ref(1);
const pageSize = ref(10);

const pagination = ref({
  page: 1,
  perPage: 10,
  total: 0,
  totalPages: 1,
  hasPrev: false,
  hasNext: false,
});

const savingIds = ref([]);
const editingIdentity = ref(false);
const savingIdentity = ref(false);
const identityError = ref("");
const savingVerbIds = ref([]);
const editForm = ref({
  id: null,
  module: "",
  controller: "",
  method: "",
});

// ==================================================
// REQUEST CONTROL
// ==================================================

let requestSequence = 0;
let searchTimer = null;

// ==================================================
// COMPUTED
// ==================================================

const identityPreview = computed(() => {
  const controller = editForm.value.controller.trim();
  const method = editForm.value.method.trim();

  return controller && method ? `${controller}::${method}` : "—";
});

const totalPages = computed(() => Math.max(1, Number(pagination.value.totalPages) || 1));

const firstRow = computed(() => {
  if (!pagination.value.total) return 0;

  return (pagination.value.page - 1) * pagination.value.perPage + 1;
});

const lastRow = computed(() => {
  if (!pagination.value.total) return 0;

  return Math.min(pagination.value.page * pagination.value.perPage, pagination.value.total);
});

// Statistik ini dihitung dari halaman yang sedang dimuat,
// karena API belum mengirim agregasi global.
const totalRoutable = computed(() => rows.value.filter((item) => isEnabled(registryOf(item).is_routable)).length);

const totalAllowed = computed(() => rows.value.filter((item) => isEnabled(registryOf(item).is_allowed)).length);

const paginationItems = computed(() => {
  const total = totalPages.value;
  const current = pagination.value.page;

  if (total <= 7) {
    return Array.from({ length: total }, (_, index) => ({
      type: "page",
      value: index + 1,
    }));
  }

  const pageSet = new Set([1, total, current - 1, current, current + 1]);

  const pages = [...pageSet].filter((number) => number >= 1 && number <= total).sort((a, b) => a - b);

  const items = [];
  let previous = 0;

  for (const number of pages) {
    if (number - previous > 1) {
      items.push({
        type: "ellipsis",
        key: `ellipsis-${previous}-${number}`,
      });
    }

    items.push({
      type: "page",
      value: number,
    });

    previous = number;
  }

  return items;
});

// ==================================================
// DATA HELPERS
// ==================================================

function registryOf(item) {
  return item?.registry ?? item ?? {};
}

function endpointId(item) {
  const registry = registryOf(item);

  return registry.id ?? item?.id ?? item?.registry_id ?? item?.route_registry_id ?? "";
}

function endpointName(item) {
  const registry = registryOf(item);

  return item?.endpoint ?? item?.route ?? item?.uri ?? item?.path ?? registry.endpoint ?? "—";
}

function moduleName(item) {
  const registry = registryOf(item);

  return item?.module_name ?? item?.module ?? item?.registry_module ?? registry.module ?? "—";
}

function controllerMethod(item) {
  const registry = registryOf(item);

  const controller = item?.controller ?? item?.controller_name ?? registry.controller ?? "";

  const method = item?.method ?? item?.action ?? registry.method ?? "";

  if (controller && method) {
    return `${controller}::${camelToKebab(method)}`;
  }

  return item?.handler ?? item?.class_name ?? controller ?? method ?? "—";
}

function kebabToCamel(value) {
  return String(value ?? "").replace(/-([a-z0-9])/g, (_, char) => char.toUpperCase());
}
function camelToKebab(value) {
  return String(value ?? "")
    .replace(/([a-z0-9])([A-Z])/g, "$1-$2")
    .toLowerCase();
}
function isEnabled(value) {
  return value === true || value === 1 || value === "1" || value === "true";
}

function verbsOf(item) {
  const verbs = registryOf(item).verbs;

  if (!Array.isArray(verbs)) return [];

  return verbs.map((verb) => String(typeof verb === "object" ? (verb.verb ?? verb.method ?? verb.http_verb ?? "") : verb).toUpperCase()).filter(Boolean);
}

function normalizeItem(item) {
  return {
    ...item,
    _selectedVerbs: [...verbsOf(item)],
  };
}

// ==================================================
// SERVER-SIDE PAGINATION
// ==================================================

async function loadRegistered() {
  const sequence = ++requestSequence;

  loading.value = true;
  errorMessage.value = "";

  try {
    const params = new URLSearchParams({
      page: String(page.value),
      perPage: String(pageSize.value),
      search: search.value.trim(),
    });

    const result = await api.get(`${endpoints.registered}?${params.toString()}`);

    // Abaikan respons lama jika permintaan baru sudah berjalan.
    if (sequence !== requestSequence) return;

    const data = result?.data ?? {};
    const items = Array.isArray(data.items) ? data.items : [];

    const meta = data.pagination ?? {};

    rows.value = items.map(normalizeItem);

    pagination.value = {
      page: Math.max(1, Number(meta.page) || 1),
      perPage: Math.max(1, Number(meta.perPage) || pageSize.value),
      total: Math.max(0, Number(meta.total) || 0),
      totalPages: Math.max(1, Number(meta.totalPages) || 1),
      hasPrev: Boolean(meta.hasPrev),
      hasNext: Boolean(meta.hasNext),
    };

    // Server adalah acuan nomor halaman final.
    page.value = pagination.value.page;
    pageSize.value = pagination.value.perPage;
  } catch (error) {
    if (sequence !== requestSequence) return;

    rows.value = [];
    pagination.value = {
      page: 1,
      perPage: pageSize.value,
      total: 0,
      totalPages: 1,
      hasPrev: false,
      hasNext: false,
    };

    errorMessage.value = error?.message ?? "Gagal memuat route registry.";
  } finally {
    if (sequence === requestSequence) {
      loading.value = false;
    }
  }
}

function goToPage(number) {
  const target = Math.max(1, Math.min(Number(number) || 1, totalPages.value));

  if (target === pagination.value.page && rows.value.length) {
    return;
  }

  page.value = target;
  loadRegistered();
}

async function onSearch() {
  const keyword = searchInput.value.trim();

  if (keyword === search.value) {
    return;
  }

  search.value = keyword;
  localStorage.setItem(STORAGE_KEY, keyword);
  page.value = 1;

  await loadRegistered();
}

function onPageSizeChange() {
  const selectedSize = Number(pageSize.value);

  if (!pageSizes.includes(selectedSize)) {
    pageSize.value = 10;
  }

  page.value = 1;
  loadRegistered();
}

function refreshRegistered() {
  if (searchTimer !== null) {
    clearTimeout(searchTimer);
    searchTimer = null;
  }

  loadRegistered();
}

// ==================================================
// UPDATE ROUTE STATUS
// ==================================================

async function updateStatus(item, field, event) {
  const input = event.target;
  const id = endpointId(item);
  const value = input.checked;
  const previousValue = !value;

  if (!id) {
    input.checked = previousValue;
    errorMessage.value = "ID endpoint tidak ditemukan.";
    return;
  }

  const savingKey = `${id}:${field}`;

  if (savingIds.value.includes(savingKey)) {
    input.checked = previousValue;
    return;
  }

  savingIds.value.push(savingKey);
  errorMessage.value = "";

  try {
    await api.patch(endpoints.updateStatus, {
      id: Number(id),
      field,
      value,
    });

    const registry = registryOf(item);

    registry[field === "routable" ? "is_routable" : "is_allowed"] = value;
  } catch (error) {
    input.checked = previousValue;
    errorMessage.value = error?.message ?? "Gagal memperbarui status endpoint.";
  } finally {
    savingIds.value = savingIds.value.filter((key) => key !== savingKey);
  }
}

// ==================================================
// SAVE HTTP VERBS
// ==================================================

async function saveVerbs(item) {
  const id = endpointId(item);
  const endpoint = item.endpoint ?? item.url_route ?? registryOf(item).endpoint ?? registryOf(item).url_route;

  const savingKey = String(id);

  if (savingVerbIds.value.includes(savingKey)) return;

  if (typeof endpoint !== "string" || !endpoint.trim()) {
    errorMessage.value = "Nama endpoint tidak ditemukan.";
    return;
  }

  const verbs = availableVerbs.filter((verb) => item._selectedVerbs?.includes(verb));

  const previousVerbs = [...verbsOf(item)];

  savingVerbIds.value.push(savingKey);
  errorMessage.value = "";

  try {
    await api.post(endpoints.saveVerbs, {
      endpoint,
      verbs,
    });

    registryOf(item).verbs = [...verbs];
    item._selectedVerbs = [...verbs];
  } catch (error) {
    item._selectedVerbs = previousVerbs;

    errorMessage.value = `Gagal menyimpan HTTP verbs: ${error?.message ?? "Kesalahan tidak diketahui."}`;
  } finally {
    savingVerbIds.value = savingVerbIds.value.filter((key) => key !== savingKey);
  }
}

// ==================================================
// EDIT ENDPOINT IDENTITY
// ==================================================

function openEdit(item) {
  const registry = registryOf(item);

  editForm.value = {
    id: Number(endpointId(item)),
    module: String(registry.module ?? item.module ?? item.module_name ?? ""),
    controller: String(registry.controller ?? item.controller ?? item.controller_name ?? ""),
    method: String(registry.method ?? item.method ?? item.action ?? ""),
  };

  identityError.value = "";
  editingIdentity.value = true;
}

function closeEdit() {
  if (savingIdentity.value) return;

  editingIdentity.value = false;
  identityError.value = "";
}

async function saveIdentity() {
  const payload = {
    id: Number(editForm.value.id),
    module: editForm.value.module.trim(),
    controller: editForm.value.controller.trim(),
    method: editForm.value.method.trim(),
  };

  if (!Number.isInteger(payload.id) || payload.id < 1 || !payload.module || !payload.controller || !payload.method) {
    identityError.value = "Semua kolom wajib diisi dengan benar.";
    return;
  }

  savingIdentity.value = true;
  identityError.value = "";

  try {
    await api.post(endpoints.updateIdentity, payload);

    editingIdentity.value = false;

    await loadRegistered();
  } catch (error) {
    identityError.value = error?.message ?? "Gagal memperbarui identitas endpoint.";
  } finally {
    savingIdentity.value = false;
  }
}

// ==================================================
// LIFECYCLE
// ==================================================

onMounted(loadRegistered);

onBeforeUnmount(() => {
  if (searchTimer !== null) {
    clearTimeout(searchTimer);
  }
});
</script>

<template>
  <div>
    <!-- Statistik halaman aktif -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div class="d-flex flex-wrap gap-2">
        <span class="badge bg-light text-dark border"> Total registry: {{ pagination.total }} </span>

        <span class="badge bg-success-subtle text-success"> Routable halaman ini: {{ totalRoutable }} </span>

        <span class="badge bg-primary-subtle text-primary"> Allowed halaman ini: {{ totalAllowed }} </span>
      </div>

      <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="loading" @click="refreshRegistered">
        <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
        <i v-else class="ti ti-refresh me-1"></i>
        Refresh
      </button>
    </div>

    <div v-if="errorMessage" class="alert alert-danger" role="alert">
      {{ errorMessage }}
    </div>

    <div class="card">
      <!-- Header -->
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
          <h5 class="mb-1">Endpoint Registry</h5>
          <p class="text-muted mb-0">Data endpoint yang sudah tersimpan di Route Registry.</p>
        </div>

        <div class="d-flex gap-2 mb-3">
          <input v-model="searchInput" type="search" class="form-control" placeholder="Cari endpoint..." @keyup.enter="onSearch" />

          <button type="button" class="btn btn-primary" @click="onSearch">
            <i class="ti ti-search me-1"></i>
            Cari
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="table-responsive">
        <table class="table table-hover align-middle table-sm mb-0">
          <thead>
            <tr>
              <th class="text-center" style="width: 60px">No.</th>
              <th style="min-width: 230px">Endpoint</th>
              <th style="min-width: 120px">Module</th>
              <th style="min-width: 160px">HTTP Verbs</th>
              <th class="text-center">Routable</th>
              <th class="text-center">Allowed</th>
              <th>Action</th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="loading && rows.length === 0">
              <td colspan="8" class="text-center py-4">
                <span class="spinner-border spinner-border-sm me-2"></span>
                Memuat registered endpoints...
              </td>
            </tr>

            <tr v-else-if="rows.length === 0">
              <td colspan="8" class="text-center py-4 text-muted">
                {{ search.trim() ? "Tidak ada endpoint yang cocok." : "Belum ada registered endpoint." }}
              </td>
            </tr>

            <tr v-for="(item, index) in rows" :key="endpointId(item)">
              <td class="text-center">
                {{ firstRow + index }}
              </td>

              <td>
                <div class="fw-semibold">
                  {{ endpointName(item) }}
                </div>
                <small class="text-muted">
                  <code>{{ controllerMethod(item) }}</code>
                </small>
              </td>

              <td>{{ moduleName(item) }}</td>

              <td>
                <div class="d-flex flex-wrap gap-2">
                  <label v-for="verb in availableVerbs" :key="verb" class="form-check form-check-inline mb-1">
                    <input v-model="item._selectedVerbs" class="form-check-input" type="checkbox" :value="verb" :disabled="savingVerbIds.includes(String(endpointId(item)))" @change="saveVerbs(item)" />

                    <span class="form-check-label">{{ verb }}</span>
                  </label>
                </div>

                <small v-if="savingVerbIds.includes(String(endpointId(item)))" class="text-primary d-block mt-1">
                  <span class="spinner-border spinner-border-sm me-1"></span>
                  Menyimpan...
                </small>
              </td>

              <td class="text-center">
                <div class="form-check form-switch d-inline-flex">
                  <input
                    class="form-check-input"
                    type="checkbox"
                    :checked="isEnabled(registryOf(item).is_routable)"
                    :disabled="savingIds.includes(`${endpointId(item)}:routable`)"
                    aria-label="Ubah status routable"
                    @change="updateStatus(item, 'routable', $event)"
                  />
                </div>
              </td>

              <td class="text-center">
                <div class="form-check form-switch d-inline-flex">
                  <input
                    class="form-check-input"
                    type="checkbox"
                    :checked="isEnabled(registryOf(item).is_allowed)"
                    :disabled="savingIds.includes(`${endpointId(item)}:allowed`)"
                    aria-label="Ubah status allowed"
                    @change="updateStatus(item, 'allowed', $event)"
                  />
                </div>
              </td>

              <td>
                <button type="button" class="btn btn-sm btn-outline-primary" @click="openEdit(item)">
                  <i class="ti ti-edit me-1"></i>
                  Edit
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Server-side pagination -->
      <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-3">
        <small class="text-muted"> Menampilkan {{ firstRow }}–{{ lastRow }} dari {{ pagination.total }} endpoint </small>

        <div class="d-flex align-items-center gap-2">
          <label for="registeredPageSize" class="small text-muted text-nowrap"> Per halaman </label>

          <select id="registeredPageSize" v-model.number="pageSize" class="form-select form-select-sm" style="width: auto" :disabled="loading" @change="onPageSizeChange">
            <option v-for="size in pageSizes" :key="size" :value="size">
              {{ size }}
            </option>
          </select>
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-end gap-1" aria-label="Navigasi halaman">
          <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="loading || !pagination.hasPrev" @click="goToPage(page - 1)">Sebelumnya</button>

          <template v-for="item in paginationItems" :key="item.key ?? item.value">
            <span v-if="item.type === 'ellipsis'" class="px-1 text-muted" aria-hidden="true"> … </span>

            <button
              v-else
              type="button"
              class="btn btn-sm"
              :class="item.value === pagination.page ? 'btn-primary' : 'btn-outline-secondary'"
              :aria-current="item.value === pagination.page ? 'page' : undefined"
              :aria-label="`Halaman ${item.value}`"
              :disabled="loading || item.value === pagination.page"
              @click="goToPage(item.value)"
            >
              {{ item.value }}
            </button>
          </template>

          <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="loading || !pagination.hasNext" @click="goToPage(page + 1)">Berikutnya</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Edit Identitas Endpoint -->
  <div v-if="editingIdentity" class="modal fade show d-block" tabindex="-1" role="dialog" aria-labelledby="editEndpointIdentityTitle">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form @submit.prevent="saveIdentity">
          <div class="modal-header">
            <div>
              <h5 id="editEndpointIdentityTitle" class="modal-title">Edit Identitas Endpoint</h5>
              <small class="text-muted"> Ubah identitas endpoint registry. </small>
            </div>

            <button type="button" class="btn-close" aria-label="Tutup" :disabled="savingIdentity" @click="closeEdit"></button>
          </div>

          <div class="modal-body">
            <div v-if="identityError" class="alert alert-danger" role="alert">
              {{ identityError }}
            </div>

            <div class="mb-3">
              <label class="form-label" for="editIdentityModule"> Module </label>
              <input id="editIdentityModule" v-model="editForm.module" type="text" class="form-control" required maxlength="100" :disabled="savingIdentity" />
            </div>

            <div class="mb-3">
              <label class="form-label" for="editIdentityController"> Controller </label>
              <input id="editIdentityController" v-model="editForm.controller" type="text" class="form-control" required maxlength="150" :disabled="savingIdentity" />
            </div>

            <div class="mb-3">
              <label class="form-label" for="editIdentityMethod"> Method </label>
              <input id="editIdentityMethod" v-model="editForm.method" type="text" class="form-control" required maxlength="100" :disabled="savingIdentity" />
            </div>

            <div>
              <label class="form-label"> Preview Controller::Method </label>
              <div class="form-control bg-light">
                <code>{{ identityPreview }}</code>
              </div>
            </div>

            <input type="hidden" v-model="editForm.id" />
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-light" :disabled="savingIdentity" @click="closeEdit">Batal</button>

            <button type="submit" class="btn btn-primary" :disabled="savingIdentity">
              <span v-if="savingIdentity" class="spinner-border spinner-border-sm me-1"></span>
              {{ savingIdentity ? "Menyimpan..." : "Simpan Perubahan" }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div v-if="editingIdentity" class="modal-backdrop fade show" @click="closeEdit"></div>
</template>
