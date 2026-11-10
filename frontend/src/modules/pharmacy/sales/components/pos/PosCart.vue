<script setup lang="ts">
import PosCartItem from "./PosCartItem.vue";

import type { PosCartItem as CartItem } from "../../types/sales";

defineProps<{
  items: CartItem[];
  subtotal: number;
  disabled?: boolean;
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

function currency(value: number): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(value);
}
</script>

<template>
  <section class="pos-card pos-cart-card">
    <div class="pos-card-header">
      <div>
        <h5>Keranjang Penjualan</h5>
        <p>{{ items.length }} jenis produk dalam transaksi</p>
      </div>

      <button type="button" class="pos-button pos-button-danger-light" :disabled="!items.length || disabled" @click="emit('clear')">
        <i class="ti ti-trash"></i>
        Kosongkan
      </button>
    </div>

    <div v-if="!items.length" class="pos-empty">
      <i class="ti ti-shopping-cart"></i>
      <strong>Keranjang masih kosong</strong>
      <span>Scan barcode atau cari produk untuk memulai transaksi.</span>
    </div>

    <div v-else class="pos-table-wrapper">
      <table class="pos-table">
        <thead>
          <tr>
            <th class="col-no">No</th>
            <th>Barcode</th>
            <th>Nama Barang</th>
            <th>Satuan</th>
            <th class="text-end">Qty</th>
            <th class="text-end">Harga</th>
            <th>Disc Jns</th>
            <th class="text-end">Disc Total</th>
            <th>Catatan</th>
            <th></th>
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
    </div>

    <div v-if="items.length" class="pos-cart-footer">
      <span
        >Total item: <strong>{{ items.length }}</strong></span
      >
      <span
        >Subtotal: <strong>{{ currency(subtotal) }}</strong></span
      >
    </div>
  </section>
</template>

<style>
.pos-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 11px;
  margin-bottom: 14px;
}

.pos-cart-card {
  padding: 0;
  overflow: hidden;
}

.pos-card-header {
  align-items: center;
  display: flex;
  justify-content: space-between;
  margin-bottom: 14px;
  padding: 16px 16px 0;
}

.pos-card-header h5 {
  font-size: 14px;
  font-weight: 750;
  margin: 0 0 3px;
}

.pos-card-header p {
  color: #6b7280;
  font-size: 11px;
  margin: 0;
}

.pos-button {
  align-items: center;
  border: 1px solid transparent;
  border-radius: 7px;
  display: inline-flex;
  font-size: 12px;
  font-weight: 650;
  gap: 7px;
  min-height: 34px;
  padding: 7px 11px;
}

.pos-button-danger-light {
  background: #fef2f2;
  border-color: #fecaca;
  color: #dc2626;
}

.pos-table-wrapper {
  max-height: 430px;
  overflow: auto;
  width: 100%;
}

.pos-table {
  border-collapse: separate;
  border-spacing: 0;
  font-size: 11px;
  min-width: 1050px;
  width: 100%;
}

.pos-table th {
  background: #f8fafc;
  border-bottom: 1px solid #e5e7eb;
  color: #64748b;
  font-size: 10px;
  font-weight: 750;
  padding: 10px 8px;
  position: sticky;
  text-align: left;
  text-transform: uppercase;
  top: 0;
  white-space: nowrap;
  z-index: 2;
}

.pos-table td {
  border-bottom: 1px solid #f1f5f9;
  padding: 8px;
  vertical-align: middle;
}

.pos-empty {
  align-items: center;
  color: #6b7280;
  display: flex;
  flex-direction: column;
  gap: 7px;
  justify-content: center;
  min-height: 180px;
  padding: 20px;
  text-align: center;
}

.pos-empty > i {
  color: #cbd5e1;
  font-size: 35px;
}

.pos-empty strong {
  color: #475569;
  font-size: 13px;
}

.pos-empty span {
  font-size: 11px;
}

.pos-cart-footer {
  align-items: center;
  background: #f8fafc;
  color: #6b7280;
  display: flex;
  font-size: 11px;
  justify-content: space-between;
  padding: 11px 15px;
}

.pos-cart-footer strong {
  color: #111827;
}
</style>
