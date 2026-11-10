<script setup lang="ts">
import { nextTick, ref, watch } from "vue";
import { formatNumber } from "../../utils/format";

const props = defineProps<{
  modelValue: number;
  id?: string;
  disabled?: boolean;
}>();

const emit = defineEmits<{
  "update:modelValue": [value: number];
}>();

function format(value: number): string {
  return value > 0 ? formatNumber(value) : "";
}

const display = ref(format(props.modelValue));

// Sinkron saat nilai diubah dari luar (tombol nominal cepat, Alt+angka, uang pas, prefill total).
watch(
  () => props.modelValue,
  (value) => {
    const current = Number(display.value.replace(/\D/g, "")) || 0;
    if (current !== value) display.value = format(value);
  },
);

function onInput(event: Event): void {
  const el = event.target as HTMLInputElement;
  const caret = el.selectionStart ?? el.value.length;
  const digitsBeforeCaret = el.value.slice(0, caret).replace(/\D/g, "").length;

  const digits = el.value.replace(/\D/g, "").slice(0, 12);
  const value = digits ? Number(digits) : 0;

  display.value = format(value);
  el.value = display.value;
  emit("update:modelValue", value);

  // Kembalikan posisi kursor setelah titik ribuan disisipkan.
  void nextTick(() => {
    let pos = 0;
    let count = 0;
    while (pos < el.value.length && count < digitsBeforeCaret) {
      if (/\d/.test(el.value[pos])) count++;
      pos++;
    }
    el.setSelectionRange(pos, pos);
  });
}
</script>

<template>
  <input :id="id" type="text" inputmode="numeric" autocomplete="off" class="form-control text-end" placeholder="0" :value="display" :disabled="disabled" @input="onInput" @focus="($event.target as HTMLInputElement).select()" />
</template>
