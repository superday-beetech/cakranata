<script setup lang="ts">
import { computed, ref, nextTick } from "vue";
import { uuidv4 } from "@/utils/uuid";
import type { GlobalLogo, PaperConfig, PrintElement } from "../types/printTemplate";

const props = defineProps<{
  paper: PaperConfig;
  elements: PrintElement[];
  selectedId?: string | null;
  zoom?: number;
  logo?: GlobalLogo;
  logoSelected?: boolean;
}>();

const emit = defineEmits<{
  "add-element": [PrintElement];
  "move-element": [{ id: string; x: number; y: number }];
  "resize-element": [{ id: string; width: number; height: number }];
  "update-element": [Partial<PrintElement> & { id: string }];
  "delete-element": [string];
  "select-element": [string | null];
  "update-logo": [Partial<GlobalLogo>];
  "move-logo": [{ x: number; y: number }];
  "resize-logo": [{ width: number; height: number }];
  "select-logo": [];
  "deselect-logo": [];
}>();

const MM_TO_PX = 3.7795;
const GRID_MM = 5;
const SNAP_TOLERANCE_MM = 2;

const editingId = ref<string | null>(null);
const guides = ref<{ v: number[]; h: number[] }>({ v: [], h: [] });

const scale = computed(() => props.zoom ?? 1);

const paperWidthMm = computed(() => {
  const w = props.paper.width || 80;
  return props.paper.orientation === "landscape" ? props.paper.height || w : w;
});
const paperHeightMm = computed(() => {
  const h = props.paper.height || 120;
  return props.paper.orientation === "landscape" ? props.paper.width || h : h;
});

const canvasStyle = computed(() => ({
  width: `${paperWidthMm.value * MM_TO_PX * scale.value}px`,
  height: `${paperHeightMm.value * MM_TO_PX * scale.value}px`,
  position: "relative" as const,
}));

function pxToMm(v: number) {
  return v / (MM_TO_PX * scale.value);
}
function snap(v: number) {
  return Math.round(v / GRID_MM) * GRID_MM;
}

function onDrop(event: DragEvent) {
  event.preventDefault();
  const raw = event.dataTransfer?.getData("application/x-print-component");
  if (!raw) return;
  const item = JSON.parse(raw) as { type: PrintElement["type"]; label: string };
  const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
  const x = snap(pxToMm(event.clientX - rect.left));
  const y = snap(pxToMm(event.clientY - rect.top));

  emit("add-element", {
    id: uuidv4(),
    type: item.type,
    x,
    y,
    width: item.type === "table" ? 100 : item.type === "line" ? 60 : item.type === "logo" ? 20 : 40,
    height: item.type === "table" ? 40 : item.type === "line" ? 1 : item.type === "logo" ? 20 : 8,
    props: {
      label: item.label,
      fontSize: 11,
      color: "#000",
      align: "left",
      ...(item.type === "table"
        ? {
            columns: [
              { key: "no", label: "No", width: 12, align: "center" as const },
              { key: "name", label: "Nama", width: 60, align: "left" as const },
              { key: "qty", label: "Qty", width: 18, align: "center" as const },
              { key: "price", label: "Harga", width: 30, align: "right" as const },
            ],
            rows: [{ no: "1", name: "Contoh", qty: "1", price: "Rp 0" }],
          }
        : {}),
    },
  });
}

function onClickCanvas(e: MouseEvent) {
  if (e.target === e.currentTarget) {
    emit("select-element", null);
    emit("deselect-logo");
  }
}

/* ============ LOGO DRAG / RESIZE ============ */
function startLogoDrag(ev: MouseEvent) {
  if (!props.logo) return;
  emit("select-logo");
  const sx = ev.clientX;
  const sy = ev.clientY;
  const ox = props.logo.x;
  const oy = props.logo.y;
  function onMove(e: MouseEvent) {
    const dx = pxToMm(e.clientX - sx);
    const dy = pxToMm(e.clientY - sy);
    emit("move-logo", { x: snap(ox + dx), y: snap(oy + dy) });
  }
  function onUp() {
    window.removeEventListener("mousemove", onMove);
    window.removeEventListener("mouseup", onUp);
  }
  window.addEventListener("mousemove", onMove);
  window.addEventListener("mouseup", onUp);
}

function startLogoResize(ev: MouseEvent) {
  if (!props.logo) return;
  const sx = ev.clientX;
  const sy = ev.clientY;
  const ow = props.logo.width;
  const oh = props.logo.height;
  function onMove(e: MouseEvent) {
    const dx = pxToMm(e.clientX - sx);
    const dy = pxToMm(e.clientY - sy);
    emit("resize-logo", {
      width: Math.max(5, snap(ow + dx)),
      height: Math.max(5, snap(oh + dy)),
    });
  }
  function onUp() {
    window.removeEventListener("mousemove", onMove);
    window.removeEventListener("mouseup", onUp);
  }
  window.addEventListener("mousemove", onMove);
  window.addEventListener("mouseup", onUp);
}

