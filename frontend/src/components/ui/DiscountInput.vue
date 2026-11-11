<script setup lang="ts">
import { computed, ref, watch } from "vue";

type DiscountMode = "percent" | "amount";

interface Props {
  modelValue: number | string;
  mode?: DiscountMode;
  disabled?: boolean;
  placeholder?: string;
}

const props = withDefaults(defineProps<Props>(), {
  mode: "percent",
  disabled: false,
  placeholder: "0",
});

const emit = defineEmits<{
  "update:modelValue": [value: number];
  "update:mode": [value: DiscountMode];
  change: [value: number];
}>();

const isFocused = ref(false);
const rawInput = ref("");

const numericValue = computed(() => {
  const value = Number(props.modelValue);
  return Number.isFinite(value) && value >= 0 ? value : 0;
});

const displayValue = computed(() => {
  const value = isFocused.value ? rawInput.value : String(numericValue.value);

  if (value === "") return "";

  if (props.mode === "percent") {
    return value;
  }

  return new Intl.NumberFormat("id-ID").format(Number(value) || 0);
});

function onFocus() {
  if (props.disabled) return;

  isFocused.value = true;
  rawInput.value = numericValue.value === 0 ? "" : String(numericValue.value);
}

function onInput(event: Event) {
  const target = event.target as HTMLInputElement;

  const digits = target.value.replace(/\D/g, "");

  if (props.mode === "percent") {
    const value = Math.min(100, Number(digits) || 0);

    rawInput.value = String(value);
    emit("update:modelValue", value);
    return;
  }

  rawInput.value = digits;

  const value = Number(digits) || 0;
  emit("update:modelValue", value);
}

function onBlur() {
  isFocused.value = false;

  const value = rawInput.value === "" ? 0 : numericValue.value;

  emit("update:modelValue", value);
  emit("change", value);
}

function clearValue() {
  if (props.disabled) return;

  rawInput.value = "";

  emit("update:modelValue", 0);
  emit("change", 0);
}

function changeMode(mode: DiscountMode) {
  if (props.disabled || props.mode === mode) return;

  rawInput.value = "";

  emit("update:mode", mode);
  emit("update:modelValue", 0);
  emit("change", 0);
}

watch(
  () => props.modelValue,
  (value) => {
    if (!isFocused.value) {
      rawInput.value = String(Number(value) || 0);
    }
  },
);
</script>

<template>
  <div class="discount-input">
    <div class="discount-mode">
      <button type="button" :class="{ active: mode === 'percent' }" :disabled="disabled" @click="changeMode('percent')">%</button>

      <button type="button" :class="{ active: mode === 'amount' }" :disabled="disabled" @click="changeMode('amount')">Rp</button>
    </div>

    <input type="text" inputmode="numeric" autocomplete="off" :value="displayValue" :placeholder="placeholder" :disabled="disabled" @focus="onFocus" @input="onInput" @blur="onBlur" />

    <button v-if="numericValue !== 0 && !disabled" type="button" class="discount-clear" title="Hapus diskon" @mousedown.prevent @click="clearValue">
      <i class="ti ti-x"></i>
    </button>
  </div>
</template>

<style scoped>
.discount-input {
  display: flex;
  align-items: center;
  width: 100%;
  min-width: 0;
  border: 1px solid var(--bs-border-color, #ced4da);
  border-radius: 6px;
  overflow: hidden;
  background: var(--bs-body-bg, #fff);
}

.discount-input:focus-within {
  border-color: var(--bs-primary, #5d87ff);
  box-shadow: 0 0 0 0.2rem rgba(93, 135, 255, 0.15);
}

.discount-mode {
  display: flex;
  align-items: center;
  gap: 3px;
  padding: 3px;
  background: var(--bs-secondary-bg, #f1f3f5);
}

.discount-mode button {
  border: 0;
  border-radius: 4px;
  padding: 5px 9px;
  background: transparent;
  color: var(--bs-secondary-color);
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
}

.discount-mode button.active {
  background: var(--bs-primary, #5d87ff);
  color: white;
}

.discount-mode button:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.discount-input input {
  flex: 1;
  width: 100%;
  min-width: 0;
  border: 0;
  outline: 0;
  background: transparent;
  color: var(--bs-body-color);
  text-align: right;
  padding: 8px;
  font: inherit;
}

.discount-clear {
  border: 0;
  background: transparent;
  color: var(--bs-secondary-color);
  padding: 0 10px;
  cursor: pointer;
}

.discount-clear:hover {
  color: var(--bs-danger);
}
</style>
