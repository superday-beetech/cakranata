<script setup lang="ts">
import { computed, ref, nextTick } from "vue";
import { uuidv4 } from "@/utils/uuid";
import type { PaperConfig, PrintElement } from "../types/printTemplate";

const props = defineProps<{
  paper: PaperConfig;
  elements: PrintElement[];
  selectedId?: string | null;
}>();

const emit = defineEmits<{
  "add-element": [PrintElement];
  "move-element": [{ id: string; x: number; y: number }];
  "resize-element": [{ id: string; width: number; height: number }];
  "update-element": [Partial<PrintElement> & { id: string }];
  "delete-element": [string];
  "select-element": [string | null];
}>();

const editingId = ref<string | null>(null);

const canvasStyle = computed(() => ({
  width: props.paper.width ? `${props.paper.width}${props.paper.unit}` : "80mm",
  minHeight: props.paper.type === "80mm" ? "120mm" : props.paper.height ? `${props.paper.height}${props.paper.unit}` : "297mm",
  position: "relative" as const,
}));

function onDrop(event: DragEvent) {
  event.preventDefault();
  const raw = event.dataTransfer?.getData("application/x-print-component");
  if (!raw) return;
  const item = JSON.parse(raw) as { type: PrintElement["type"]; label: string };
  const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
  emit("add-element", {
    id: uuidv4(),
    type: item.type,
    x: Math.round(event.clientX - rect.left),
    y: Math.round(event.clientY - rect.top),
    width: 120,
    height: item.type === "line" ? 2 : item.type === "table" ? 120 : 24,
    props: {
      label: item.label,
      fontSize: 12,
      color: "#000",
      align: "left",
      ...(item.type === "table"
        ? {
            columns: [
              { key: "no", label: "No", width: 40, align: "center" },
              { key: "name", label: "Nama", width: 200, align: "left" },
              { key: "qty", label: "Qty", width: 60, align: "center" },
              { key: "price", label: "Harga", width: 120, align: "right" },
            ],
            rows: [{ no: "1", name: "Contoh", qty: "1", price: "Rp 0" }],
          }
        : {}),
    },
  });
}

function onClickCanvas(e: MouseEvent) {
  if (e.target === e.currentTarget) emit("select-element", null);
}

function startDrag(ev: MouseEvent, el: PrintElement) {
  emit("select-element", el.id);
  const sx = ev.clientX,
    sy = ev.clientY,
    ox = el.x,
    oy = el.y;
  function onMove(e: MouseEvent) {
    emit("move-element", {
      id: el.id,
      x: Math.round(ox + (e.clientX - sx)),
      y: Math.round(oy + (e.clientY - sy)),
    });
  }
  function onUp() {
    window.removeEventListener("mousemove", onMove);
    window.removeEventListener("mouseup", onUp);
  }
  window.addEventListener("mousemove", onMove);
  window.addEventListener("mouseup", onUp);
}

function startResize(ev: MouseEvent, el: PrintElement) {
  const sx = ev.clientX,
    sy = ev.clientY,
    ow = el.width,
    oh = el.height;
  function onMove(e: MouseEvent) {
    emit("resize-element", {
      id: el.id,
      width: Math.max(20, ow + (e.clientX - sx)),
      height: Math.max(10, oh + (e.clientY - sy)),
    });
  }
  function onUp() {
    window.removeEventListener("mousemove", onMove);
    window.removeEventListener("mouseup", onUp);
  }
  window.addEventListener("mousemove", onMove);
  window.addEventListener("mouseup", onUp);
}

function startInlineEdit(el: PrintElement) {
  if (el.type === "table") return;
  if (el.props.binding) return;
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
</script>

<template>
  <div class="paper-canvas bg-white border rounded shadow-sm" :style="canvasStyle" @dragover.prevent @drop="onDrop" @click="onClickCanvas">
    <template v-for="el in elements" :key="el.id">
      <div
        class="print-el"
        :class="{ selected: el.id === props.selectedId, editing: editingId === el.id }"
        :data-el-id="el.id"
        :style="{
          left: el.x + 'px',
          top: el.y + 'px',
          width: el.width + 'px',
          height: el.height + 'px',
          fontSize: (el.props.fontSize || 12) + 'px',
          color: el.props.color || '#000',
          justifyContent: el.props.align === 'center' ? 'center' : el.props.align === 'right' ? 'flex-end' : 'flex-start',
          border: el.type === 'rect' ? '1px solid #333' : 'none',
          whiteSpace: 'pre-wrap',
          alignItems: 'flex-start',
        }"
        @mousedown.stop="startDrag($event, el)"
        @dblclick.stop="startInlineEdit(el)"
      >
        <div v-if="editingId === el.id" class="inline-editor" contenteditable="true" @blur="commitInlineEdit(el, $event)" @keydown.enter.exact.prevent="($event.target as HTMLElement).blur()" v-text="el.props.label || el.type"></div>
        <template v-else>
          <span>{{ el.props.label || el.type }}</span>
          <small v-if="el.props.binding" class="binding-hint">→ {{ el.props.binding }}</small>
        </template>
        <div v-if="el.id === props.selectedId" class="resize-handle" @mousedown.stop.prevent="startResize($event, el)"></div>
        <button v-if="el.id === props.selectedId" class="delete-handle" title="Hapus" @mousedown.stop.prevent="emit('delete-element', el.id)">×</button>
      </div>
    </template>
    <div v-if="elements.length === 0" class="empty-hint text-muted text-center py-5">
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
  padding: 2px 4px;
  user-select: none;
}
.print-el.selected {
  outline: 2px dashed #5d87ff;
  outline-offset: 1px;
}
.print-el.editing {
  outline: 2px solid #5d87ff;
  background: rgba(93, 135, 255, 0.05);
}
.inline-editor {
  outline: none;
  min-width: 40px;
  min-height: 16px;
  cursor: text;
  white-space: pre-wrap;
}
.binding-hint {
  font-size: 8px;
  color: #5d87ff;
  margin-left: 4px;
  opacity: 0.7;
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
</style>
