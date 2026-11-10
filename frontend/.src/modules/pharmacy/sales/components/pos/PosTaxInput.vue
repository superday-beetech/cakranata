<script setup lang="ts">
import { computed } from "vue";

type TaxMode = "amount" | "percent";

const props = defineProps<{
  mode: TaxMode;
  value: number;
  baseAmount: number;
  disabled?: boolean;
}>();

const emit = defineEmits<{
  "update:mode": [value: TaxMode];
  "update:value": [value: number];
}>();

const previewAmount = computed(() => {
  const value = Math.max(0, Number(props.value) || 0);
  const base = Math.max(0, props.baseAmount);

  if (props.mode === "percent") {
    return Math.round((base * Math.min(value, 100)) / 100);
  }

  return value;
});

function updateValue(event: Event): void {
  const raw = Number((event.target as HTMLInputElement).value);

  emit("update:value", Number.isFinite(raw) ? Math.max(0, raw) : 0);
}

function currency(value: number): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(value);
}
</script>

<template>
  <div class="pos-tax-input">
    <div class="pos-input-group">
      <select :value="mode" :disabled="disabled" aria-label="Mode perhitungan pajak" @change="emit('update:mode', ($event.target as HTMLSelectElement).value as TaxMode)">
        <option value="percent">Persen (%)</option>
        <option value="amount">Nominal (Rp)</option>
      </select>

      <input type="number" min="0" :max="mode === 'percent' ? 100 : undefined" step="any" :value="value" :disabled="disabled" :placeholder="mode === 'percent' ? 'Persentase pajak' : 'Nominal pajak'" @input="updateValue" />
    </div>

    <div class="pos-tax-preview">
      <span>Nilai pajak</span>
      <strong>{{ currency(previewAmount) }}</strong>
    </div>
  </div>
</template>
