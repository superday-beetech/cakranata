<script setup lang="ts">
defineProps<{
  modelValue: string;
  disabled?: boolean;
}>();

const emit = defineEmits<{
  "update:modelValue": [value: string];
}>();
</script>

<template>
  <div class="pos-notes">
    <label class="visually-hidden" for="pos-transaction-notes">Catatan transaksi</label>

    <textarea
      id="pos-transaction-notes"
      class="form-control"
      :value="modelValue"
      :disabled="disabled"
      maxlength="1000"
      placeholder="Catatan umum untuk transaksi ini (misalnya resep, titipan, atau permintaan pelanggan)"
      @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    ></textarea>

    <small>{{ modelValue.length }}/1000 karakter</small>
  </div>
</template>
