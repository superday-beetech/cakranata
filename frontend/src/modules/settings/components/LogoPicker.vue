<script setup lang="ts">
import type { GlobalLogo } from "../types/printTemplate";

const props = defineProps<{ modelValue: GlobalLogo | undefined }>();
const emit = defineEmits<{ "update:modelValue": [GlobalLogo] }>();

const defaultLogo: GlobalLogo = { url: "", x: 10, y: 8, width: 20, height: 20 };

function update<K extends keyof GlobalLogo>(key: K, value: GlobalLogo[K]) {
  emit("update:modelValue", { ...(props.modelValue ?? defaultLogo), [key]: value });
}

function onFile(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (!file) return;
  if (file.size > 300_000) {
    alert("Ukuran logo maksimal 300 KB. Kompres dulu atau perkecil resolusinya.");
    return;
  }
  const reader = new FileReader();
  reader.onload = () => update("url", reader.result as string);
  reader.readAsDataURL(file);
}

function clearLogo() {
  update("url", "");
}
</script>

<template>
  <div>
    <h6 class="text-muted small text-uppercase mb-2">Logo Kop Surat</h6>

    <div class="d-flex align-items-center gap-2 mb-3">
      <div class="logo-thumb border rounded d-flex align-items-center justify-content-center bg-light">
        <img v-if="modelValue?.url" :src="modelValue.url" alt="logo" />
        <i v-else class="ti ti-photo text-muted"></i>
      </div>
      <div class="flex-grow-1">
        <label class="btn btn-sm btn-outline-primary w-100 mb-1">
          <i class="ti ti-upload me-1"></i> Pilih Logo
          <input type="file" accept="image/png,image/jpeg,image/svg+xml" class="d-none" @change="onFile" />
        </label>
        <button v-if="modelValue?.url" class="btn btn-sm btn-soft-danger w-100" @click="clearLogo"><i class="ti ti-x me-1"></i> Hapus Logo</button>
      </div>
    </div>

    <div class="row g-2">
      <div class="col-6">
        <label class="form-label small">Lebar (mm)</label>
        <input :value="modelValue?.width ?? 20" type="number" min="5" max="100" class="form-control form-control-sm" @input="update('width', Number(($event.target as HTMLInputElement).value))" />
      </div>
      <div class="col-6">
        <label class="form-label small">Tinggi (mm)</label>
        <input :value="modelValue?.height ?? 20" type="number" min="5" max="100" class="form-control form-control-sm" @input="update('height', Number(($event.target as HTMLInputElement).value))" />
      </div>
      <div class="col-6">
        <label class="form-label small">Posisi X (mm)</label>
        <input :value="modelValue?.x ?? 10" type="number" min="0" class="form-control form-control-sm" @input="update('x', Number(($event.target as HTMLInputElement).value))" />
      </div>
      <div class="col-6">
        <label class="form-label small">Posisi Y (mm)</label>
        <input :value="modelValue?.y ?? 8" type="number" min="0" class="form-control form-control-sm" @input="update('y', Number(($event.target as HTMLInputElement).value))" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.logo-thumb {
  width: 60px;
  height: 60px;
  overflow: hidden;
  flex-shrink: 0;
}
.logo-thumb img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}
</style>
