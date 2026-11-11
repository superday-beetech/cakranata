<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, toRef } from "vue";
import { useOption, type OptionItem, type OptionValue } from "@/composables/useOption";

const props = withDefaults(
  defineProps<{
    modelValue: OptionValue;
    options: OptionItem[];
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    disabled?: boolean;
    clearable?: boolean;
    searchable?: boolean;
    name?: string;
    dropdownClass?: string;
  }>(),
  {
    placeholder: "Pilih data...",
    searchPlaceholder: "Cari nama atau kode...",
    emptyText: "Data tidak ditemukan",
    disabled: false,
    clearable: true,
    searchable: true,
    name: "",
    dropdownClass: "",
  },
);

const emit = defineEmits<{
  "update:modelValue": [value: OptionValue];
  change: [option: OptionItem | null];
}>();

const root = ref<HTMLElement | null>(null);
const input = ref<HTMLInputElement | null>(null);

const optionsRef = toRef(props, "options");
const modelValueRef = toRef(props, "modelValue");

const { isOpen, search, activeIndex, selectedOption, filteredOptions, open, close, toggle, setSearch, moveActive, getActiveOption } = useOption(optionsRef, modelValueRef);

const hasOptions = computed(() => filteredOptions.value.length > 0);

function selectOption(option: OptionItem): void {
  if (option.disabled) return;

  emit("update:modelValue", option.value);
  emit("change", option);
  close();
}

function clearSelection(): void {
  emit("update:modelValue", "");
  emit("change", null);
  close();
}

function handleInput(event: Event): void {
  const target = event.target as HTMLInputElement;

  if (!isOpen.value) {
    open();
  }

  setSearch(target.value);
}

function handleKeydown(event: KeyboardEvent): void {
  if (props.disabled) return;

  switch (event.key) {
    case "ArrowDown":
      event.preventDefault();

      if (!isOpen.value) {
        open();
      } else {
        moveActive(1);
      }
      break;

    case "ArrowUp":
      event.preventDefault();

      if (!isOpen.value) {
        open();
      } else {
        moveActive(-1);
      }
      break;

    case "Enter": {
      if (!isOpen.value) {
        event.preventDefault();
        open();
        break;
      }

      event.preventDefault();

      const option = getActiveOption();

      if (option) {
        selectOption(option);
      }
      break;
    }

    case "Escape":
      if (isOpen.value) {
        event.preventDefault();
        close();
      }
      break;

    case "Tab":
      close();
      break;
  }
}

function handleOutsideClick(event: PointerEvent): void {
  if (root.value && event.target instanceof Node && !root.value.contains(event.target)) {
    close();
  }
}

function focusSearch(): void {
  if (!props.searchable) return;

  requestAnimationFrame(() => input.value?.focus());
}

function handleToggle(): void {
  if (props.disabled) return;

  if (isOpen.value) {
    close();
  } else {
    open();
    focusSearch();
  }
}

onMounted(() => {
  document.addEventListener("pointerdown", handleOutsideClick);
});

onBeforeUnmount(() => {
  document.removeEventListener("pointerdown", handleOutsideClick);
});
</script>

<template>
  <div
    ref="root"
    class="option-select"
    :class="{
      'is-open': isOpen,
      'is-disabled': disabled,
      'has-value': selectedOption,
    }"
  >
    <div class="option-select__control" @click="handleToggle">
      <div class="option-select__main">
        <i class="ti ti-search option-select__search-icon" aria-hidden="true"></i>

        <input
          v-if="searchable && isOpen"
          ref="input"
          :value="search"
          type="text"
          class="option-select__input"
          :placeholder="searchPlaceholder"
          autocomplete="off"
          :disabled="disabled"
          @input="handleInput"
          @keydown="handleKeydown"
          @click.stop
        />

        <input v-else :value="selectedOption?.label ?? ''" type="text" class="option-select__input" :placeholder="placeholder" readonly :disabled="disabled" @keydown="handleKeydown" />
      </div>

      <div class="option-select__actions">
        <button v-if="clearable && selectedOption && !disabled" type="button" class="option-select__clear" title="Hapus pilihan" aria-label="Hapus pilihan" @click.stop="clearSelection">
          <i class="ti ti-x"></i>
        </button>

        <span class="option-select__separator"></span>

        <i class="ti option-select__arrow" :class="isOpen ? 'ti-chevron-up' : 'ti-chevron-down'"></i>
      </div>
    </div>

    <Transition name="option-dropdown">
      <div v-if="isOpen" class="option-select__dropdown" :class="dropdownClass">
        <div v-if="!hasOptions" class="option-select__empty">
          <i class="ti ti-database-off"></i>
          <span>{{ emptyText }}</span>
        </div>

        <div v-else class="option-select__list">
          <button
            v-for="(option, index) in filteredOptions"
            :key="String(option.value)"
            type="button"
            class="option-select__item"
            :class="{
              'is-active': activeIndex === index,
              'is-selected': String(modelValue) === String(option.value),
              'is-item-disabled': option.disabled,
            }"
            :disabled="option.disabled"
            @mouseenter="activeIndex = index"
            @click="selectOption(option)"
          >
            <span class="option-select__item-content">
              <span class="option-select__item-label">
                {{ option.label }}
              </span>

              <span v-if="option.description" class="option-select__item-description">
                {{ option.description }}
              </span>
            </span>

            <i v-if="String(modelValue) === String(option.value)" class="ti ti-check option-select__check"></i>
          </button>
        </div>

        <div v-if="filteredOptions.length" class="option-select__footer">
          <span> {{ filteredOptions.length }} pilihan </span>

          <span>
            <kbd>↑</kbd>
            <kbd>↓</kbd>
            navigasi
            <kbd>Enter</kbd>
            pilih
          </span>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.option-select {
  position: relative;
  width: 100%;
  min-width: 0;
  z-index: 1;
  font-size: 0.875rem;
  color: var(--bs-body-color, #344054);
}

.option-select.is-open {
  z-index: 1090;
}

.option-select__dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  z-index: 1091;
}

