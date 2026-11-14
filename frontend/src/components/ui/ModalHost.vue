<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, watch } from "vue";

import { useModalStore } from "@/composables/useModal";

const modalStore = useModalStore();

const dialogClasses = computed(() => ({
  "modal-sm": modalStore.size === "sm",
  "modal-lg": modalStore.size === "lg",
  "modal-xl": modalStore.size === "xl",
  "modal-fullscreen": modalStore.size === "fullscreen",
  "modal-dialog-centered": modalStore.centered,
  "modal-dialog-scrollable": modalStore.scrollable,
}));

let previousOverflow = "";
let previousFocus: HTMLElement | null = null;

const focusableSelector = ["a[href]", "button:not([disabled])", "input:not([disabled])", "select:not([disabled])", "textarea:not([disabled])", '[tabindex]:not([tabindex="-1"])'].join(",");

function getDialog() {
  return document.querySelector<HTMLElement>(".acn-modal-dialog");
}

function focusFirstElement() {
  const dialog = getDialog();

  if (!dialog) return;

  const elements = dialog.querySelectorAll<HTMLElement>(focusableSelector);

  (elements[0] ?? dialog).focus();
}

function handleKeydown(event: KeyboardEvent) {
  if (!modalStore.isOpen) return;

  if (event.key === "Escape") {
    if (modalStore.keyboard) {
      event.preventDefault();
      modalStore.close();
    }

    return;
  }

  if (event.key !== "Tab") return;

  const dialog = getDialog();

  if (!dialog) return;

  const elements = Array.from(dialog.querySelectorAll<HTMLElement>(focusableSelector));

  if (!elements.length) {
    event.preventDefault();
    dialog.focus();
    return;
  }

  const first = elements[0];
  const last = elements[elements.length - 1];

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}

function handleBackdrop() {
  if (modalStore.backdrop === true) {
    modalStore.close();
  }
}

watch(
  () => modalStore.isOpen,
  async (isOpen) => {
    if (isOpen) {
      previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;

      previousOverflow = document.body.style.overflow;
      document.body.style.overflow = "hidden";

      await nextTick();
      focusFirstElement();
    } else {
      document.body.style.overflow = previousOverflow;
      previousOverflow = "";

      await nextTick();
      previousFocus?.focus();
      previousFocus = null;
    }
  },
);

onMounted(() => {
  document.addEventListener("keydown", handleKeydown);
});

onUnmounted(() => {
  document.removeEventListener("keydown", handleKeydown);
  document.body.style.overflow = previousOverflow;
});
</script>

<template>
  <Teleport to="body">
    <Transition name="acn-modal-backdrop">
      <div v-if="modalStore.isOpen && modalStore.backdrop" class="modal-backdrop fade show" @click="handleBackdrop"></div>
    </Transition>

    <Transition name="acn-modal">
      <div v-if="modalStore.isOpen" class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" :aria-label="modalStore.title || 'Dialog'" @click.self="handleBackdrop">
        <div class="modal-dialog acn-modal-dialog" :class="dialogClasses" role="document" tabindex="-1">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                {{ modalStore.title }}
              </h5>

              <button type="button" class="btn-close" aria-label="Close" @click="modalStore.close()"></button>
            </div>

            <div class="modal-body">
              <component v-if="modalStore.component" :is="modalStore.component" v-bind="modalStore.props" @select="modalStore.onSelect" @close="modalStore.close()" />
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.acn-modal-backdrop-enter-active,
.acn-modal-backdrop-leave-active {
  transition: opacity 0.15s linear;
}

.acn-modal-backdrop-enter-from,
.acn-modal-backdrop-leave-to {
  opacity: 0;
}

.acn-modal-enter-active,
.acn-modal-leave-active {
  transition: opacity 0.15s linear;
}

.acn-modal-enter-active .modal-dialog,
.acn-modal-leave-active .modal-dialog {
  transition: transform 0.2s ease-out;
}

.acn-modal-enter-from,
.acn-modal-leave-to {
  opacity: 0;
}

.acn-modal-enter-from .modal-dialog {
  transform: translateY(-20px);
}

.acn-modal-leave-to .modal-dialog {
  transform: translateY(-10px);
}
</style>
