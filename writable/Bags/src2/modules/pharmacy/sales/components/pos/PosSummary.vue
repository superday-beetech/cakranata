<script setup lang="ts">
defineProps<{
  subtotal: number;
  itemDiscount: number;
  transactionDiscount: number;
  dpp: number;
  tax: number;
  additionalCharges: number;
  total: number;
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
  <section class="pos-summary">
    <div class="pos-summary-row">
      <span>Subtotal Produk</span>
      <strong>{{ currency(subtotal) }}</strong>
    </div>

    <div class="pos-summary-row discount">
      <span>Diskon Item</span>
      <strong>- {{ currency(itemDiscount) }}</strong>
    </div>

    <div class="pos-summary-row discount">
      <span>Diskon Transaksi</span>
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
      <span>Biaya Tambahan</span>
      <strong>{{ currency(additionalCharges) }}</strong>
    </div>

    <div class="pos-summary-total">
      <span>Total Tagihan</span>
      <strong>{{ currency(total) }}</strong>
    </div>
  </section>
</template>

<style>
.pos-summary {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.pos-summary-row,
.pos-summary-total {
  align-items: center;
  display: flex;
  font-size: 11px;
  justify-content: space-between;
  gap: 12px;
}

.pos-summary-row {
  color: #64748b;
}

.pos-summary-row strong {
  color: #334155;
  font-size: 11px;
  font-weight: 650;
  white-space: nowrap;
}

.pos-summary-row.discount strong {
  color: #dc2626;
}

.pos-summary-total {
  border-top: 1px solid #e5e7eb;
  color: #111827;
  font-size: 13px;
  font-weight: 750;
  margin-top: 3px;
  padding-top: 12px;
}

.pos-summary-total strong {
  color: #2563eb;
  font-size: 16px;
  white-space: nowrap;
}
</style>
