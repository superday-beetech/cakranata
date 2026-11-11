<script setup lang="ts">
import { computed, ref } from "vue";

type DockEdge = "left" | "right" | "top" | "bottom";

const props = defineProps<{
  open: boolean;
  edge: DockEdge;
  offset: number;
  items: Array<{ id: string; icon: string; label: string; shortcut?: string; disabled?: boolean }>;
}>();

const emit = defineEmits<{
  action: [id: string];
  toggle: [];
  move: [payload: { edge: DockEdge; offset: number }];
}>();

const vertical = computed(() => props.edge === "left" || props.edge === "right");

const dragging = ref(false);
const dragPoint = ref({ x: 0, y: 0 });

let tracking = false;
let moved = false;
let startX = 0;
let startY = 0;

function onPointerDown(event: PointerEvent): void {
  if (event.button !== 0) return;

  (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);

  tracking = true;
  moved = false;
  startX = event.clientX;
  startY = event.clientY;
}

function onPointerMove(event: PointerEvent): void {
  if (!tracking) return;

  // Gerakan kecil dianggap klik, bukan tarik.
  if (!moved && Math.hypot(event.clientX - startX, event.clientY - startY) < 6) return;

  moved = true;
  dragging.value = true;
  dragPoint.value = { x: event.clientX, y: event.clientY };
}

function onPointerUp(event: PointerEvent): void {
  if (!tracking) return;

  tracking = false;
  (event.currentTarget as HTMLElement).releasePointerCapture?.(event.pointerId);

  if (!moved) {
    emit("toggle");
    return;
  }

  dragging.value = false;

  // Tempel ke tepi layar terdekat.
  const x = event.clientX;
  const y = event.clientY;
  const width = window.innerWidth;
  const height = window.innerHeight;

  const distances: Record<DockEdge, number> = { left: x, right: width - x, top: y, bottom: height - y };
  const edge = (Object.keys(distances) as DockEdge[]).sort((a, b) => distances[a] - distances[b])[0];

  const raw = edge === "left" || edge === "right" ? (y / height) * 100 : (x / width) * 100;

  emit("move", { edge, offset: Math.min(92, Math.max(8, Math.round(raw))) });
}

function onPointerCancel(): void {
  tracking = false;
  dragging.value = false;
}

const dockStyle = computed(() =>
  dragging.value
    ? {
        top: `${dragPoint.value.y}px`,
        left: `${dragPoint.value.x}px`,
        right: "auto",
        bottom: "auto",
        transform: "translate(-50%, -50%)",
      }
    : { "--dock-offset": `${props.offset}%` },
);
</script>

<template>
  <aside class="pos-dock" :class="{ 'is-collapsed': !open, 'is-dragging': dragging }" :data-edge="edge" :style="dockStyle" aria-label="Toolbar cepat">
    <button
      type="button"
      class="pos-dock-handle"
      :title="open ? 'Klik untuk menutup · tarik untuk memindahkan' : 'Toolbar cepat · klik untuk membuka, tarik untuk memindahkan'"
      :aria-label="open ? 'Tutup toolbar cepat' : 'Buka toolbar cepat'"
      :aria-expanded="open"
      @pointerdown="onPointerDown"
      @pointermove="onPointerMove"
      @pointerup="onPointerUp"
      @pointercancel="onPointerCancel"
      @keydown.enter.prevent="emit('toggle')"
      @keydown.space.prevent="emit('toggle')"
    >
      <i :class="open ? (vertical ? 'ti ti-grip-horizontal' : 'ti ti-grip-vertical') : 'ti ti-apps'"></i>
    </button>

    <div v-show="open" class="pos-dock-items">
      <button
        v-for="item in items"
        :key="item.id"
        type="button"
        class="pos-dock-btn"
        :disabled="item.disabled"
        :title="item.shortcut ? `${item.label} (${item.shortcut})` : item.label"
        :aria-label="item.label"
        @click="emit('action', item.id)"
      >
        <i :class="item.icon"></i>
      </button>
    </div>
  </aside>
</template>
