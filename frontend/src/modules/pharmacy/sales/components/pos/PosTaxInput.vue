<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { formatCurrency, formatNumber } from "../../utils/format";
import type { TaxMode } from "../../types/sales";
import { calculateTax } from "../../utils/posMath";
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

// Teks persen disimpan sendiri agar ketikan "2," tidak hilang saat mengetik desimal.
function toPercentText(value: number): string {
  return value > 0 ? String(value).replace(".", ",") : "";
}

function parsePercent(text: string): number {
  const number = parseFloat(text.replace(",", "."));
  return Number.isFinite(number) ? number : 0;
}

const percentText = ref(toPercentText(props.value));

watch(
  () => props.value,
  (value) => {
    if (parsePercent(percentText.value) !== value) percentText.value = toPercentText(value);
  },
);

watch(
  () => props.mode,
  () => {
    percentText.value = toPercentText(props.value);
  },
);

// Mode nominal: pemisah ribuan (1500000 -> 1.500.000)
const amountText = computed(() => (props.value > 0 ? formatNumber(props.value) : ""));

const shownValue = computed(() => (props.mode === "percent" ? percentText.value : amountText.value));

// Nilai nominal pajak aktual
const previewAmount = computed(() => calculateTax(props.baseAmount, props.mode, props.value));
function handleModeChange(event: Event): void {
  const newMode = (event.target as HTMLSelectElement).value as TaxMode;
  emit("update:mode", newMode);

  if (newMode === "percent" && props.value > 100) {
    emit("update:value", 100);
  }
}

function updateValue(event: Event): void {
  const input = event.target as HTMLInputElement;

  if (props.mode === "percent") {
    // Hanya angka dan satu pemisah desimal (titik diubah jadi koma), maksimal 2 desimal.
    let text = input.value.replace(/[^0-9.,]/g, "").replace(/\./g, ",");
    let [whole, ...rest] = text.split(",");

    whole = whole.replace(/^0+(?=\d)/, "");
    if (whole === "" && rest.length) whole = "0";

    text = rest.length ? `${whole},${rest.join("").slice(0, 2)}` : whole;

    let numeric = parsePercent(text);

    if (numeric > 100) {
      numeric = 100;
      text = "100";
    }

    percentText.value = text;
    input.value = text;
    emit("update:value", numeric);
    return;
  }

  const digits = input.value.replace(/[^0-9]/g, "");
  const numeric = digits ? parseInt(digits, 10) : 0;

  emit("update:value", numeric);
  input.value = numeric === 0 ? "" : formatNumber(numeric);
}
</script>

<template>
  <div class="pos-tax-input">
    <div class="input-group input-group-sm">
      <select class="form-select" :value="mode" :disabled="disabled" aria-label="Mode perhitungan pajak" @change="handleModeChange">
        <option value="percent">Persen (%)</option>
        <option value="amount">Nominal (Rp)</option>
      </select>

      <input
        type="text"
        class="form-control"
        :inputmode="mode === 'percent' ? 'decimal' : 'numeric'"
        aria-label="Nilai pajak"
        :value="shownValue"
        :disabled="disabled"
        :placeholder="mode === 'percent' ? '0 - 100' : '0'"
        @input="updateValue"
      />
    </div>

    <div v-if="showPreview" class="pos-tax-preview">
      <span>Nilai pajak</span>
      <strong>{{ formatCurrency(previewAmount) }}</strong>
    </div>
  </div>
</template>