/* ============ ELEMENT DRAG / RESIZE ============ */
function startDrag(ev: MouseEvent, el: PrintElement) {
  if (el.props.locked) {
    emit("select-element", el.id);
    return;
  }
  emit("select-element", el.id);
  const sx = ev.clientX;
  const sy = ev.clientY;
  const ox = el.x;
  const oy = el.y;

  function computeGuides(nx: number, ny: number) {
    const vGuides: number[] = [];
    const hGuides: number[] = [];
    for (const other of props.elements) {
      if (other.id === el.id) continue;
      if (Math.abs(other.x - nx) < SNAP_TOLERANCE_MM) vGuides.push(other.x);
      if (Math.abs(other.y - ny) < SNAP_TOLERANCE_MM) hGuides.push(other.y);
      const otherRight = other.x + other.width;
      if (Math.abs(otherRight - nx) < SNAP_TOLERANCE_MM) vGuides.push(otherRight);
      const otherBottom = other.y + other.height;
      if (Math.abs(otherBottom - ny) < SNAP_TOLERANCE_MM) hGuides.push(otherBottom);
    }
    guides.value = { v: vGuides, h: hGuides };
  }

  function onMove(e: MouseEvent) {
    const dx = pxToMm(e.clientX - sx);
    const dy = pxToMm(e.clientY - sy);
    const nx = snap(ox + dx);
    const ny = snap(oy + dy);
    computeGuides(nx, ny);
    emit("move-element", { id: el.id, x: nx, y: ny });
  }
  function onUp() {
    guides.value = { v: [], h: [] };
    window.removeEventListener("mousemove", onMove);
    window.removeEventListener("mouseup", onUp);
  }
  window.addEventListener("mousemove", onMove);
  window.addEventListener("mouseup", onUp);
}

function startResize(ev: MouseEvent, el: PrintElement) {
  if (el.props.locked) return;
  const sx = ev.clientX;
  const sy = ev.clientY;
  const ow = el.width;
  const oh = el.height;
  function onMove(e: MouseEvent) {
    const dx = pxToMm(e.clientX - sx);
    const dy = pxToMm(e.clientY - sy);
    emit("resize-element", {
      id: el.id,
      width: Math.max(5, snap(ow + dx)),
      height: Math.max(2, snap(oh + dy)),
    });
  }
  function onUp() {
    window.removeEventListener("mousemove", onMove);
    window.removeEventListener("mouseup", onUp);
  }
  window.addEventListener("mousemove", onMove);
  window.addEventListener("mouseup", onUp);
}

/* ============ INLINE EDIT ============ */
function startInlineEdit(el: PrintElement) {
  if (el.type === "table" || el.type === "qr" || el.type === "barcode" || el.type === "logo") return;
  if (el.props.binding) return;
  if (el.props.locked) return;
  editingId.value = el.id;
  nextTick(() => {
    const node = document.querySelector<HTMLElement>(`[data-el-id="${el.id}"] .inline-editor`);
    node?.focus();
  });
}
function commitInlineEdit(el: PrintElement, e: Event) {
  const text = (e.target as HTMLElement).innerText.trim();
  emit("update-element", { id: el.id, props: { ...el.props, label: text } });
  editingId.value = null;
}
function onInlineKeydown(e: KeyboardEvent) {
  if (e.key === "Escape") {
    editingId.value = null;
    e.preventDefault();
  } else if (e.key === "Enter" && !e.shiftKey) {
    (e.target as HTMLElement).blur();
    e.preventDefault();
  }
}

function elStyle(el: PrintElement) {
  const s = scale.value;
  return {
    left: `${el.x * MM_TO_PX * s}px`,
    top: `${el.y * MM_TO_PX * s}px`,
    width: `${el.width * MM_TO_PX * s}px`,
    height: `${el.height * MM_TO_PX * s}px`,
    fontSize: `${(el.props.fontSize || 11) * MM_TO_PX * s * 0.35}px`,
    color: el.props.color || "#000",
    justifyContent: el.props.align === "center" ? "center" : el.props.align === "right" ? "flex-end" : "flex-start",
    border: el.type === "rect" ? "1px solid #333" : "none",
    whiteSpace: "pre-wrap" as const,
    alignItems: "flex-start" as const,
  };
}

function logoBoxStyle() {
  if (!props.logo) return {};
  const l = props.logo;
  const s = scale.value;
  return {
    left: `${l.x * MM_TO_PX * s}px`,
    top: `${l.y * MM_TO_PX * s}px`,
    width: `${l.width * MM_TO_PX * s}px`,
    height: `${l.height * MM_TO_PX * s}px`,
  };
}
</script>

