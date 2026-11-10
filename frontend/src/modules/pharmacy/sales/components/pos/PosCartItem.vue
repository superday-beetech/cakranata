<script setup lang="ts">
import { ref, watch } from "vue";
import { formatCurrency } from "../../utils/format";
import type { DiscountMode, PosCartItem } from "../../types/sales";

const props = defineProps<{
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

// Catatan item disembunyikan sampai dibutuhkan agar baris keranjang tetap ringkas.
const noteOpen = ref(Boolean(props.note));

watch(
  () => props.note,
  (value) => {
    if (value) noteOpen.value = true;
  },
);

// Tombol +/- mengubah 1 satuan; pembulatan 3 desimal menghindari 0.1 + 0.2 = 0.30000000000000004.
function stepQuantity(delta: number): void {
  const next = Math.round((Number(props.item.quantity) + delta) * 1000) / 1000;

  if (next < 0.001) return;

  emit("quantityChange", next);
}

function numberValue(event: Event): number {
  const value = Number((event.target as HTMLInputElement).value);
  return Number.isFinite(value) ? value : 0;
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

        <input v-if="noteOpen" type="text" class="form-control pos-item-note" placeholder="Catatan item" aria-label="Catatan item" :value="note" :disabled="disabled" @input="emit('noteChange', ($event.target as HTMLInputElement).value)" />
      </div>
    </td>

    <td class="col-qty">
      <div class="input-group input-group-sm pos-qty">
        <button type="button" class="btn btn-soft-secondary" aria-label="Kurangi jumlah" :disabled="disabled || item.quantity <= 1" @click="stepQuantity(-1)">
          <i class="ti ti-minus"></i>
        </button>

        <input
          type="number"
          min="0.001"
          step="0.001"
          class="form-control"
          aria-label="Jumlah"
          :value="item.quantity"
          :disabled="disabled"
          @focus="($event.target as HTMLInputElement).select()"
          @change="emit('quantityChange', numberValue($event))"
        />

        <button type="button" class="btn btn-soft-secondary" aria-label="Tambah jumlah" :disabled="disabled" @click="stepQuantity(1)">
          <i class="ti ti-plus"></i>
        </button>
      </div>
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
      <span :class="{ 'pos-minus': item.discount_amount > 0 }">{{ item.discount_amount > 0 ? "- " : "" }}{{ formatCurrency(item.discount_amount) }}</span>
    </td>

    <td class="col-act">
      <div class="pos-row-actions">
        <button type="button" class="btn btn-icon btn-sm" :class="note ? 'btn-soft-primary' : 'btn-soft-secondary'" title="Catatan item" aria-label="Catatan item" :aria-pressed="noteOpen" @click="noteOpen = !noteOpen">
          <i class="ti ti-notes"></i>
        </button>

        <button type="button" class="btn btn-soft-danger btn-icon btn-sm" title="Hapus produk" aria-label="Hapus produk" :disabled="disabled" @click="emit('remove')">
          <i class="ti ti-trash"></i>
        </button>
      </div>
    </td>
  </tr>
</template>
