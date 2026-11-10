<script setup lang="ts">
import type { PosCartItem } from "../../types/sales";

type DiscountMode = "amount" | "percent";

defineProps<{
  item: PosCartItem;
  index: number;
  discountMode: DiscountMode;
  discountInput: number;
  note: string;
  disabled?: boolean;
}>();

const emit = defineEmits<{
  quantityChange: [value: number];
  priceChange: [value: number];
  discountModeChange: [value: DiscountMode];
  discountChange: [value: number];
  noteChange: [value: string];
  remove: [];
}>();

function numberValue(event: Event): number {
  const value = Number((event.target as HTMLInputElement).value);
  return Number.isFinite(value) ? value : 0;
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
  <tr>
    <td class="col-no">{{ index + 1 }}</td>

    <td class="pos-barcode-cell">
      {{ item.barcode || "-" }}
    </td>

    <td class="pos-item-name">
      <strong>{{ item.product_name }}</strong>
      <small>{{ item.sku }}</small>
    </td>

    <td>{{ item.unit_name || "-" }}</td>

    <td>
      <input type="number" min="0.001" step="0.001" class="pos-table-input input-number" :value="item.quantity" :disabled="disabled" @change="emit('quantityChange', numberValue($event))" />
    </td>

    <td>
      <input type="number" min="0" step="1" class="pos-table-input input-number" :value="item.unit_price" :disabled="disabled" @change="emit('priceChange', numberValue($event))" />
    </td>

    <td>
      <select class="pos-table-select" :value="discountMode" :disabled="disabled" @change="emit('discountModeChange', ($event.target as HTMLSelectElement).value as DiscountMode)">
        <option value="amount">Rp</option>
        <option value="percent">%</option>
      </select>

      <input type="number" min="0" step="0.01" class="pos-table-input input-number mt-1" :value="discountInput" :disabled="disabled" @change="emit('discountChange', numberValue($event))" />
    </td>

    <td class="text-end pos-number">
      {{ currency(item.discount_amount) }}
    </td>

    <td>
      <input type="text" class="pos-table-input" placeholder="Catatan..." :value="note" :disabled="disabled" @input="emit('noteChange', ($event.target as HTMLInputElement).value)" />
    </td>

    <td>
      <button type="button" class="pos-remove-button" title="Hapus produk" :disabled="disabled" @click="emit('remove')">
        <i class="ti ti-trash"></i>
      </button>
    </td>
  </tr>
</template>

<style>
.pos-table-input,
.pos-table-select {
  background: white;
  border: 1px solid #dbe1e8;
  border-radius: 5px;
  color: #1f2937;
  font-size: 11px;
  min-height: 30px;
  padding: 5px 7px;
  width: 100%;
}

.pos-table-input:focus,
.pos-table-select:focus {
  border-color: #2563eb;
  outline: none;
}

.pos-table-input.input-number {
  min-width: 68px;
  text-align: right;
}

.pos-table-select {
  min-width: 65px;
}

.col-no {
  color: #6b7280;
  text-align: center;
  width: 35px;
}

.pos-barcode-cell {
  color: #64748b;
  font-size: 10px;
  white-space: nowrap;
}

.pos-item-name {
  min-width: 150px;
}

.pos-item-name strong,
.pos-item-name small {
  display: block;
}

.pos-item-name strong {
  font-size: 11px;
}

.pos-item-name small {
  color: #6b7280;
  font-size: 10px;
  margin-top: 2px;
}

.pos-remove-button {
  align-items: center;
  background: transparent;
  border: 0;
  color: #ef4444;
  display: inline-flex;
  font-size: 15px;
  justify-content: center;
  padding: 5px;
}
</style>
