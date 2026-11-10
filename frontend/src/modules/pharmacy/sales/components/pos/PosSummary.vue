<script setup lang="ts">
import { formatCurrency } from "../../utils/format";
withDefaults(
  defineProps<{
    subtotal: number;
    itemDiscount: number;
    transactionDiscount: number;
    dpp: number;
    tax: number;
    additionalCharges: number;
    total: number;
    /** Sembunyikan baris total bila total sudah tampil di panel lain. */
    showTotal?: boolean;
  }>(),
  { showTotal: true },
);

</script>

<template>
  <div class="pos-summary">
    <div class="pos-summary-row">
      <span>Subtotal produk</span>
      <strong>{{ formatCurrency(subtotal) }}</strong>
    </div>

    <div class="pos-summary-row" :class="{ 'is-discount': itemDiscount > 0 }">
      <span>Diskon item</span>
      <strong>- {{ formatCurrency(itemDiscount) }}</strong>
    </div>

    <div class="pos-summary-row" :class="{ 'is-discount': transactionDiscount > 0 }">
      <span>Diskon transaksi</span>
      <strong>- {{ formatCurrency(transactionDiscount) }}</strong>
    </div>

    <div class="pos-summary-row">
      <span>DPP</span>
      <strong>{{ formatCurrency(dpp) }}</strong>
    </div>

    <div class="pos-summary-row">
      <span>Pajak</span>
      <strong>{{ formatCurrency(tax) }}</strong>
    </div>

    <div class="pos-summary-row">
      <span>Biaya tambahan</span>
      <strong>{{ formatCurrency(additionalCharges) }}</strong>
    </div>

    <div v-if="showTotal" class="pos-summary-total">
      <span>Total tagihan</span>
      <strong>{{ formatCurrency(total) }}</strong>
    </div>
  </div>
</template>
