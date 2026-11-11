import { computed, type Ref } from "vue";

export function useCurrencyInput(modelValue: Ref<number | string>) {
  const numericValue = computed(() => {
    const value = Number(modelValue.value);
    return Number.isFinite(value) ? value : 0;
  });

  const formattedValue = computed(() =>
    new Intl.NumberFormat("id-ID", {
      maximumFractionDigits: 2,
    }).format(numericValue.value),
  );

  function parse(value: string): number {
    const cleaned = value.replace(/[^\d,.-]/g, "");
    const normalized = cleaned.replace(/\./g, "").replace(",", ".");

    const parsed = Number(normalized);

    return Number.isFinite(parsed) ? parsed : 0;
  }

  function update(value: string): void {
    modelValue.value = parse(value);
  }

  return {
    formattedValue,
    numericValue,
    update,
  };
}
