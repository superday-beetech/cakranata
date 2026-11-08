<script setup lang="ts">
import { computed } from "vue";

type TaxMode = "amount" | "percent";

const props = withDefaults(
  defineProps<{
    mode: TaxMode;
    value: number;
    baseAmount: number;
    disabled?: boolean;
    /** Tampilkan baris "Nilai pajak" di bawah input. */
    showPreview?: boolean;
  }>(),
  { showPreview: true },
);

const emit = defineEmits<{
  "update:mode": [value: TaxMode];
  "update:value": [value: number];
}>();

// Tampilan input: persen apa adanya, nominal dengan pemisah ribuan (1500000 -> 1.500.000)
const displayValue = computed(() => {
  if (props.mode === "percent") {
    return props.value === 0 ? "" : props.value.toString();
  }

  if (!props.value || props.value === 0) return "";
  return new Intl.NumberFormat("id-ID").format(props.value);
});

// Nilai nominal pajak aktual
const previewAmount = computed(() => {
  const value = Math.max(0, Number(props.value) || 0);
  const base = Math.max(0, props.baseAmount);

  if (props.mode === "percent") {
    return Math.round((base * Math.min(value, 100)) / 100);
  }

  return value;
});

// Beralih ke persen: batasi nilai maksimal 100
function handleModeChange(event: Event): void {
  const newMode = (event.target as HTMLSelectElement).value as TaxMode;
  emit("update:mode", newMode);

  if (newMode === "percent" && props.value > 100) {
    emit("update:value", 100);
  }
}

function updateValue(event: Event): void {
  const inputEl = event.target as HTMLInputElement;
  const cleanDigits = inputEl.value.replace(/[^0-9]/g, "");
  let numeric = cleanDigits ? parseInt(cleanDigits, 10) : 0;

  if (props.mode === "percent") {
    // Kunci otomatis maksimal 100
    if (numeric > 100) {
      numeric = 100;
      inputEl.value = "100";
    }

    emit("update:value", numeric);
    return;
  }

  // Mode nominal (Rp): emit angka murni, tampilkan dengan pemisah ribuan
  emit("update:value", numeric);
  inputEl.value = numeric === 0 ? "" : new Intl.NumberFormat("id-ID").format(numeric);
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
    <div class="input-group input-group-sm">
      <select class="form-select" :value="mode" :disabled="disabled" aria-label="Mode perhitungan pajak" @change="handleModeChange">
        <option value="percent">Persen (%)</option>
        <option value="amount">Nominal (Rp)</option>
      </select>

      <input type="text" class="form-control" inputmode="numeric" aria-label="Nilai pajak" :value="displayValue" :disabled="disabled" :placeholder="mode === 'percent' ? '0 - 100' : '0'" @input="updateValue" />
    </div>

    <div v-if="showPreview" class="pos-tax-preview">
      <span>Nilai pajak</span>
      <strong>{{ currency(previewAmount) }}</strong>
    </div>
  </div>
</template>