<template>
  <div class="paper-canvas bg-white border rounded shadow-sm" :style="canvasStyle" @dragover.prevent @drop="onDrop" @click="onClickCanvas">
    <!-- Guide lines -->
    <div v-for="(vx, i) in guides.v" :key="`v-${i}`" class="guide guide-v" :style="{ left: `${vx * MM_TO_PX * scale}px` }"></div>
    <div v-for="(hy, i) in guides.h" :key="`h-${i}`" class="guide guide-h" :style="{ top: `${hy * MM_TO_PX * scale}px` }"></div>

    <!-- LOGO GLOBAL (draggable) -->
    <div v-if="logo?.url" class="print-el logo-el" :class="{ selected: props.logoSelected }" :style="logoBoxStyle()" @mousedown.stop="startLogoDrag($event)" @click.stop="emit('select-logo')">
      <img :src="logo.url" alt="logo" class="logo-img" />
      <template v-if="props.logoSelected">
        <div class="resize-handle" @mousedown.stop.prevent="startLogoResize($event)"></div>
        <button class="delete-handle" title="Hapus logo" @mousedown.stop.prevent="emit('update-logo', { url: '' })">×</button>
      </template>
    </div>

    <!-- ELEMENTS -->
    <template v-for="el in elements" :key="el.id">
      <div
        class="print-el"
        :class="{
          selected: el.id === props.selectedId,
          editing: editingId === el.id,
          locked: el.props.locked,
        }"
        :data-el-id="el.id"
        :style="elStyle(el)"
        @mousedown.stop="startDrag($event, el)"
        @dblclick.stop="startInlineEdit(el)"
      >
        <div v-if="editingId === el.id" class="inline-editor" contenteditable="true" @blur="commitInlineEdit(el, $event)" @keydown="onInlineKeydown" v-text="el.props.label || el.type"></div>

        <template v-else-if="el.type === 'logo'">
          <img v-if="el.props.src" :src="el.props.src as string" alt="logo" style="max-width: 100%; max-height: 100%; object-fit: contain; pointer-events: none" />
          <span v-else class="text-muted small">[Logo]</span>
        </template>

        <template v-else>
          <span>{{ el.props.label || el.type }}</span>
          <small v-if="el.props.binding" class="binding-hint">→ {{ el.props.binding }}</small>
          <small v-if="el.props.locked" class="lock-hint">🔒</small>
        </template>

        <template v-if="el.id === props.selectedId && !el.props.locked">
          <div class="resize-handle" @mousedown.stop.prevent="startResize($event, el)"></div>
          <button class="delete-handle" title="Hapus" @mousedown.stop.prevent="emit('delete-element', el.id)">×</button>
        </template>
      </div>
    </template>

    <div v-if="elements.length === 0 && !logo?.url" class="empty-hint text-muted text-center py-5">
      <i class="ti ti-drag-drop fs-1 d-block mb-2"></i>
      Seret komponen dari panel kiri ke sini
    </div>
  </div>
</template>

<style scoped>
.paper-canvas {
  position: relative;
  overflow: hidden;
}
.print-el {
  position: absolute;
  display: flex;
  align-items: flex-start;
  cursor: move;
  padding: 1px 2px;
  user-select: none;
}
.print-el.locked {
  cursor: not-allowed;
  opacity: 0.85;
}
.print-el.selected {
  outline: 2px dashed #5d87ff;
  outline-offset: 1px;
}
.print-el.editing {
  outline: 2px solid #5d87ff;
  background: rgba(93, 135, 255, 0.05);
}
.logo-el {
  padding: 0;
  background: rgba(93, 135, 255, 0.02);
}
.logo-img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  pointer-events: none;
}
.inline-editor {
  outline: none;
  min-width: 30px;
  min-height: 14px;
  cursor: text;
  white-space: pre-wrap;
}
.binding-hint {
  font-size: 8px;
  color: #5d87ff;
  margin-left: 3px;
  opacity: 0.7;
}
.lock-hint {
  font-size: 8px;
  margin-left: 3px;
}
.resize-handle {
  position: absolute;
  right: -4px;
  bottom: -4px;
  width: 10px;
  height: 10px;
  background: #5d87ff;
  border: 1px solid #fff;
  border-radius: 2px;
  cursor: nwse-resize;
}
.delete-handle {
  position: absolute;
  right: -10px;
  top: -10px;
  width: 18px;
  height: 18px;
  background: #fa896b;
  color: #fff;
  border: none;
  border-radius: 50%;
  font-size: 12px;
  line-height: 1;
  cursor: pointer;
  padding: 0;
}
.empty-hint {
  pointer-events: none;
}
.guide {
  position: absolute;
  background: #ff7a7a;
  pointer-events: none;
  z-index: 5;
}
.guide-v {
  top: 0;
  bottom: 0;
  width: 1px;
}
.guide-h {
  left: 0;
  right: 0;
  height: 1px;
}
</style>
