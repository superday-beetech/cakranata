<script setup lang="ts">
type DiscountMode = "amount" | "percent";

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

function previewAmount(): number {
  const base = Math.max(0, props.baseAmount);
  const value = Math.max(0, props.value);

  return props.mode === "percent" ? Math.round((base * Math.min(value, 100)) / 100) : Math.min(value, base);
}
</script>

<template>
  <div class="pos-inline-field">
    <label class="form-label mb-0" for="pos-transaction-discount">Diskon transaksi</label>

    <div class="input-group">
      <select class="form-select" :value="mode" :disabled="disabled" aria-label="Jenis diskon transaksi" @change="emit('update:mode', ($event.target as HTMLSelectElement).value as DiscountMode)">
        <option value="percent">%</option>
        <option value="amount">Rp</option>
      </select>

      <input id="pos-transaction-discount" type="number" class="form-control" min="0" :max="mode === 'percent' ? 100 : undefined" step="0.01" :value="value" :disabled="disabled" placeholder="0" @input="updateValue" />
    </div>

    <small>
      Dari dasar {{ currency(baseAmount) }}, potongan
      <strong>{{ currency(previewAmount()) }}</strong>
    </small>
  </div>
</template>
