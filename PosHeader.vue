<script setup lang="ts">
import { computed } from "vue";

const props = withDefaults(
  defineProps<{
    saleNumber?: string | null;
    status?: string;
    processing?: boolean;
    loadingHeld?: boolean;
    /** Tombol "Tahan" aktif hanya saat transaksi berstatus draft. */
    canHold?: boolean;
    /** Tombol "Transaksi Baru" aktif setelah transaksi diposting. */
    canNew?: boolean;
    clockTime?: string;
    greeting?: string;
    soundEnabled?: boolean;
  }>(),
  { canHold: true, canNew: true, soundEnabled: true },
);

const emit = defineEmits<{
  newTransaction: [];
  holdTransaction: [];
  refresh: [];
  back: [];
  toggleSound: [];
  toggleShortcuts: [];
}>();

const statusClass = computed(() => {
  if (props.status === "Selesai") return "is-done";
  if (props.status === "Draft") return "is-draft";
  return "is-new";
});
</script>

<template>
  <header class="pos-header">
    <div class="pos-header-brand">
      <button type="button" class="btn btn-soft-secondary btn-icon" title="Kembali ke dashboard" aria-label="Kembali ke dashboard" @click="emit('back')">
        <i class="ti ti-arrow-left"></i>
      </button>

      <span class="pos-logo" aria-hidden="true"><i class="ti ti-cash-register"></i></span>

      <div class="pos-header-title">
        <h1>Point of Sale</h1>
        <small>ACN Pharmacy</small>
      </div>
    </div>

    <div class="pos-header-meta">
      <div class="pos-header-trx">
        <small>Transaksi</small>
        <strong>{{ saleNumber || "Transaksi baru" }}</strong>
        <span v-if="status" class="pos-chip" :class="statusClass">{{ status }}</span>
      </div>
    </div>

    <div class="pos-header-actions">
      <div v-if="clockTime" class="pos-clock">
        <strong>{{ clockTime }}</strong>
        <small v-if="greeting">{{ greeting }}</small>
      </div>

      <span class="pos-header-divider" aria-hidden="true"></span>

      <button type="button" class="btn btn-soft-secondary btn-icon" title="Bantuan shortcut" aria-label="Bantuan shortcut" @click="emit('toggleShortcuts')">
        <i class="ti ti-keyboard"></i>
      </button>

      <button type="button" class="btn btn-soft-secondary btn-icon" title="Muat ulang transaksi tertahan" aria-label="Muat ulang transaksi tertahan" :disabled="loadingHeld" @click="emit('refresh')">
        <i class="ti ti-refresh"></i>
      </button>

      <button
        type="button"
        class="btn btn-soft-secondary btn-icon"
        :title="soundEnabled ? 'Matikan suara' : 'Aktifkan suara'"
        :aria-label="soundEnabled ? 'Matikan suara' : 'Aktifkan suara'"
        :aria-pressed="soundEnabled"
        @click="emit('toggleSound')"
      >
        <i :class="soundEnabled ? 'ti ti-volume' : 'ti ti-volume-off'"></i>
      </button>

      <button type="button" class="btn btn-soft-primary" :disabled="processing || !canHold" @click="emit('holdTransaction')">
        <i class="ti ti-player-pause"></i>
        <span class="btn-label">Tahan</span>
        <kbd>F10</kbd>
      </button>

      <button type="button" class="btn btn-primary" :disabled="processing || !canNew" @click="emit('newTransaction')">
        <i class="ti ti-plus"></i>
        <span class="btn-label">Transaksi baru</span>
        <kbd>F11</kbd>
      </button>
    </div>
  </header>
</template>
