<script setup lang="ts">
import type { Unit } from "../services/unitService";

defineProps<{
  unit: Unit;
}>();

const emit = defineEmits<{
  select: [value: Unit];
  close: [];
}>();
</script>

<template>
  <div v-if="unit">
    <div class="mb-3">
      <label class="form-label text-muted">Nama Satuan</label>
      <div class="fw-semibold">{{ unit.name }}</div>
    </div>

    <div class="mb-3">
      <label class="form-label text-muted">Singkatan</label>
      <div class="fw-semibold">{{ unit.abbreviation }}</div>
    </div>

    <div class="mb-3">
      <label class="form-label text-muted">ID</label>
      <div>{{ unit.id }}</div>
    </div>

    <div class="mb-3">
      <label class="form-label text-muted">Dibuat</label>
      <div>{{ unit.created_at || "-" }}</div>
    </div>

    <div class="mb-3">
      <label class="form-label text-muted">Terakhir Diperbarui</label>
      <div>{{ unit.updated_at || "-" }}</div>
    </div>

    <div class="d-flex justify-content-end gap-2">
      <button type="button" class="btn btn-light" @click="emit('close')">Tutup</button>

      <button type="button" class="btn btn-primary" @click="emit('select', unit)">
        <i class="ti ti-edit me-1"></i>
        Ubah
      </button>
    </div>
  </div>
</template>
