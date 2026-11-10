<script setup lang="ts">
type TaxMode = "amount" | "percent";

defineProps<{
  mode: TaxMode;
  value: number;
  baseAmount: number;
  disabled?: boolean;
}>();

const emit = defineEmits<{
  "update:mode": [value: TaxMode];
  "update:value": [value: number];
}>();

function updateValue(event: Event): void {
  const raw = Number((event.target as HTMLInputElement).value);
  emit("update:value", Number.isFinite(raw) ? Math.max(0, raw) : 0);
}
</script>

<template>
  <div class="pos-field">
    <label>Pajak</label>

    <div class="pos-input-group">
      <select :value="mode" :disabled="disabled" @change="emit('update:mode', ($event.target as HTMLSelectElement).value as TaxMode)">
        <option value="percent">%</option>
        <option value="amount">Rp</option>
      </select>

      <input type="number" min="0" :max="mode === 'percent' ? 100 : undefined" step="0.01" :value="value" :disabled="disabled" placeholder="0" @input="updateValue" />
    </div>

    <small>
      Dasar pengenaan pajak:
      {{ new Intl.NumberFormat("id-ID").format(baseAmount) }}
    </small>
  </div>
</template>

<style scoped>
.pos-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.pos-field label {
  color: #475569;
  font-size: 11px;
  font-weight: 700;
}

.pos-input-group {
  display: flex;
  min-width: 0;
}

.pos-input-group select,
.pos-input-group input {
  background: white;
  border: 1px solid #dbe1e8;
  font-size: 12px;
  min-height: 36px;
  padding: 7px 9px;
}

.pos-input-group select {
  border-radius: 7px 0 0 7px;
  color: #475569;
  min-width: 65px;
}

.pos-input-group input {
  border-left: 0;
  border-radius: 0 7px 7px 0;
  min-width: 0;
  text-align: right;
  width: 100%;
}

.pos-input-group input:focus,
.pos-input-group select:focus {
  border-color: #2563eb;
  outline: none;
}

.pos-field small {
  color: #94a3b8;
  font-size: 10px;
}
</style>
