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
  <section class="pos-total-panel">
    <div class="pos-total-heading">
      <span>Total Pembayaran</span>
      <small>{{ settlementLabel || "Transaksi berjalan" }}</small>
    </div>

    <div class="pos-grand-total">
      {{ currency(total) }}
    </div>

    <div class="pos-total-divider"></div>

    <div class="pos-total-detail">
      <span>Dibayar</span>
      <strong>{{ currency(paid) }}</strong>
    </div>

    <div class="pos-total-detail">
      <span>Kembalian</span>
      <strong class="success">{{ currency(change) }}</strong>
    </div>

    <div class="pos-total-detail remaining">
      <span>Sisa Tagihan</span>
      <strong>{{ currency(remaining) }}</strong>
    </div>
  </section>
</template>

<style scoped>
.pos-total-panel {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 10px;
  padding: 16px;
}

.pos-total-heading {
  align-items: center;
  color: #475569;
  display: flex;
  font-size: 11px;
  font-weight: 700;
  justify-content: space-between;
}

.pos-total-heading small {
  color: #64748b;
  font-size: 10px;
  font-weight: 500;
}

.pos-grand-total {
  color: #1d4ed8;
  font-size: clamp(23px, 3vw, 31px);
  font-weight: 850;
  letter-spacing: -1px;
  margin-top: 8px;
  overflow-wrap: anywhere;
}

.pos-total-divider {
  border-top: 1px dashed #bfdbfe;
  margin: 13px 0;
}

.pos-total-detail {
  align-items: center;
  color: #64748b;
  display: flex;
  font-size: 11px;
  justify-content: space-between;
  margin-top: 9px;
}

.pos-total-detail strong {
  color: #334155;
  font-size: 12px;
  font-weight: 750;
}

.pos-total-detail strong.success {
  color: #15803d;
}

.pos-total-detail.remaining {
  border-top: 1px solid #dbeafe;
  color: #1e40af;
  font-weight: 750;
  margin-top: 12px;
  padding-top: 11px;
}

.pos-total-detail.remaining strong {
  color: #1d4ed8;
}
</style>
