<script setup lang="ts">
defineProps<{ buyerName: string; buyerPhone: string; collateralNote: string; disabled?: boolean }>();

const emit = defineEmits<{
  (e: "update:buyerName", v: string): void;
  (e: "update:buyerPhone", v: string): void;
  (e: "update:collateralNote", v: string): void;
}>();
</script>

<template>
  <div class="border rounded p-3 mb-3">
    <div class="fw-semibold mb-2">Pembeli tempo (belum terdaftar)</div>
    <div class="mb-2">
      <label class="form-label mb-1">Nama pembeli <span class="text-danger">*</span></label>
      <input class="form-control" :value="buyerName" :disabled="disabled" @input="emit('update:buyerName', ($event.target as HTMLInputElement).value)" />
    </div>
    <div class="mb-2">
      <label class="form-label mb-1">Telepon</label>
      <input class="form-control" :value="buyerPhone" :disabled="disabled" @input="emit('update:buyerPhone', ($event.target as HTMLInputElement).value)" />
    </div>
    <div>
      <label class="form-label mb-1">Jaminan <span class="text-danger">*</span></label>
      <input class="form-control" placeholder="mis. KTP, STNK, jam tangan" :value="collateralNote" :disabled="disabled" @input="emit('update:collateralNote', ($event.target as HTMLInputElement).value)" />
    </div>
  </div>
</template>
