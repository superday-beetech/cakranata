import { computed, type Ref } from "vue";

export interface ButtonSpinOptions {
  modelValue: Ref<number | string>;
  min?: number;
  max?: number;
  step?: number;
}

export function useButtonSpin(options: ButtonSpinOptions) {
  const min = options.min ?? 0;
  const max = options.max ?? Number.MAX_SAFE_INTEGER;
  const step = options.step ?? 1;

  const precision = computed(() => {
    const decimal = String(step).split(".")[1];
    return decimal?.length ?? 0;
  });

  const currentValue = computed(() => {
    const value = Number(options.modelValue.value);

    return Number.isFinite(value) ? value : min;
  });

  const normalizedValue = computed(() => Math.min(max, Math.max(min, currentValue.value)));

  const canDecrease = computed(() => normalizedValue.value > min);

  const canIncrease = computed(() => normalizedValue.value < max);

  function round(value: number): number {
    return Number(value.toFixed(precision.value));
  }

  function setValue(value: number): number {
    const next = round(Math.min(max, Math.max(min, value)));
    return next;
  }

  function increase(): number {
    return setValue(normalizedValue.value + step);
  }

  function decrease(): number {
    return setValue(normalizedValue.value - step);
  }

  function normalize(value: unknown): number {
    const parsed = Number(value);

    if (!Number.isFinite(parsed)) {
      return min;
    }

    return setValue(parsed);
  }

  return {
    currentValue,
    normalizedValue,
    canIncrease,
    canDecrease,
    increase,
    decrease,
    normalize,
  };
}
