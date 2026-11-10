<script setup>
import { ref } from "vue";

const isOpen = ref(false);
const title = ref("");

function open(options = {}) {
  title.value = options.title || "Panel";
  isOpen.value = true;
}

function close() {
  isOpen.value = false;
}

defineExpose({ open, close });
</script>

<template>
  <Teleport to="body">
    <div v-if="isOpen" class="offcanvas-backdrop fade show" @click="close"></div>

    <div class="offcanvas offcanvas-end show" :class="{ 'd-block': isOpen }" tabindex="-1" role="dialog" :aria-label="title">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title">{{ title }}</h5>
        <button type="button" class="btn-close" aria-label="Tutup" @click="close"></button>
      </div>

      <div class="offcanvas-body">
        <slot />
      </div>
    </div>
  </Teleport>
</template>
