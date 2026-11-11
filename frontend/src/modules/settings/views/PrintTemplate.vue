<script setup lang="ts">
import { reactive, ref, computed, onMounted } from "vue";
import { useRoute } from "vue-router";
import PaperSizePicker from "../components/PaperSizePicker.vue";
import ComponentPalette from "../components/ComponentPalette.vue";
import PaperCanvas from "../components/PaperCanvas.vue";
import PaperPreview from "../components/PaperPreview.vue";
import ElementInspector from "../components/ElementInspector.vue";
import { createPrintTemplate, updatePrintTemplate, getPrintTemplate, extractErrorMessage } from "../services/printTemplates";
import type { PaperConfig, PrintElement } from "../types/printTemplate";

const route = useRoute();
const saving = ref(false);
const loading = ref(false);
const errorMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);
const rightTab = ref<"preview" | "json">("preview");
const form = reactive<{ id: number | null; name: string; type: string }>({
  id: null,
  name: "",
  type: "kwitansi",
});
const paper = ref<PaperConfig>({
  type: "a4",
  width: 210,
  height: 297,
  unit: "mm",
  orientation: "portrait",
});
const elements = ref<PrintElement[]>([]);
const selectedId = ref<string | null>(null);
const selectedElement = computed(() => elements.value.find((e) => e.id === selectedId.value) ?? null);
const designJsonPretty = computed(() => JSON.stringify({ paper: paper.value, elements: elements.value }, null, 2));

function onAdd(el: PrintElement) {
  elements.value.push(el);
  selectedId.value = el.id;
}
function onMove({ id, x, y }: { id: string; x: number; y: number }) {
  const el = elements.value.find((e) => e.id === id);
  if (el) {
    el.x = x;
    el.y = y;
  }
}
function onResize({ id, width, height }: { id: string; width: number; height: number }) {
  const el = elements.value.find((e) => e.id === id);
  if (el) {
    el.width = width;
    el.height = height;
  }
}
function onUpdateElement(patch: Partial<PrintElement> & { id: string }) {
  const el = elements.value.find((e) => e.id === patch.id);
  if (el) Object.assign(el, patch);
}
function onDelete(id: string) {
  elements.value = elements.value.filter((e) => e.id !== id);
  if (selectedId.value === id) selectedId.value = null;
}
function onSelect(id: string | null) {
  selectedId.value = id;
}

async function save() {
  if (!form.name.trim()) {
    errorMessage.value = "Nama template wajib diisi.";
    return;
  }
  saving.value = true;
  errorMessage.value = null;
  successMessage.value = null;
  try {
    const payload = {
      name: form.name,
      type: form.type,
      paper_type: paper.value.type,
      paper_config: paper.value,
      design_json: { elements: elements.value },
    };
    const result = form.id ? await updatePrintTemplate(form.id, payload) : await createPrintTemplate(payload);
    form.id = result.id;
    successMessage.value = "Template berhasil disimpan.";
    setTimeout(() => (successMessage.value = null), 2500);
  } catch (err) {
    errorMessage.value = extractErrorMessage(err);
  } finally {
    saving.value = false;
  }
}
async function load(id: number) {
  loading.value = true;
  errorMessage.value = null;
  try {
    const data = await getPrintTemplate(id);
    form.id = data.id;
    form.name = data.name;
    form.type = data.type;
    paper.value = data.paper_config;
    elements.value = data.design_json?.elements ?? [];
  } catch (err) {
    errorMessage.value = extractErrorMessage(err);
  } finally {
    loading.value = false;
  }
}
function printTest() {
  if (!form.id) return;
  window.open(`/pharmacy/print-templates/${form.id}/render`, "_blank");
}
function previewPdf() {
  if (!form.id) return;
  window.open(`/pharmacy/print-templates/${form.id}/render.pdf?inline=1`, "_blank");
}
onMounted(() => {
  const id = route.params.id;
  if (id) load(Number(id));
});
</script>

