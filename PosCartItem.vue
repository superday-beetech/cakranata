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

    <td>
      <div class="pos-item-name">
        <strong>{{ item.product_name }}</strong>
        <small>
          {{ item.sku || "-" }}
          <template v-if="item.barcode"> · {{ item.barcode }}</template>
          <template v-if="item.unit_name"> · {{ item.unit_name }}</template>
        </small>
      </div>
    </td>

    <td class="col-qty">
      <input type="number" min="0.001" step="0.001" class="form-control text-end-input" aria-label="Jumlah" :value="item.quantity" :disabled="disabled" @change="emit('quantityChange', numberValue($event))" />
    </td>

    <td class="col-price">
      <input type="number" min="0" step="1" class="form-control text-end-input" aria-label="Harga satuan" :value="item.unit_price" :disabled="disabled" @change="emit('priceChange', numberValue($event))" />
    </td>

    <td class="col-disc">
      <div class="input-group input-group-sm">
        <select class="form-select" aria-label="Jenis diskon" :value="discountMode" :disabled="disabled" @change="emit('discountModeChange', ($event.target as HTMLSelectElement).value as DiscountMode)">
          <option value="amount">Rp</option>
          <option value="percent">%</option>
        </select>

        <input type="number" min="0" step="0.01" class="form-control text-end-input" aria-label="Nilai diskon" :value="discountInput" :disabled="disabled" @change="emit('discountChange', numberValue($event))" />
      </div>
    </td>

    <td class="col-amount">
      <span :class="{ 'pos-minus': item.discount_amount > 0 }">{{ item.discount_amount > 0 ? "- " : "" }}{{ currency(item.discount_amount) }}</span>
    </td>

    <td class="col-note">
      <input type="text" class="form-control" placeholder="Catatan" aria-label="Catatan item" :value="note" :disabled="disabled" @input="emit('noteChange', ($event.target as HTMLInputElement).value)" />
    </td>

    <td class="col-act">
      <button type="button" class="btn btn-soft-danger btn-icon btn-sm" title="Hapus produk" aria-label="Hapus produk" :disabled="disabled" @click="emit('remove')">
        <i class="ti ti-trash"></i>
      </button>
    </td>
  </tr>
</template>
