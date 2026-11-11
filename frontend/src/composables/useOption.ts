import { computed, ref, type Ref } from "vue";

export type OptionValue = string | number;

export interface OptionItem {
  value: OptionValue;
  label: string;
  description?: string;
  keywords?: string;
  disabled?: boolean;
}

export function useOption(options: Readonly<Ref<OptionItem[]>>, modelValue: Readonly<Ref<OptionValue | null | undefined>>) {
  const isOpen = ref(false);
  const search = ref("");
  const activeIndex = ref(0);

  const selectedOption = computed(() => options.value.find((item) => String(item.value) === String(modelValue.value)) ?? null);

  const filteredOptions = computed(() => {
    const keyword = search.value.trim().toLocaleLowerCase("id-ID");

    if (!keyword) return options.value;

    return options.value.filter((item) => {
      const searchable = [item.label, item.description ?? "", item.keywords ?? ""].join(" ").toLocaleLowerCase("id-ID");

      return searchable.includes(keyword);
    });
  });

  const enabledIndexes = computed(() => filteredOptions.value.map((item, index) => (item.disabled ? -1 : index)).filter((index) => index >= 0));

  function open(): void {
    isOpen.value = true;
    search.value = "";
    activeIndex.value = Math.max(
      0,
      filteredOptions.value.findIndex((item) => String(item.value) === String(modelValue.value) && !item.disabled),
    );
  }

  function close(): void {
    isOpen.value = false;
    search.value = "";
    activeIndex.value = 0;
  }

  function toggle(): void {
    if (isOpen.value) {
      close();
    } else {
      open();
    }
  }

  function setSearch(value: string): void {
    search.value = value;
    activeIndex.value = enabledIndexes.value[0] ?? 0;
  }

  function moveActive(direction: 1 | -1): void {
    const indexes = enabledIndexes.value;

    if (!indexes.length) {
      activeIndex.value = 0;
      return;
    }

    const currentPosition = indexes.indexOf(activeIndex.value);

    if (currentPosition === -1) {
      activeIndex.value = direction === 1 ? indexes[0] : indexes[indexes.length - 1];
      return;
    }

    const nextPosition = (currentPosition + direction + indexes.length) % indexes.length;

    activeIndex.value = indexes[nextPosition];
  }

  function getActiveOption(): OptionItem | null {
    const option = filteredOptions.value[activeIndex.value];

    return option && !option.disabled ? option : null;
  }

  return {
    isOpen,
    search,
    activeIndex,
    selectedOption,
    filteredOptions,
    open,
    close,
    toggle,
    setSearch,
    moveActive,
    getActiveOption,
  };
}
