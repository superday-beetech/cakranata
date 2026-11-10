<script setup lang="ts">
import { computed, toRef } from "vue";
import { useButtonSpin } from "@/composables/useButtonSpin";

const props = withDefaults(
  defineProps<{
    modelValue: number | string;
    min?: number;
    max?: number;
    step?: number;
    disabled?: boolean;
    readonly?: boolean;
    placeholder?: string;
    size?: "sm" | "md" | "lg";
  }>(),
  {
    min: 0,
    max: Number.MAX_SAFE_INTEGER,
    step: 1,
    disabled: false,
    readonly: false,
    placeholder: "0",
    size: "md",
  },
);

const emit = defineEmits<{
  "update:modelValue": [value: number];
  change: [value: number];
}>();

const modelValueRef = toRef(props, "modelValue");

const { normalizedValue, canIncrease, canDecrease, increase, decrease, normalize } = useButtonSpin({
  modelValue: modelValueRef,
  min: props.min,
  max: props.max,
  step: props.step,
});

const inputValue = computed(() => {
  const value = Number(props.modelValue);

  return Number.isFinite(value) ? value : "";
});

function updateValue(value: number): void {
  emit("update:modelValue", value);
  emit("change", value);
}

function increment(): void {
  if (props.disabled || props.readonly || !canIncrease.value) {
    return;
  }

  updateValue(increase());
}

function decrement(): void {
  if (props.disabled || props.readonly || !canDecrease.value) {
    return;
  }

  updateValue(decrease());
}

function onInput(event: Event): void {
  const target = event.target as HTMLInputElement;

  if (target.value === "") return;

  const value = Number(target.value);

  if (!Number.isFinite(value)) return;

  updateValue(normalize(value));
}

function onBlur(event: FocusEvent): void {
  const target = event.target as HTMLInputElement;

  if (target.value === "") {
    updateValue(props.min);
    return;
  }

  updateValue(normalize(target.value));
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === "ArrowUp") {
    event.preventDefault();
    increment();
  }

  if (event.key === "ArrowDown") {
    event.preventDefault();
    decrement();
  }
}
</script>

<template>
  <div
    class="button-spin"
    :class="[
      `button-spin--${size}`,
      {
        'is-disabled': disabled,
        'is-readonly': readonly,
      },
    ]"
  >
    <button type="button" class="button-spin__button" aria-label="Kurangi jumlah" :disabled="disabled || readonly || !canDecrease" @click="decrement">
      <i class="ti ti-minus"></i>
    </button>

    <input :value="inputValue" type="number" class="button-spin__input" :min="min" :max="max" :step="step" :placeholder="placeholder" :disabled="disabled" :readonly="readonly" @input="onInput" @blur="onBlur" @keydown="onKeydown" />

    <button type="button" class="button-spin__button" aria-label="Tambah jumlah" :disabled="disabled || readonly || !canIncrease" @click="increment">
      <i class="ti ti-plus"></i>
    </button>
  </div>
</template>

<style scoped>
.button-spin {
  display: flex;
  align-items: stretch;
  width: 100%;
  min-width: 90px;
  height: 40px;
  overflow: hidden;
  border: 1px solid var(--bs-border-color, #dfe3e8);
  border-radius: 7px;
  background: var(--bs-body-bg, #fff);
  transition:
    border-color 0.18s ease,
    box-shadow 0.18s ease;
}

.button-spin:focus-within {
  border-color: var(--bs-primary, #5d87ff);
  box-shadow: 0 0 0 3px rgba(93, 135, 255, 0.12);
}

.button-spin__button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 36px;
  padding: 0;
  border: 0;
  background: var(--bs-tertiary-bg, #f8f9fa);
  color: var(--bs-secondary-color, #667085);
  cursor: pointer;
  transition:
    color 0.15s ease,
    background 0.15s ease;
}

.button-spin__button:hover:not(:disabled) {
  background: rgba(93, 135, 255, 0.12);
  color: var(--bs-primary, #5d87ff);
}

.button-spin__button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.button-spin__input {
  flex: 1;
  width: 100%;
  min-width: 0;
  padding: 0 5px;
  border: 0;
  outline: 0;
  background: transparent;
  color: var(--bs-body-color, #344054);
  font: inherit;
  font-weight: 600;
  text-align: center;
  appearance: textfield;
  -moz-appearance: textfield;
}

.button-spin__input::-webkit-inner-spin-button,
.button-spin__input::-webkit-outer-spin-button {
  margin: 0;
  appearance: none;
}

.button-spin__input:focus {
  outline: none;
  box-shadow: none;
}

.button-spin.is-disabled {
  opacity: 0.6;
  background: var(--bs-secondary-bg, #f2f4f7);
}

.button-spin.is-readonly {
  background: var(--bs-secondary-bg, #f2f4f7);
}

.button-spin--sm {
  height: 32px;
}

.button-spin--sm .button-spin__button {
  flex-basis: 29px;
}

.button-spin--sm .button-spin__input {
  font-size: 0.8rem;
}

.button-spin--lg {
  height: 46px;
}

.button-spin--lg .button-spin__button {
  flex-basis: 42px;
}

.button-spin--lg .button-spin__input {
  font-size: 1rem;
}
</style>
