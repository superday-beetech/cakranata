<script setup lang="ts">
import { computed } from "vue";
import type { GlobalLogo, PaperConfig, PrintElement } from "../types/printTemplate";
import type { CSSProperties } from "vue";
const props = defineProps<{
  paper: PaperConfig;
  elements: PrintElement[];
  logo?: GlobalLogo;
}>();

const MM_TO_PX = 3.7795;
const previewMaxWidthPx = 340;

const paperWidthMm = computed(() => {
  const w = props.paper.width || 80;
  return props.paper.orientation === "landscape" ? props.paper.height || w : w;
});
const paperHeightMm = computed(() => {
  const h = props.paper.height || 120;
  return props.paper.orientation === "landscape" ? props.paper.width || h : h;
});
const scale = computed(() => Math.min(1, previewMaxWidthPx / (paperWidthMm.value * MM_TO_PX)));

const paperStyle = computed(() => ({
  width: `${paperWidthMm.value * MM_TO_PX * scale.value}px`,
  height: `${paperHeightMm.value * MM_TO_PX * scale.value}px`,
  position: "relative" as const,
  background: "#fff",
  boxShadow: "0 2px 8px rgba(0,0,0,0.08)",
  border: "1px solid #e5e7eb",
  overflow: "hidden",
  fontFamily: "Helvetica, Arial, sans-serif",
}));

function elementStyle(el: PrintElement): CSSProperties {
  const s = scale.value;
  return {
    position: "absolute",
    left: `${el.x * MM_TO_PX * s}px`,
    top: `${el.y * MM_TO_PX * s}px`,
    width: `${el.width * MM_TO_PX * s}px`,
    height: `${el.height * MM_TO_PX * s}px`,
    fontSize: `${(el.props.fontSize || 11) * MM_TO_PX * s * 0.35}px`,
    color: el.props.color || "#000",
    border: el.type === "rect" ? "1px solid #333" : "none",
    display: "flex",
    justifyContent: el.props.align === "center" ? "center" : el.props.align === "right" ? "flex-end" : "flex-start",
    alignItems: "flex-start",
    overflow: "hidden",
    whiteSpace: "pre-wrap",
    lineHeight: 1.25,
  };
}

function logoStyle() {
  if (!props.logo?.url) return {};
  const l = props.logo;
  const s = scale.value;
  return {
    position: "absolute" as const,
    left: `${l.x * MM_TO_PX * s}px`,
    top: `${l.y * MM_TO_PX * s}px`,
    width: `${l.width * MM_TO_PX * s}px`,
    height: `${l.height * MM_TO_PX * s}px`,
    objectFit: "contain" as const,
  };
}

function contentFor(el: PrintElement): string {
  if (el.props.binding) return `{{ ${el.props.binding} }}`;
  return el.props.label || el.type;
}
</script>

<template>
  <div class="preview-wrapper text-center">
    <div class="d-inline-block" :style="paperStyle">
      <!-- Logo global -->
      <img v-if="logo?.url" :src="logo.url" :style="logoStyle()" alt="logo" />

      <template v-for="el in elements" :key="el.id">
        <div v-if="el.type === 'text'" :style="elementStyle(el)">{{ contentFor(el) }}</div>

        <div v-else-if="el.type === 'line'" :style="{ ...elementStyle(el), height: '1px', background: el.props.color || '#000' }"></div>

        <div v-else-if="el.type === 'rect'" :style="elementStyle(el)"></div>

        <div v-else-if="el.type === 'table'" :style="{ ...elementStyle(el), border: '1px dashed #999' }">Tabel</div>

        <div v-else-if="el.type === 'qr'" :style="{ ...elementStyle(el), border: '1px dashed #999', justifyContent: 'center', alignItems: 'center', fontSize: '9px' }">QR</div>

        <div v-else-if="el.type === 'logo'" :style="{ ...elementStyle(el), border: '1px dashed #999', justifyContent: 'center', alignItems: 'center' }">
          <img v-if="el.props.src" :src="el.props.src as string" alt="logo" style="max-width: 100%; max-height: 100%; object-fit: contain" />
          <span v-else style="font-size: 9px">Logo</span>
        </div>

        <div v-else :style="{ ...elementStyle(el), border: '1px dashed #999', justifyContent: 'center', alignItems: 'center', fontSize: '9px' }">
          {{ contentFor(el) }}
        </div>
      </template>
    </div>
    <p class="text-muted small mt-2 mb-0">{{ Math.round(scale * 100) }}% dari asli ({{ paperWidthMm }} × {{ paperHeightMm }} mm)</p>
  </div>
</template>

<style scoped>
.preview-wrapper {
  user-select: none;
}
</style>
