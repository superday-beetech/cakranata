<script setup lang="ts">
import { formatCurrency, formatDateTime } from "../../utils/format";
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

</script>

<template>
  <div>
    <div class="pos-held-bar">
      <small>{{ transactions.length }} transaksi tertahan</small>

      <button type="button" class="btn btn-soft-secondary btn-sm" :disabled="loading" @click="emit('refresh')">
        <i class="ti ti-refresh"></i>
        Muat ulang
      </button>
    </div>

    <small v-if="loading" class="pos-muted-center d-block">Memuat transaksi tertahan...</small>

    <small v-else-if="!transactions.length" class="pos-muted-center d-block">Belum ada transaksi tertahan.</small>

    <div v-else class="pos-held-list">
      <article v-for="sale in transactions" :key="sale.id" class="pos-held-item">
        <div class="pos-held-info">
          <strong>{{ sale.sale_number }}</strong>
          <small>{{ sale.customer_name || "Pelanggan umum" }}</small>
          <small>{{ formatDateTime(sale.sale_date) }}</small>
        </div>

        <div class="pos-held-action">
          <strong>{{ formatCurrency(sale.total_amount) }}</strong>

          <button type="button" class="btn btn-soft-primary btn-sm" :disabled="disabled" @click="emit('resume', sale)">
            <i class="ti ti-player-play"></i>
            Lanjutkan
          </button>
        </div>
      </article>
    </div>
  </div>
</template>
