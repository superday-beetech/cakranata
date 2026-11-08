<script setup lang="ts">
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

function currency(value: number): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(value);
}
</script>

<template>
  <div class="pos-summary">
    <div class="pos-summary-row">
      <span>Subtotal produk</span>
      <strong>{{ currency(subtotal) }}</strong>
    </div>

    <div class="pos-summary-row is-discount">
      <span>Diskon item</span>
      <strong>- {{ currency(itemDiscount) }}</strong>
    </div>

    <div class="pos-summary-row is-discount">
      <span>Diskon transaksi</span>
      <strong>- {{ currency(transactionDiscount) }}</strong>
    </div>

    <div class="pos-summary-row">
      <span>DPP</span>
      <strong>{{ currency(dpp) }}</strong>
    </div>

    <div class="pos-summary-row">
      <span>Pajak</span>
      <strong>{{ currency(tax) }}</strong>
    </div>

    <div class="pos-summary-row">
      <span>Biaya tambahan</span>
      <strong>{{ currency(additionalCharges) }}</strong>
    </div>

    <div v-if="showTotal" class="pos-summary-total">
      <span>Total tagihan</span>
      <strong>{{ currency(total) }}</strong>
    </div>
  </div>
</template>
