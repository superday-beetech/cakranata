<script setup lang="ts">
import { formatCurrency } from "../../utils/format";
import type { DiscountMode } from "../../types/sales";
import { calculateDiscount } from "../../utils/posMath";
import CurrencyInput from "@/components/ui/CurrencyInput.vue";

const props = defineProps<{
  mode: DiscountMode;
  value: number;
  baseAmount: number;
  disabled?: boolean;
}>();

const emit = defineEmits<{
  "update:mode": [value: DiscountMode];
  "update:value": [value: number];
}>();

function previewAmount(): number {
  return calculateDiscount(Math.max(0, props.baseAmount), props.mode, props.value);
}

function updateValue(value: number): void {
  const normalized = Math.max(0, value);
  emit("update:value", props.mode === "percent" ? Math.min(100, normalized) : normalized);
}
</script>

<template>
  <div class="pos-inline-field">
    <label class="form-label mb-0" for="pos-transaction-discount"> Diskon transaksi </label>

    <div class="input-group">
      <div class="input-group">
        <select class="form-select" :value="mode" :disabled="disabled" aria-label="Jenis diskon transaksi" @change="emit('update:mode', ($event.target as HTMLSelectElement).value as DiscountMode)">
          <option value="percent">%</option>
          <option value="amount">Rp</option>
        </select>

        <CurrencyInput
          id="pos-transaction-discount"
          :model-value="value"
          :disabled="disabled"
          :prefix="mode === 'amount' ? 'Rp' : ''"
          :max="mode === 'percent' ? 100 : undefined"
          :decimal="true"
          @update:model-value="emit('update:value', $event)"
        />
      </div>
    </div>

    <small>
      Dari dasar {{ formatCurrency(baseAmount) }}, potongan
      <strong>{{ formatCurrency(previewAmount()) }}</strong>
    </small>
  </div>
</template>
