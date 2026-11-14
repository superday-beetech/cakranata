<script setup lang="ts">
import type { PrintElementType } from "../types/printTemplate";
interface PaletteItem {
  type: PrintElementType;
  label: string;
  icon: string;
}
const items: PaletteItem[] = [
  { type: "text", label: "Teks", icon: "ti ti-typography" },
  { type: "line", label: "Garis", icon: "ti ti-minus" },
  { type: "rect", label: "Kotak", icon: "ti ti-square" },
  { type: "table", label: "Tabel", icon: "ti ti-table" },
  { type: "image", label: "Gambar", icon: "ti ti-photo" },
  { type: "barcode", label: "Barcode", icon: "ti ti-barcode" },
  { type: "qr", label: "QR Code", icon: "ti ti-qrcode" },
  { type: "logo", label: "Logo", icon: "ti ti-photo" },
];
function onDragStart(event: DragEvent, item: PaletteItem) {
  if (!event.dataTransfer) return;
  event.dataTransfer.setData("application/x-print-component", JSON.stringify(item));
  event.dataTransfer.effectAllowed = "copy";
}
</script>
<template>
  <div class="palette">
    <p class="text-muted small mb-2">Seret ke kanvas untuk menambahkan.</p>
    <div class="d-flex flex-column gap-2">
      <div v-for="item in items" :key="item.type" class="palette-item d-flex align-items-center p-2 rounded border bg-white" draggable="true" role="button" @dragstart="onDragStart($event, item)">
        <i :class="item.icon" class="fs-5 text-primary me-2"></i>
        <span class="fw-medium">{{ item.label }}</span>
      </div>
    </div>
  </div>
</template>
<style scoped>
.palette-item {
  cursor: grab;
  user-select: none;
  transition: all 0.15s;
}
.palette-item:hover {
  background: #f3f6f9 !important;
  border-color: #5d87ff !important;
  transform: translateY(-1px);
}
.palette-item:active {
  cursor: grabbing;
}
</style>
