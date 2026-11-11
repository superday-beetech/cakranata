<script setup lang="ts">
import { computed, ref, watch } from "vue";

interface Props {
  modelValue: number | string;
  placeholder?: string;
  disabled?: boolean;
  readonly?: boolean;
  size?: "sm" | "md" | "lg";
  min?: number;
  max?: number;
  prefix?: string;
  decimal?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: "0",
  disabled: false,
  readonly: false,
  size: "md",
  min: 0,
  prefix: "Rp",
  decimal: false,
});

const emit = defineEmits<{
  "update:modelValue": [value: number];
  change: [value: number];
}>();

const isFocused = ref(false);
const rawInput = ref("");

const numericValue = computed(() => {
  const value = Number(props.modelValue);
  return Number.isFinite(value) ? value : 0;
});

/* function formatCurrency(value: number): string {
  return new Intl.NumberFormat("id-ID", {
    maximumFractionDigits: 0,
  }).format(value);
}
 */

function formatCurrency(value: number): string {
  return new Intl.NumberFormat("id-ID", {
    maximumFractionDigits: props.decimal ? 2 : 0,
  }).format(value);
}

const displayValue = computed(() => {
  if (isFocused.value) {
    return rawInput.value ? formatCurrency(Number(rawInput.value)) : "";
  }

  return formatCurrency(numericValue.value);
});

function onFocus() {
  isFocused.value = true;
  rawInput.value = numericValue.value === 0 ? "" : String(numericValue.value);
}

/* function onInput(event: Event) {
  const target = event.target as HTMLInputElement;

  rawInput.value = target.value.replace(/\D/g, "");

  const value = Math.max(props.min, Number(rawInput.value) || 0);

  emit("update:modelValue", value);
} */
function onInput(event: Event) {
  const target = event.target as HTMLInputElement;

  rawInput.value = props.decimal ? target.value.replace(/[^\d,.-]/g, "") : target.value.replace(/\D/g, "");

  const value = Math.max(props.min, Number(rawInput.value.replace(",", ".")) || 0);
  const normalized = props.max !== undefined ? Math.min(props.max, value) : value;

  emit("update:modelValue", normalized);
}

function onBlur() {
  isFocused.value = false;

  if (!rawInput.value) {
    emit("update:modelValue", 0);
  }

  emit("change", Number(props.modelValue) || 0);
}

function clearValue() {
  rawInput.value = "";
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
  <div class="currency-input" :class="[`currency-input-${size}`, { 'is-disabled': disabled }]">
    <!-- <span class="currency-prefix">Rp</span> -->
    <span v-if="prefix" class="currency-prefix">{{ prefix }}</span>

    <input type="text" inputmode="numeric" autocomplete="off" :value="displayValue" :placeholder="placeholder" :disabled="disabled" :readonly="readonly" @focus="onFocus" @input="onInput" @blur="onBlur" />

    <button v-if="numericValue !== 0 && !disabled && !readonly" type="button" class="currency-clear" title="Hapus nilai" @mousedown.prevent @click="clearValue">
      <i class="ti ti-x"></i>
    </button>
  </div>
</template>

<style scoped>
.currency-input {
  display: flex;
  align-items: center;
  width: 100%;
  border: 1px solid var(--bs-border-color, #ced4da);
  border-radius: 6px;
  background: var(--bs-body-bg, #fff);
  overflow: hidden;
  transition:
    border-color 0.15s ease,
    box-shadow 0.15s ease;
}

.currency-input:focus-within {
  border-color: var(--bs-primary, #5d87ff);
  box-shadow: 0 0 0 0.2rem rgba(93, 135, 255, 0.15);
}

.currency-prefix {
  padding-left: 12px;
  color: var(--bs-secondary-color, #6c757d);
  font-weight: 600;
  user-select: none;
}

.currency-clear {
  border: 0;
  background: transparent;
  color: var(--bs-secondary-color, #6c757d);
  padding: 0 10px;
  cursor: pointer;
  display: flex;
  align-items: center;
}

.currency-clear:hover {
  color: var(--bs-danger, #dc3545);
}

.currency-input input {
  width: 100%;
  min-width: 0;
  border: 0;
  outline: 0;
  background: transparent;
  color: var(--bs-body-color, #212529);
  text-align: right;
  padding: 8px 12px 8px 8px;
  font: inherit;
}

.currency-input-sm input {
  padding-top: 5px;
  padding-bottom: 5px;
  font-size: 0.875rem;
}

.currency-input-lg input {
  padding-top: 12px;
  padding-bottom: 12px;
  font-size: 1.1rem;
}

.currency-input.is-disabled {
  background: var(--bs-secondary-bg, #e9ecef);
  opacity: 0.7;
}

.input-group > .currency-input {
  flex: 1 1 auto;
  width: 1%;
  min-width: 0;
  border-radius: 0 var(--bs-border-radius) var(--bs-border-radius) 0;
}

.input-group > .form-select + .currency-input {
  margin-left: -1px;
}

:deep(.input-group > .currency-input) {
  flex: 1 1 auto;
  width: 1%;
  min-width: 0;
  border-radius: 0 var(--bs-border-radius) var(--bs-border-radius) 0;
}

:deep(.input-group > .form-select + .currency-input) {
  margin-left: -1px;
}
</style>
