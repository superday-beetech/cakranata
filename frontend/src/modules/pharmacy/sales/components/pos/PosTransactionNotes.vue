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
  <section class="pos-notes">
    <label for="pos-transaction-notes">
      <i class="ti ti-notes"></i>
      Catatan Transaksi
    </label>

    <textarea
      id="pos-transaction-notes"
      :value="modelValue"
      :disabled="disabled"
      rows="3"
      maxlength="1000"
      placeholder="Catatan umum untuk transaksi ini..."
      @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    ></textarea>

    <small>{{ modelValue.length }}/1000 karakter</small>
  </section>
</template>

<style scoped>
.pos-notes {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.pos-notes label {
  align-items: center;
  color: #475569;
  display: flex;
  font-size: 11px;
  font-weight: 700;
  gap: 6px;
}

.pos-notes label i {
  color: #64748b;
  font-size: 15px;
}

.pos-notes textarea {
  border: 1px solid #dbe1e8;
  border-radius: 7px;
  font-size: 11px;
  line-height: 1.5;
  padding: 9px 10px;
  resize: vertical;
  width: 100%;
}

.pos-notes textarea:focus {
  border-color: #2563eb;
  outline: none;
}

.pos-notes small {
  color: #94a3b8;
  font-size: 10px;
  text-align: right;
}
</style>
