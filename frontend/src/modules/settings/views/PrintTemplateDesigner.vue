<!--PrintTemplateDesigner--->
<script setup lang="ts">
import { reactive, ref, computed, onMounted, onUnmounted, watch, nextTick } from "vue";
import { useRoute, useRouter } from "vue-router";
import PaperSizePicker from "../components/PaperSizePicker.vue";
import ComponentPalette from "../components/ComponentPalette.vue";
import PaperCanvas from "../components/PaperCanvas.vue";
import PaperPreview from "../components/PaperPreview.vue";
import ElementInspector from "../components/ElementInspector.vue";
import LayerList from "../components/LayerList.vue";
import StandardTemplatePicker from "../components/StandardTemplatePicker.vue";
import LogoPicker from "../components/LogoPicker.vue";
import { useHistory } from "../composables/useHistory";
import { useDesignerShortcuts } from "../composables/useDesignerShortcuts";
import { createPrintTemplate, updatePrintTemplate, getPrintTemplate, extractErrorMessage } from "../services/printTemplates";
import type { GlobalLogo, PaperConfig, PrintElement, StandardTemplatePreset } from "../types/printTemplate";
import ValidationBanner from "../components/ValidationBanner.vue";
import { validateDesign, designJsonSizeBytes, MAX_DESIGN_JSON_BYTES, type ValidationResult } from "../utils/validateDesign";
const props = defineProps<{ id: number | null }>();
const route = useRoute();
const router = useRouter();
const saving = ref(false);
const loading = ref(false);
const errorMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);

const mode = ref<"standard" | "blank" | "editor">("standard");
const rightTab = ref<"preview" | "json" | "logo">("preview");
const zoom = ref(1);

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
const logo = ref<GlobalLogo>({ url: "", x: 10, y: 8, width: 20, height: 20 });
const logoSelected = ref(false);

const selectedElement = computed(() => elements.value.find((e) => e.id === selectedId.value) ?? null);
const designJsonPretty = computed(() => JSON.stringify({ paper: paper.value, elements: elements.value, logo: logo.value }, null, 2));

/* ---------- history ---------- */
const { undo, redo, reset: resetHistory, canUndo, canRedo } = useHistory(elements);

const { undo: undoLogo, redo: redoLogo, reset: resetLogoHistory, canUndo: canUndoLogo, canRedo: canRedoLogo } = useHistory(logo);

/* ---------- shortcuts ---------- */
useDesignerShortcuts({
  elements,
  selectedId,
  logoSelected,
  onDeleteLogo: () => {
    logo.value = { ...logo.value, url: "" };
    logoSelected.value = false;
  },
  onDelete,
  onSelect,
  onUndo: () => {
    if (logoSelected.value) undoLogo();
    else undo();
  },
  onRedo: () => {
    if (logoSelected.value) redoLogo();
    else redo();
  },
});

