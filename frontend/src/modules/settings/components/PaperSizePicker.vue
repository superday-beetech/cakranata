<script setup lang="ts">
import type { PaperConfig, PaperType } from "../types/printTemplate";
const props = defineProps<{ modelValue: PaperConfig }>();
const emit = defineEmits<{ "update:modelValue": [PaperConfig] }>();
const presets: Record<Exclude<PaperType, "custom">, Pick<PaperConfig, "width" | "height">> = {
  a4: { width: 210, height: 297 },
  a5: { width: 148, height: 210 },
  "80mm": { width: 80, height: 0 },
};
function onChange(type: PaperType) {
  const preset = type === "custom" ? null : presets[type as keyof typeof presets];
  emit("update:modelValue", { ...props.modelValue, type, ...(preset ?? {}) });
}
</script>
<template>
  <select :value="modelValue.type" class="form-select" @change="onChange(($event.target as HTMLSelectElement).value as PaperType)">
    <option value="a4">A4 (210 × 297 mm)</option>
    <option value="a5">A5 (148 × 210 mm)</option>
    <option value="80mm">Struk 80mm</option>
    <option value="custom">Custom</option>
  </select>
</template>
