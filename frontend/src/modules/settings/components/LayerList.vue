<script setup lang="ts">
import type { PrintElement } from "../types/printTemplate";

const props = defineProps<{
  elements: PrintElement[];
  selectedId: string | null;
}>();
const emit = defineEmits<{
  select: [string];
  "move-up": [string];
  "move-down": [string];
  "toggle-lock": [string];
  delete: [string];
}>();

function iconFor(type: PrintElement["type"]) {
  return {
    text: "ti-text-size",
    line: "ti-minus",
    rect: "ti-square",
    table: "ti-table",
    image: "ti-photo",
    barcode: "ti-barcode",
    qr: "ti-qrcode",
    logo: "ti-photo",
  }[type];
}
</script>

<template>
  <div class="layer-list">
    <div class="p-2 border-bottom">
      <small class="text-muted fw-semibold">LAYERS ({{ elements.length }})</small>
    </div>
    <div v-if="elements.length === 0" class="text-muted small text-center p-3">Belum ada elemen</div>
    <div v-for="(el, i) in [...elements].reverse()" :key="el.id" class="layer-row d-flex align-items-center gap-1 px-2 py-1" :class="{ active: el.id === selectedId }" @click="emit('select', el.id)">
      <i :class="`ti ${iconFor(el.type)}`"></i>
      <span class="layer-label flex-grow-1 text-truncate small">
        {{ el.props.label || el.type }}
      </span>
      <button class="btn btn-xs btn-ghost" title="Naik" @click.stop="emit('move-up', el.id)">
        <i class="ti ti-arrow-up"></i>
      </button>
      <button class="btn btn-xs btn-ghost" title="Turun" @click.stop="emit('move-down', el.id)">
        <i class="ti ti-arrow-down"></i>
      </button>
      <button class="btn btn-xs btn-ghost" :title="el.props.locked ? 'Buka kunci' : 'Kunci'" @click.stop="emit('toggle-lock', el.id)">
        <i :class="el.props.locked ? 'ti ti-lock' : 'ti ti-lock-open'"></i>
      </button>
      <button class="btn btn-xs btn-ghost text-danger" @click.stop="emit('delete', el.id)">
        <i class="ti ti-x"></i>
      </button>
    </div>
  </div>
</template>

<style scoped>
.layer-list {
  background: #fafbfc;
}
.layer-row {
  cursor: pointer;
  border-bottom: 1px solid #eef1f4;
  font-size: 12px;
}
.layer-row:hover {
  background: #f1f5f9;
}
.layer-row.active {
  background: #e7efff;
}
.layer-label {
  min-width: 0;
}
.btn-xs {
  padding: 0 4px;
  font-size: 11px;
  line-height: 1;
  border: none;
  background: transparent;
}
.btn-xs:hover {
  background: #e2e8f0;
}
</style>
