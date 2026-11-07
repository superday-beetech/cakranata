<script setup lang="ts">
import type { Sale } from "../../types/sales";

defineProps<{
  transactions: Sale[];
  loading?: boolean;
  disabled?: boolean;
}>();

const emit = defineEmits<{
  refresh: [];
  resume: [sale: Sale];
}>();

function currency(value: string | number): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(Number(value) || 0);
}

function dateTime(value: string): string {
  if (!value) return "-";

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat("id-ID", {
    dateStyle: "short",
    timeStyle: "short",
  }).format(date);
}
</script>

<template>
  <section class="pos-held-card">
    <div class="pos-held-heading">
      <div>
        <h5>
          <i class="ti ti-player-pause"></i>
          Transaksi Tertahan
        </h5>
        <small>{{ transactions.length }} transaksi</small>
      </div>

      <button type="button" class="pos-held-refresh" :disabled="loading" @click="emit('refresh')">
        <i class="ti ti-refresh"></i>
      </button>
    </div>

    <div v-if="loading" class="pos-held-empty">Memuat transaksi tertahan...</div>

    <div v-else-if="!transactions.length" class="pos-held-empty">Belum ada transaksi tertahan.</div>

    <div v-else class="pos-held-list">
      <article v-for="sale in transactions" :key="sale.id" class="pos-held-item">
        <div class="pos-held-info">
          <strong>{{ sale.sale_number }}</strong>
          <small>{{ sale.customer_name || "Pelanggan umum" }}</small>
          <small>{{ dateTime(sale.sale_date) }}</small>
        </div>

        <div class="pos-held-action">
          <strong>{{ currency(sale.total_amount) }}</strong>

          <button type="button" :disabled="disabled" @click="emit('resume', sale)">
            <i class="ti ti-player-play"></i>
            Lanjutkan
          </button>
        </div>
      </article>
    </div>
  </section>
</template>

<style scoped>
.pos-held-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px;
}

.pos-held-heading {
  align-items: center;
  display: flex;
  justify-content: space-between;
  margin-bottom: 12px;
}

.pos-held-heading h5 {
  align-items: center;
  color: #334155;
  display: flex;
  font-size: 12px;
  font-weight: 750;
  gap: 7px;
  margin: 0 0 3px;
}

.pos-held-heading h5 i {
  color: #d97706;
  font-size: 16px;
}

.pos-held-heading small {
  color: #94a3b8;
  font-size: 10px;
}

.pos-held-refresh {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  color: #64748b;
  padding: 6px 8px;
}

.pos-held-empty {
  color: #94a3b8;
  font-size: 11px;
  padding: 17px 5px;
  text-align: center;
}

.pos-held-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 260px;
  overflow-y: auto;
}

.pos-held-item {
  align-items: center;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 7px;
  display: flex;
  gap: 10px;
  justify-content: space-between;
  padding: 10px;
}

.pos-held-info,
.pos-held-action {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.pos-held-info strong {
  color: #334155;
  font-size: 11px;
}

.pos-held-info small {
  color: #94a3b8;
  font-size: 10px;
}

.pos-held-action {
  align-items: flex-end;
  flex-shrink: 0;
}

.pos-held-action > strong {
  color: #1e293b;
  font-size: 11px;
}

.pos-held-action button {
  align-items: center;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 5px;
  color: #1d4ed8;
  display: inline-flex;
  font-size: 10px;
  font-weight: 700;
  gap: 4px;
  padding: 5px 7px;
}

.pos-held-action button:disabled {
  opacity: 0.5;
}
</style>