.option-select__control {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 40px;
  width: 100%;
  padding: 0 11px;
  background: var(--bs-body-bg, #fff);
  border: 1px solid var(--bs-border-color, #dfe3e8);
  border-radius: 7px;
  cursor: pointer;
  transition:
    border-color 0.18s ease,
    box-shadow 0.18s ease,
    background 0.18s ease;
}

.option-select__control:hover {
  border-color: #9ca8b8;
}

.option-select.is-open .option-select__control {
  border-color: var(--bs-primary, #5d87ff);
  box-shadow: 0 0 0 3px rgba(93, 135, 255, 0.12);
}

.option-select.is-disabled .option-select__control {
  background: var(--bs-secondary-bg, #f2f4f7);
  cursor: not-allowed;
  opacity: 0.65;
}

.option-select__main {
  display: flex;
  align-items: center;
  flex: 1;
  min-width: 0;
  gap: 9px;
}

.option-select__search-icon {
  flex-shrink: 0;
  color: #98a2b3;
  font-size: 16px;
}

.option-select__input {
  display: block;
  width: 100%;
  min-width: 0;
  height: 38px;
  padding: 0;
  border: 0;
  outline: 0;
  color: inherit;
  background: transparent;
  box-shadow: none;
  font: inherit;
  cursor: inherit;
}

.option-select__input::placeholder {
  color: #98a2b3;
  opacity: 1;
}

.option-select__input:focus {
  outline: none;
  box-shadow: none;
}

.option-select__actions {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  gap: 8px;
  padding-left: 8px;
}

.option-select__clear {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  color: #98a2b3;
  background: transparent;
  cursor: pointer;
}

.option-select__clear:hover {
  color: #dc3545;
  background: #fff0f0;
}

.option-select__separator {
  width: 1px;
  height: 20px;
  background: var(--bs-border-color, #e5e7eb);
}

.option-select__arrow {
  color: #98a2b3;
  font-size: 17px;
  transition: transform 0.18s ease;
}

.option-select__dropdown {
  position: absolute;
  z-index: 1080;
  top: calc(100% + 6px);
  right: 0;
  left: 0;
  overflow: hidden;
  background: var(--bs-body-bg, #fff);
  border: 1px solid var(--bs-border-color, #e5e7eb);
  border-radius: 9px;
  box-shadow:
    0 12px 28px rgba(16, 24, 40, 0.12),
    0 2px 5px rgba(16, 24, 40, 0.05);
}

.option-select__list {
  max-height: 250px;
  overflow-y: auto;
  padding: 5px;
  overscroll-behavior: contain;
}

.option-select__item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  min-height: 43px;
  gap: 12px;
  padding: 8px 10px;
  text-align: left;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: inherit;
  cursor: pointer;
  transition: background 0.12s ease;
}

.option-select__item:hover,
.option-select__item.is-active {
  background: var(--bs-tertiary-bg, #f5f7fa);
}

.option-select__item.is-selected {
  color: var(--bs-primary, #5d87ff);
  background: rgba(93, 135, 255, 0.08);
}

.option-select__item.is-item-disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.option-select__item-content {
  display: flex;
  flex-direction: column;
  min-width: 0;
  gap: 2px;
}

.option-select__item-label {
  overflow: hidden;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.option-select__item-description {
  overflow: hidden;
  color: #98a2b3;
  font-size: 0.75rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.option-select__check {
  flex-shrink: 0;
  font-size: 17px;
}

.option-select__empty {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 7px;
  min-height: 105px;
  padding: 16px;
  color: #98a2b3;
  text-align: center;
}

.option-select__empty i {
  font-size: 24px;
}

.option-select__footer {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  padding: 8px 11px;
  border-top: 1px solid var(--bs-border-color, #e5e7eb);
  color: #98a2b3;
  background: var(--bs-tertiary-bg, #fafbfc);
  font-size: 0.7rem;
}

.option-select__footer kbd {
  padding: 1px 4px;
  border: 1px solid #d0d5dd;
  border-radius: 3px;
  background: #fff;
  color: #667085;
  font-size: 0.65rem;
}

.option-dropdown-enter-active,
.option-dropdown-leave-active {
  transition:
    opacity 0.14s ease,
    transform 0.14s ease;
  transform-origin: top;
}

.option-dropdown-enter-from,
.option-dropdown-leave-to {
  opacity: 0;
  transform: translateY(-5px);
}

@media (max-width: 576px) {
  .option-select__footer {
    font-size: 0.65rem;
  }
}
</style>
