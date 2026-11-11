<script setup lang="ts">
import { formatCurrency } from "../../utils/format";

defineProps<{
  total: number;
  paid: number;
  change: number;
  remaining: number;
  settlementLabel?: string;
}>();
</script>

<template>
  <section class="pos-total" aria-label="Total pembayaran">
    <div class="pos-total-main">
      <small>Total tagihan</small>
      <output class="pos-total-amount">{{ formatCurrency(total) }}</output>
      <small class="pos-total-mode">{{ settlementLabel || "Transaksi berjalan" }}</small>
    </div>

    <dl class="pos-total-stats">
      <div>
        <dt>Dibayar</dt>
        <dd>{{ formatCurrency(paid) }}</dd>
      </div>

      <div class="is-change">
        <dt>Kembalian</dt>
        <dd>{{ formatCurrency(change) }}</dd>
      </div>

      <div class="is-remaining">
        <dt>Sisa</dt>
        <dd>{{ formatCurrency(remaining) }}</dd>
      </div>
    </dl>
  </section>
</template>
