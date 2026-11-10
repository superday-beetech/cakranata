<script setup lang="ts">
defineProps<{
  visible: boolean;
}>();

const emit = defineEmits<{
  close: [];
}>();

const shortcuts = [
  { key: "F1", label: "Cari produk / scan barcode" },
  { key: "F2", label: "Fokus pelanggan" },
  { key: "F3", label: "Fokus keranjang" },
  { key: "F4", label: "Fokus diskon transaksi" },
  { key: "F5", label: "Fokus biaya tambahan" },
  { key: "F6", label: "Mode pembayaran" },
  { key: "F7", label: "Metode pembayaran" },
  { key: "F8", label: "Nominal pembayaran" },
  { key: "F9", label: "Uang pas" },
  { key: "F10", label: "Tahan transaksi" },
  { key: "F11", label: "Transaksi baru" },
  { key: "F12", label: "Proses transaksi" },
  { key: "ESC", label: "Tutup bantuan" },
];
</script>

<template>
  <section v-if="visible" class="pos-shortcut-panel" aria-label="Bantuan keyboard shortcut">
    <div class="pos-shortcut-heading">
      <div>
        <strong>Keyboard Shortcuts</strong>
        <p>Gunakan tombol berikut untuk mempercepat proses kasir.</p>
      </div>

      <button type="button" class="pos-icon-button" aria-label="Tutup bantuan shortcut" @click="emit('close')">
        <i class="ti ti-x"></i>
      </button>
    </div>

    <div class="pos-shortcut-grid">
      <span v-for="shortcut in shortcuts" :key="shortcut.key">
        <kbd>{{ shortcut.key }}</kbd>
        {{ shortcut.label }}
      </span>
    </div>
  </section>
</template>
