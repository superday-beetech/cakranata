<script setup lang="ts">
defineProps<{
  saleNumber?: string | null;
  status?: string;
  processing?: boolean;
}>();

const emit = defineEmits<{
  newTransaction: [];
  holdTransaction: [];
  refresh: [];
}>();
</script>

<template>
  <header class="pos-header">
    <div class="pos-header-title">
      <div class="pos-header-icon">
        <i class="ti ti-cash-register"></i>
      </div>

      <div>
        <h4>Point of Sale</h4>
        <div class="pos-header-meta">
          <span>Kasir Penjualan</span>
          <span v-if="saleNumber">· {{ saleNumber }}</span>
          <span v-if="status" class="pos-status">{{ status }}</span>
        </div>
      </div>
    </div>

    <div class="pos-header-actions">
      <button type="button" class="pos-header-button" :disabled="processing" @click="emit('refresh')">
        <i class="ti ti-refresh"></i>
        <span>Muat Ulang</span>
      </button>

      <button type="button" class="pos-header-button" :disabled="processing" @click="emit('holdTransaction')">
        <i class="ti ti-player-pause"></i>
        <span>Tahan</span>
        <kbd>F10</kbd>
      </button>

      <button type="button" class="pos-header-button primary" :disabled="processing" @click="emit('newTransaction')">
        <i class="ti ti-plus"></i>
        <span>Transaksi Baru</span>
        <kbd>F11</kbd>
      </button>
    </div>
  </header>
</template>

<style>
.pos-header {
  align-items: center;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 11px;
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  justify-content: space-between;
  margin-bottom: 16px;
  padding: 14px 16px;
}

.pos-header-title,
.pos-header-actions {
  align-items: center;
  display: flex;
  gap: 11px;
}

.pos-header-icon {
  align-items: center;
  background: #eff6ff;
  border-radius: 9px;
  color: #2563eb;
  display: flex;
  font-size: 23px;
  height: 43px;
  justify-content: center;
  width: 43px;
}

.pos-header-title h4 {
  color: #1e293b;
  font-size: 16px;
  font-weight: 800;
  margin: 0 0 4px;
}

.pos-header-meta {
  align-items: center;
  color: #94a3b8;
  display: flex;
  flex-wrap: wrap;
  font-size: 10px;
  gap: 5px;
}

.pos-status {
  background: #f1f5f9;
  border-radius: 4px;
  color: #475569;
  font-weight: 700;
  padding: 3px 6px;
}

.pos-header-button {
  align-items: center;
  background: white;
  border: 1px solid #dbe1e8;
  border-radius: 7px;
  color: #475569;
  display: inline-flex;
  font-size: 11px;
  font-weight: 650;
  gap: 6px;
  min-height: 35px;
  padding: 7px 10px;
}

.pos-header-button.primary {
  background: #2563eb;
  border-color: #2563eb;
  color: white;
}

.pos-header-button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

kbd {
  background: #f1f5f9;
  border: 1px solid #dbe1e8;
  border-radius: 3px;
  font-family: inherit;
  font-size: 9px;
  padding: 2px 4px;
}

.primary kbd {
  background: #1d4ed8;
  border-color: #60a5fa;
  color: white;
}

@media (max-width: 650px) {
  .pos-header {
    align-items: stretch;
    flex-direction: column;
  }

  .pos-header-actions {
    flex-wrap: wrap;
  }
}
</style>
