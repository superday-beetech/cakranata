<script setup lang="ts">
import { computed, onMounted, onUnmounted, watch } from "vue";
import { useOffcanvasStore } from "@/composables/useOffcanvas";

const offcanvas = useOffcanvasStore();

const placementClass = computed(() => `offcanvas-${offcanvas.placement}`);

const panelStyle = computed(() => {
  if (offcanvas.placement === "top" || offcanvas.placement === "bottom") {
    return { height: offcanvas.height };
  }

  return { width: offcanvas.width };
});

let previousOverflow = "";

watch(
  () => [offcanvas.isOpen, offcanvas.scroll] as const,
  ([isOpen, scroll]) => {
    if (isOpen && !scroll) {
      if (!previousOverflow) {
        previousOverflow = document.body.style.overflow;
      }

      document.body.style.overflow = "hidden";
    } else {
      document.body.style.overflow = previousOverflow;
      previousOverflow = "";
    }
  },
);

function handleKeydown(event: KeyboardEvent) {
  if (event.key === "Escape" && offcanvas.isOpen && offcanvas.keyboard) {
    offcanvas.close();
  }
}

function handleBackdrop() {
  if (offcanvas.backdrop === true) {
    offcanvas.close();
  }
}

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
    <!-- Backdrop -->
    <Transition name="offcanvas-fade">
      <div v-if="offcanvas.isOpen && offcanvas.backdrop" class="offcanvas-backdrop fade show" @click="handleBackdrop"></div>
    </Transition>

    <!-- Offcanvas -->
    <div v-show="offcanvas.isOpen" class="offcanvas" :class="[placementClass, { show: offcanvas.isOpen }]" tabindex="-1" role="dialog" aria-modal="true" :aria-label="offcanvas.title || 'Offcanvas'" :style="panelStyle">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title">
          {{ offcanvas.title }}
        </h5>

        <button type="button" class="btn-close" aria-label="Close" @click="offcanvas.close()"></button>
      </div>

      <div class="offcanvas-body">
        <component v-if="offcanvas.isOpen && offcanvas.component" :is="offcanvas.component" v-bind="offcanvas.props" @close="offcanvas.close()" />
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.offcanvas {
  z-index: 1045;
}

.offcanvas-backdrop {
  z-index: 1040;
}

.offcanvas-fade-enter-active,
.offcanvas-fade-leave-active {
  transition: opacity 0.3s ease;
}

.offcanvas-fade-enter-from,
.offcanvas-fade-leave-to {
  opacity: 0;
}
</style>
