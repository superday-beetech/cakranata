<script setup lang="ts">
import { formatCurrency } from "../../utils/format";
import PosPanel from "./PosPanel.vue";
import PosCartItem from "./PosCartItem.vue";

import type { PosCartItem as CartItem, DiscountMode } from "../../types/sales";

defineProps<{
  items: CartItem[];
  subtotal: number;
  disabled?: boolean;
  shortcut?: string;
}>();

const emit = defineEmits<{
  quantityChange: [productId: number, value: number];
  priceChange: [productId: number, value: number];
  discountModeChange: [productId: number, value: DiscountMode];
  discountChange: [productId: number, value: number];
  noteChange: [productId: number, value: string];
  remove: [productId: number];
  clear: [];
}>();

</script>

<template>
  <PosPanel title="Keranjang" :subtitle="`${items.length} jenis produk dalam transaksi`" icon="ti ti-shopping-cart" tone="info" :shortcut="shortcut" flush scroll>
    <template #actions>
      <span class="pos-count">{{ items.length }} item</span>

      <button type="button" class="btn btn-soft-danger btn-sm" :disabled="!items.length || disabled" @click="emit('clear')">
        <i class="ti ti-trash"></i>
        Kosongkan
      </button>
    </template>

    <div v-if="!items.length" class="pos-empty">
      <i class="ti ti-shopping-cart"></i>
      <strong>Keranjang masih kosong</strong>
      <span>Scan barcode atau cari produk untuk memulai transaksi.</span>
    </div>

    <table v-else class="pos-table">
      <thead>
        <tr>
          <th class="col-no">No</th>
          <th>Produk</th>
          <th class="col-qty">Qty</th>
          <th class="col-price">Harga</th>
          <th class="col-disc">Diskon</th>
          <th class="col-amount">Potongan</th>
          <th class="col-act"><span class="visually-hidden">Aksi</span></th>
        </tr>
      </thead>

      <tbody>
        <PosCartItem
          v-for="(item, index) in items"
          :key="item.product_id"
          :item="item"
          :index="index"
          :discount-mode="item.discount_mode ?? 'amount'"
          :discount-input="item.discount_value ?? item.discount_amount"
          :note="item.notes ?? ''"
          :disabled="disabled"
          @quantity-change="emit('quantityChange', item.product_id, $event)"
          @price-change="emit('priceChange', item.product_id, $event)"
          @discount-mode-change="emit('discountModeChange', item.product_id, $event)"
          @discount-change="emit('discountChange', item.product_id, $event)"
          @note-change="emit('noteChange', item.product_id, $event)"
          @remove="emit('remove', item.product_id)"
        />
      </tbody>
    </table>

    <template #footer>
      <span>
        Total item: <strong>{{ items.length }}</strong>
      </span>
      <span>
        Subtotal: <strong>{{ formatCurrency(subtotal) }}</strong>
      </span>
    </template>
  </PosPanel>
</template>
