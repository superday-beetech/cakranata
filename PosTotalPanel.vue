<script setup lang="ts">
defineProps<{
  total: number;
  paid: number;
  change: number;
  remaining: number;
  settlementLabel?: string;
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
  <section class="pos-total" aria-label="Total pembayaran">
    <div class="pos-total-top">
      <span>Total tagihan</span>
      <small>{{ settlementLabel || "Transaksi berjalan" }}</small>
    </div>

    <output class="pos-total-amount">{{ currency(total) }}</output>

    <div class="pos-total-stats">
      <div class="pos-total-stat">
        <small>Dibayar</small>
        <strong>{{ currency(paid) }}</strong>
      </div>

      <div class="pos-total-stat is-change">
        <small>Kembalian</small>
        <strong>{{ currency(change) }}</strong>
      </div>

      <div class="pos-total-stat is-remaining">
        <small>Sisa tagihan</small>
        <strong>{{ currency(remaining) }}</strong>
      </div>
    </div>
  </section>
</template>