<template>
  <div class="container-fluid">
    <div class="row">
      <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
          <h4 class="mb-0">{{ form.id ? "Edit Template Cetak" : "Buat Template Cetak" }}</h4>
          <ol class="breadcrumb m-0">
            <li class="breadcrumb-item"><a href="#">Farmasi</a></li>
            <li class="breadcrumb-item"><a href="#">Template Cetak</a></li>
            <li class="breadcrumb-item active">{{ form.id ? "Edit" : "Baru" }}</li>
          </ol>
        </div>
      </div>
    </div>

    <div v-if="errorMessage" class="alert alert-danger alert-dismissible fade show">
      <i class="ti ti-alert-circle me-1"></i> {{ errorMessage }}
      <button type="button" class="btn-close" @click="errorMessage = null"></button>
    </div>
    <div v-if="successMessage" class="alert alert-success alert-dismissible fade show">
      <i class="ti ti-circle-check me-1"></i> {{ successMessage }}
      <button type="button" class="btn-close" @click="successMessage = null"></button>
    </div>

    <div class="card">
      <div class="card-body py-3">
        <div class="row g-3 align-items-end">
          <div class="col-md-3">
            <label class="form-label">Nama Template <span class="text-danger">*</span></label>
            <input v-model="form.name" type="text" class="form-control" placeholder="Kwitansi Penjualan" />
          </div>
          <div class="col-md-2">
            <label class="form-label">Tipe</label>
            <select v-model="form.type" class="form-select">
              <option value="kwitansi">Kwitansi</option>
              <option value="struk">Struk</option>
              <option value="penerimaan_barang">Penerimaan Barang</option>
            </select>
          </div>
          <div class="col-md-2"><label class="form-label">Ukuran</label><PaperSizePicker v-model="paper" /></div>
          <div class="col-md-1">
            <label class="form-label">Orientasi</label>
            <select v-model="paper.orientation" class="form-select">
              <option value="portrait">P</option>
              <option value="landscape">L</option>
            </select>
          </div>
          <div class="col-md-2 d-grid">
            <button type="button" class="btn btn-primary" :disabled="saving || loading" @click="save">
              <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="ti ti-device-floppy me-1"></i>
              {{ saving ? "Menyimpan..." : form.id ? "Update" : "Simpan" }}
            </button>
          </div>
          <div class="col-md-2 d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary flex-fill" :disabled="!form.id" @click="printTest">
              <i class="ti ti-printer"></i>
            </button>
            <button type="button" class="btn btn-outline-danger flex-fill" :disabled="!form.id" @click="previewPdf">
              <i class="ti ti-file-type-pdf"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-xl-8 col-lg-7">
        <div class="card h-100">
          <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0"><i class="ti ti-pencil me-1"></i> Desain</h5>
            <span class="badge bg-light text-dark">{{ elements.length }} elemen</span>
          </div>
          <div class="card-body p-0 designer-body">
            <div class="designer-layout">
              <div class="designer-sidebar border-end">
                <div class="p-2 border-bottom"><small class="text-muted fw-semibold">KOMPONEN</small></div>
                <div class="p-2"><ComponentPalette /></div>
              </div>
              <div class="designer-canvas">
                <div v-if="loading" class="text-center py-5">
                  <div class="spinner-border text-primary"></div>
                  <p class="text-muted mt-2 mb-0">Memuat...</p>
                </div>
                <PaperCanvas
                  v-else
                  :paper="paper"
                  :elements="elements"
                  :selected-id="selectedId"
                  @add-element="onAdd"
                  @move-element="onMove"
                  @resize-element="onResize"
                  @update-element="onUpdateElement"
                  @delete-element="onDelete"
                  @select-element="onSelect"
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-xl-4 col-lg-5">
        <div class="card">
          <div class="card-header p-0">
            <ul class="nav nav-tabs card-header-tabs m-0">
              <li class="nav-item">
                <button class="nav-link" :class="{ active: rightTab === 'preview' }" @click="rightTab = 'preview'"><i class="ti ti-eye me-1"></i> Preview</button>
              </li>
              <li class="nav-item">
                <button class="nav-link" :class="{ active: rightTab === 'json' }" @click="rightTab = 'json'"><i class="ti ti-code me-1"></i> JSON</button>
              </li>
            </ul>
          </div>
          <div class="card-body preview-body">
            <PaperPreview v-if="rightTab === 'preview'" :paper="paper" :elements="elements" />
            <pre v-else class="json-preview mb-0">{{ designJsonPretty }}</pre>
          </div>
        </div>
        <div class="card mt-3">
          <div class="card-header">
            <h5 class="card-title mb-0"><i class="ti ti-adjustments me-1"></i> Properti</h5>
          </div>
          <div class="card-body">
            <ElementInspector :element="selectedElement" @update-element="onUpdateElement" @delete-element="onDelete" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.designer-body {
  min-height: 70vh;
}
.designer-layout {
  display: grid;
  grid-template-columns: 180px 1fr;
  min-height: 70vh;
}
.designer-sidebar {
  background: #fafbfc;
  overflow-y: auto;
}
.designer-canvas {
  overflow: auto;
  background: #eef2f7;
  padding: 16px;
  display: flex;
  justify-content: center;
}
.preview-body {
  background: #f3f6f9;
  min-height: 50vh;
  overflow: auto;
  padding: 16px;
}
.json-preview {
  background: #1e1e2e;
  color: #cdd6f4;
  padding: 12px;
  border-radius: 6px;
  font-size: 12px;
  line-height: 1.5;
  max-height: 50vh;
  overflow: auto;
}
@media (max-width: 1199.98px) {
  .designer-layout {
    grid-template-columns: 1fr;
  }
}
</style>
