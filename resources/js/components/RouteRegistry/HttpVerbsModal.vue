<script setup>
import { ref, nextTick } from "vue";
import { useRegistryApi } from "./composables/useRegistryApi.js";

const props = defineProps({
  apiBase: {
    type: String,
    required: true,
  },
});

const emit = defineEmits(["saved"]);

const api = useRegistryApi(props.apiBase);

const modalElement = ref(null);
const endpointId = ref("");
const endpoint = ref("");
const selectedVerbs = ref([]);
const loading = ref(false);
const saving = ref(false);
const errorMessage = ref("");
const successMessage = ref("");

const availableVerbs = ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS", "HEAD"];

let modalInstance = null;

function getModalInstance() {
  if (!window.bootstrap?.Modal) {
    throw new Error("Bootstrap Modal tidak tersedia pada layout.");
  }

  modalInstance = window.bootstrap.Modal.getOrCreateInstance(modalElement.value);

  return modalInstance;
}

function unwrap(result) {
  return result?.data ?? result;
}

function normalizeVerbs(data) {
  const verbs = Array.isArray(data) ? data : (data?.verbs ?? data?.http_verbs ?? data?.allowed_verbs ?? []);

  return verbs.map((verb) => String(typeof verb === "object" ? (verb.verb ?? verb.method ?? "") : verb).toUpperCase()).filter((verb) => availableVerbs.includes(verb));
}

async function open(id, endpointName = "") {
  if (!id) {
    errorMessage.value = "ID endpoint tidak ditemukan.";
    return;
  }

  endpointId.value = id;
  endpoint.value = endpointName || `Endpoint ID: ${id}`;
  selectedVerbs.value = [];
  errorMessage.value = "";
  successMessage.value = "";
  loading.value = true;

  await nextTick();

  try {
    getModalInstance().show();

    const result = await api.get(`/getVerbs/${encodeURIComponent(id)}`);

    selectedVerbs.value = normalizeVerbs(unwrap(result));
  } catch (error) {
    errorMessage.value = `Gagal memuat HTTP verbs: ${error.message}`;
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (!endpointId.value || saving.value) {
    return;
  }

  const verbs = availableVerbs.filter((verb) => item._selectedVerbs?.includes(verb));

  saving.value = true;
  errorMessage.value = "";
  successMessage.value = "";

  try {
    await api.post("/saveVerbs", {
      id: Number(endpointId.value),
      verbs: [...selectedVerbs.value],
    });

    successMessage.value = "HTTP verbs berhasil disimpan.";

    emit("saved", {
      id: endpointId.value,
      verbs: [...selectedVerbs.value],
    });

    getModalInstance().hide();
  } catch (error) {
    errorMessage.value = error.message;
  } finally {
    saving.value = false;
  }
}

defineExpose({ open });
</script>

<template>
  <div ref="modalElement" id="rrVerbsModal" class="modal fade" tabindex="-1" aria-labelledby="rrVerbsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 id="rrVerbsModalLabel" class="modal-title">Pengaturan HTTP Verbs</h5>
            <small class="text-muted">{{ endpoint }}</small>
          </div>

          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>

        <div class="modal-body">
          <div v-if="errorMessage" class="alert alert-danger" role="alert">
            {{ errorMessage }}
          </div>

          <div v-if="successMessage" class="alert alert-success" role="status">
            {{ successMessage }}
          </div>

          <div v-if="loading" class="text-center py-3">
            <span class="spinner-border spinner-border-sm me-2"></span>
            Memuat HTTP verbs...
          </div>

          <template v-else>
            <input type="hidden" :value="endpointId" />

            <p class="text-muted small">Pilih HTTP method yang diizinkan untuk endpoint ini.</p>

            <div class="row g-2">
              <div v-for="verb in availableVerbs" :key="verb" class="col-6 col-md-4">
                <label class="form-check border rounded p-2 ps-5">
                  <input v-model="selectedVerbs" class="form-check-input" type="checkbox" :value="verb" />
                  <span class="form-check-label">
                    {{ verb }}
                  </span>
                </label>
              </div>
            </div>
          </template>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>

          <button type="button" class="btn btn-primary" :disabled="loading || saving || !endpointId" @click="save">
            <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
            {{ saving ? "Menyimpan..." : "Simpan HTTP Verbs" }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
