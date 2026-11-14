<!--PrintTemplateShow--->
<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import PaperPreview from "../components/PaperPreview.vue";
import { getPrintTemplate, extractErrorMessage } from "../services/printTemplates";
import type { PrintTemplate } from "../types/printTemplate";

const props = defineProps<{ id: number | null }>();
const route = useRoute();
const router = useRouter();
const loading = ref(true);
const errorMessage = ref<string | null>(null);
const data = ref<PrintTemplate | null>(null);

onMounted(async () => {
  if (!props.id) return;
  try {
    data.value = await getPrintTemplate(props.id);
  } catch (err) {
    errorMessage.value = extractErrorMessage(err);
  } finally {
    loading.value = false;
  }
});

function goBack() {
  router.push({ path: route.path });
}
function goEdit() {
  if (!props.id) return;
  router.push({ path: route.path, query: { mode: "edit", id: String(props.id) } });
}
function openRender() {
  if (!data.value) return;
  window.open(`/pharmacy/print-templates/${data.value.id}/render`, "_blank");
}
function openPdf() {
  if (!data.value) return;
  window.open(`/pharmacy/print-templates/${data.value.id}/render.pdf?inline=1`, "_blank");
}
function formatDate(s: string) {
  if (!s) return "-";
  return new Date(s).toLocaleString("id-ID", {
    dateStyle: "medium",
    timeStyle: "short",
  });
}
</script>

<template>
  <div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-outline-secondary" @click="goBack"><i class="ti ti-arrow-left me-1"></i> Daftar</button>
        <h4 class="mb-0">{{ data?.name ?? "Detail Template" }}</h4>
      </div>
      <button v-if="data" class="btn btn-primary" @click="goEdit"><i class="ti ti-pencil me-1"></i> Edit</button>
    </div>

    <div v-if="errorMessage" class="alert alert-danger">{{ errorMessage }}</div>
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary"></div>
    </div>

    <div v-else-if="data" class="row g-3">
      <div class="col-md-8">
        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0">Pratinjau</h5>
          </div>
          <div class="card-body bg-light text-center">
            <PaperPreview :paper="data.paper_config" :elements="data.design_json?.elements ?? []" :logo="data.design_json?.logo" />
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0">Info</h5>
          </div>
          <div class="card-body">
            <dl class="row mb-0">
              <dt class="col-5">Nama</dt>
              <dd class="col-7">{{ data.name }}</dd>
              <dt class="col-5">Tipe</dt>
              <dd class="col-7">{{ data.type }}</dd>
              <dt class="col-5">Ukuran</dt>
              <dd class="col-7">{{ data.paper_type }}</dd>
              <dt class="col-5">Dibuat</dt>
              <dd class="col-7">{{ formatDate(data.created_at) }}</dd>
              <dt class="col-5">Diperbarui</dt>
              <dd class="col-7">{{ formatDate(data.updated_at) }}</dd>
            </dl>
          </div>
        </div>
        <div class="card mt-3">
          <div class="card-header">
            <h5 class="card-title mb-0">Aksi</h5>
          </div>
          <div class="card-body d-grid gap-2">
            <button class="btn btn-outline-secondary" @click="openRender"><i class="ti ti-printer me-1"></i> Cetak Uji</button>
            <button class="btn btn-outline-danger" @click="openPdf"><i class="ti ti-file-type-pdf me-1"></i> Preview PDF</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