/* ---------- element handlers ---------- */
function onAdd(el: PrintElement) {
  elements.value.push(el);
  selectedId.value = el.id;
  logoSelected.value = false;
}
function onMove({ id, x, y }: { id: string; x: number; y: number }) {
  const el = elements.value.find((e) => e.id === id);
  if (el && !el.props.locked) {
    el.x = x;
    el.y = y;
  }
}
function onResize({ id, width, height }: { id: string; width: number; height: number }) {
  const el = elements.value.find((e) => e.id === id);
  if (el && !el.props.locked) {
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
  if (id) logoSelected.value = false;
}

/* ---------- logo handlers ---------- */
function onUpdateLogo(patch: Partial<GlobalLogo>) {
  logo.value = { ...logo.value, ...patch };
  if (patch.url === "") logoSelected.value = false;
}
function onMoveLogo(p: { x: number; y: number }) {
  logo.value = { ...logo.value, x: p.x, y: p.y };
}
function onResizeLogo(p: { width: number; height: number }) {
  logo.value = { ...logo.value, width: p.width, height: p.height };
}
function onSelectLogo() {
  logoSelected.value = true;
  selectedId.value = null;
}
function onDeselectLogo() {
  logoSelected.value = false;
}

/* ---------- layers ---------- */
function layerMoveUp(id: string) {
  const i = elements.value.findIndex((e) => e.id === id);
  if (i < 0 || i >= elements.value.length - 1) return;
  const copy = [...elements.value];
  [copy[i], copy[i + 1]] = [copy[i + 1], copy[i]];
  elements.value = copy;
}
function layerMoveDown(id: string) {
  const i = elements.value.findIndex((e) => e.id === id);
  if (i <= 0) return;
  const copy = [...elements.value];
  [copy[i], copy[i - 1]] = [copy[i - 1], copy[i]];
  elements.value = copy;
}
function layerToggleLock(id: string) {
  const el = elements.value.find((e) => e.id === id);
  if (el) el.props = { ...el.props, locked: !el.props.locked };
}

/* ---------- standard template ---------- */
function applyStandardTemplate(tpl: StandardTemplatePreset) {
  form.type = tpl.type;
  form.name = tpl.name;
  paper.value = structuredClone(tpl.paper);
  elements.value = structuredClone(tpl.elements);
  logo.value = tpl.logo ? structuredClone(tpl.logo) : { url: "", x: 10, y: 8, width: 20, height: 20 };
  selectedId.value = null;
  logoSelected.value = false;
  mode.value = "editor";
  resetHistory();
  resetLogoHistory();
  successMessage.value = `Template standar "${tpl.name}" dimuat. Silakan sesuaikan.`;
  setTimeout(() => (successMessage.value = null), 2500);
}

function startBlank() {
  elements.value = [];
  logo.value = { url: "", x: 10, y: 8, width: 20, height: 20 };
  selectedId.value = null;
  logoSelected.value = false;
  mode.value = "editor";
  resetHistory();
  resetLogoHistory();
}

function switchToStandard() {
  if (elements.value.length && !confirm("Beralih ke template standar akan mengganti desain saat ini. Lanjutkan?")) return;
  mode.value = "standard";
}

/* ---------- save / load ---------- */
async function save() {
  if (!form.name.trim()) {
    errorMessage.value = "Nama template wajib diisi.";
    return;
  }

  const v = validateDesign(paper.value, elements.value, logo.value);
  validation.value = v;

  if (v.errors.length > 0) {
    errorMessage.value = `Tidak bisa menyimpan: ${v.errors.length} kesalahan. Lihat banner validasi.`;
    return;
  }

  const size = designJsonSizeBytes({
    paper: paper.value,
    elements: elements.value,
    logo: logo.value,
  });
  if (size > MAX_DESIGN_JSON_BYTES) {
    errorMessage.value = `Ukuran desain (${Math.round(size / 1000)} KB) melebihi batas ${Math.round(MAX_DESIGN_JSON_BYTES / 1000)} KB. Kurangi ukuran logo/gambar atau jumlah elemen.`;
    return;
  }

  if (v.warnings.length > 0) {
    const ok = confirm(`Ada ${v.warnings.length} peringatan. Tetap simpan?`);
    if (!ok) return;
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
      design_json: { elements: elements.value, logo: logo.value },
    };
    const result = form.id ? await updatePrintTemplate(form.id, payload) : await createPrintTemplate(payload);

    form.id = result.id;
    successMessage.value = "Template berhasil disimpan.";

    // Kalau baru dibuat (props.id masih null), ubah URL ke mode edit
    if (!props.id && result.id) {
      router.replace({
        path: route.path,
        query: { mode: "edit", id: String(result.id) },
      });
    }

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
    paper.value = data.paper_config ?? paper.value;
    elements.value = data.design_json?.elements ?? [];

    // migrasi logo lama (marginTop/marginLeft) → x/y
    const rawLogo = data.design_json?.logo as (Partial<GlobalLogo> & { marginTop?: number; marginLeft?: number }) | undefined;
    logo.value = rawLogo
      ? {
          url: rawLogo.url ?? "",
          x: rawLogo.x ?? rawLogo.marginLeft ?? 10,
          y: rawLogo.y ?? rawLogo.marginTop ?? 8,
          width: rawLogo.width ?? 20,
          height: rawLogo.height ?? 20,
        }
      : { url: "", x: 10, y: 8, width: 20, height: 20 };

    mode.value = "editor";
    resetHistory();
    resetLogoHistory();
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

/* ---------- auto-save draft ---------- */
const draftKey = computed(() => `draft:template:${form.id ?? "new"}`);
let draftTimer: number | null = null;
watch(
  () => [form.name, form.type, paper.value, elements.value, logo.value],
  () => {
    if (draftTimer) window.clearTimeout(draftTimer);
    draftTimer = window.setTimeout(() => {
      if (elements.value.length === 0 && !form.name && !logo.value.url) return;
      localStorage.setItem(
        draftKey.value,
        JSON.stringify({
          form: { ...form },
          paper: paper.value,
          elements: elements.value,
          logo: logo.value,
          savedAt: Date.now(),
        }),
      );
    }, 800);
  },
  { deep: true },
);

function tryRestoreDraft() {
  const raw = localStorage.getItem(draftKey.value);
  if (!raw) return;
  try {
    const d = JSON.parse(raw);
    const ok = confirm(`Ditemukan draft tersimpan ${new Date(d.savedAt).toLocaleString()}. Pulihkan?`);
    if (!ok) {
      localStorage.removeItem(draftKey.value);
      return;
    }
    form.name = d.form?.name ?? "";
    form.type = d.form?.type ?? "kwitansi";
    paper.value = d.paper ?? paper.value;
    elements.value = d.elements ?? [];
    logo.value = d.logo ?? { url: "", x: 10, y: 8, width: 20, height: 20 };
    mode.value = "editor";
    resetHistory();
    resetLogoHistory();
  } catch {
    /* ignore */
  }
}

const validation = ref<ValidationResult>({ errors: [], warnings: [] });
const designJsonBytes = computed(() => designJsonSizeBytes({ paper: paper.value, elements: elements.value, logo: logo.value }));

watch(
  () => [paper.value, elements.value, logo.value],
  () => {
    validation.value = validateDesign(paper.value, elements.value, logo.value);
  },
  { deep: true, immediate: true },
);

function gotoElement(id: string | undefined) {
  if (!id) return;
  selectedId.value = id;
  logoSelected.value = false;
  mode.value = "editor";
  // scroll ke elemen bisa ditambahkan nanti
  nextTick(() => {
    const node = document.querySelector<HTMLElement>(`[data-el-id="${id}"]`);
    node?.scrollIntoView({ behavior: "smooth", block: "center" });
  });
}

onMounted(() => {
  console.log("[Designer] props.id =", props.id, "typeof:", typeof props.id);
  if (props.id) load(props.id);
  else tryRestoreDraft();
});
onUnmounted(() => {
  if (draftTimer) window.clearTimeout(draftTimer);
});
</script>

<template>
  <div class="container-fluid">
    <div class="row">
      <div class="col-12">
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-sm btn-outline-secondary" @click="router.push('/settings/print-templates')"><i class="ti ti-arrow-left me-1"></i> Daftar</button>
          <h4 class="mb-0">{{ form.id ? "Edit Template Cetak" : "Buat Template Cetak" }}</h4>
        </div>
        <div class="page-title-box d-flex align-items-center justify-content-between">
          <h4 class="mb-0">{{ form.id ? "Edit Template Cetak" : "Buat Template Cetak" }}</h4>
          <ol class="breadcrumb m-0">
            <li class="breadcrumb-item"><a href="#">Farmasi</a></li>
            <li class="breadcrumb-item">
              <router-link to="/settings/print-templates">Template Cetak</router-link>
            </li>
            <li class="breadcrumb-item active">{{ form.id ? "Edit" : "Baru" }}</li>
          </ol>
        </div>
      </div>
    </div>
    <ValidationBanner :errors="validation.errors" :warnings="validation.warnings" @goto-element="gotoElement" />
    <!-- Info ukuran JSON -->
    <div v-if="designJsonBytes > 0" class="text-muted small mb-2 d-flex align-items-center gap-2">
      <i class="ti ti-database"></i>
      Ukuran desain: {{ Math.round(designJsonBytes / 1000) }} KB
      <span v-if="designJsonBytes > MAX_DESIGN_JSON_BYTES" class="badge bg-danger"> Melebihi batas {{ Math.round(MAX_DESIGN_JSON_BYTES / 1000) }} KB </span>
      <span v-else-if="designJsonBytes > MAX_DESIGN_JSON_BYTES * 0.8" class="badge bg-warning text-dark"> Hampir melebihi batas </span>
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
          <div class="col-md-2">
            <label class="form-label">Ukuran</label>
            <PaperSizePicker v-model="paper" />
          </div>
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
            <button type="button" class="btn btn-outline-secondary flex-fill" :disabled="!form.id" @click="printTest" title="Cetak">
              <i class="ti ti-printer"></i>
            </button>
            <button type="button" class="btn btn-outline-danger flex-fill" :disabled="!form.id" @click="previewPdf" title="Preview PDF">
              <i class="ti ti-file-type-pdf"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <ul class="nav nav-tabs nav-tabs-custom mb-2">
      <li class="nav-item">
        <button class="nav-link" :class="{ active: mode === 'standard' }" @click="switchToStandard"><i class="ti ti-template me-1"></i> Desain Standar</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" :class="{ active: mode === 'blank' }" @click="startBlank"><i class="ti ti-square-plus me-1"></i> Kanvas Kosong</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" :class="{ active: mode === 'editor' }" :disabled="mode !== 'editor' && elements.length === 0" @click="mode = 'editor'">
          <i class="ti ti-pencil me-1"></i> Edit Manual
          <span v-if="elements.length" class="badge bg-primary ms-1">{{ elements.length }}</span>
        </button>
      </li>
    </ul>

    <div v-if="mode === 'standard'" class="card">
      <div class="card-body">
        <div class="mb-3">
          <h5 class="mb-0"><i class="ti ti-template me-1"></i> Pilih Template Standar</h5>
          <small class="text-muted"> Template siap pakai — pilih salah satu sebagai titik awal, lalu sesuaikan (termasuk logo). </small>
        </div>
        <StandardTemplatePicker @pick="applyStandardTemplate" />
      </div>
    </div>

    <div v-else-if="mode === 'editor'" class="row g-3">
      <div class="col-xl-8 col-lg-7">
        <div class="card h-100">
          <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h5 class="card-title mb-0"><i class="ti ti-pencil me-1"></i> Desain</h5>
            <div class="d-flex align-items-center gap-2">
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" :disabled="logoSelected ? !canUndoLogo() : !canUndo()" title="Undo (Ctrl+Z)" @click="logoSelected ? undoLogo() : undo()">
                  <i class="ti ti-arrow-back-up"></i>
                </button>
                <button class="btn btn-outline-secondary" :disabled="logoSelected ? !canRedoLogo() : !canRedo()" title="Redo (Ctrl+Shift+Z)" @click="logoSelected ? redoLogo() : redo()">
                  <i class="ti ti-arrow-forward-up"></i>
                </button>
              </div>
              <div class="input-group input-group-sm" style="width: 130px">
                <span class="input-group-text"><i class="ti ti-zoom-in"></i></span>
                <input v-model.number="zoom" type="range" min="0.5" max="2" step="0.1" class="form-range align-self-center" />
                <span class="input-group-text">{{ Math.round(zoom * 100) }}%</span>
              </div>
              <span class="badge bg-light text-dark">{{ elements.length }} elemen</span>
            </div>
          </div>
          <div class="card-body p-0 designer-body">
            <div class="designer-layout">
              <div class="designer-sidebar border-end">
                <LayerList :elements="elements" :selected-id="selectedId" @select="onSelect" @move-up="layerMoveUp" @move-down="layerMoveDown" @toggle-lock="layerToggleLock" @delete="onDelete" />
                <div class="p-2 border-top border-bottom">
                  <small class="text-muted fw-semibold">KOMPONEN</small>
                </div>
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
                  :zoom="zoom"
                  :logo="logo"
                  :logo-selected="logoSelected"
                  @add-element="onAdd"
                  @move-element="onMove"
                  @resize-element="onResize"
                  @update-element="onUpdateElement"
                  @delete-element="onDelete"
                  @select-element="onSelect"
                  @update-logo="onUpdateLogo"
                  @move-logo="onMoveLogo"
                  @resize-logo="onResizeLogo"
                  @select-logo="onSelectLogo"
                  @deselect-logo="onDeselectLogo"
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
              <li class="nav-item">
                <button class="nav-link" :class="{ active: rightTab === 'logo' }" @click="rightTab = 'logo'"><i class="ti ti-photo me-1"></i> Logo</button>
              </li>
            </ul>
          </div>
          <div class="card-body preview-body">
            <PaperPreview v-if="rightTab === 'preview'" :paper="paper" :elements="elements" :logo="logo" />
            <pre v-else-if="rightTab === 'json'" class="json-preview mb-0">{{ designJsonPretty }}</pre>
            <LogoPicker v-else v-model="logo" />
          </div>
        </div>

        <div class="card mt-3">
          <div class="card-header">
            <h5 class="card-title mb-0">
              <i class="ti ti-adjustments me-1"></i>
              {{ logoSelected ? "Properti Logo" : "Properti Elemen" }}
            </h5>
          </div>
          <div class="card-body">
            <LogoPicker v-if="logoSelected" v-model="logo" />
            <ElementInspector v-else :element="selectedElement" @update-element="onUpdateElement" @delete-element="onDelete" />
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
  grid-template-columns: 220px 1fr;
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
  align-items: flex-start;
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
.nav-tabs-custom .nav-link {
  cursor: pointer;
}
.nav-tabs-custom .nav-link:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}
@media (max-width: 1199.98px) {
  .designer-layout {
    grid-template-columns: 1fr;
  }
}
</style>
