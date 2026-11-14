<script setup lang="ts">
defineProps<{
  title: string;
  message: string;
  confirmText?: string;
  confirmClass?: string;
  icon?: string;
}>();

const emit = defineEmits<{
  close: [];
  select: [value: boolean];
}>();
</script>

<template>
  <div class="text-center py-2">
    <div class="mb-3" :class="confirmClass === 'btn-danger' ? 'text-danger' : 'text-warning'">
      <i :class="icon || 'ti ti-alert-circle'" style="font-size: 3.5rem"></i>
    </div>

    <h5 class="mb-2 font-weight-bold">{{ title }}</h5>
    <p class="text-muted mb-4">{{ message }}</p>

    <div class="d-flex gap-2 justify-content-center">
      <button type="button" class="btn btn-light px-4" @click="emit('close')">Batal</button>
      <button type="button" class="btn px-4" :class="confirmClass || 'btn-danger'" @click="emit('select', true)">
        {{ confirmText || "Ya, Lanjutkan" }}
      </button>
    </div>
  </div>
</template>
