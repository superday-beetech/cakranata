<script setup>
import { onMounted, onBeforeUnmount } from "vue";
import { modalState, closeModal } from "./modalServices.js";
import ModalLoading from "./ModalLoading.vue";

defineEmits(["retry"]);

function handleKeydown(event) {
  if (event.key === "Escape" && modalState.visible) {
    closeModal();
  }
}

onMounted(() => {
  document.addEventListener("keydown", handleKeydown);
});

onBeforeUnmount(() => {
  document.removeEventListener("keydown", handleKeydown);
});
</script>
<template>
  <Teleport to="body">
    <div v-if="modalState.visible" class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" @keydown.esc="closeModal">
      <div
        class="modal-dialog"
        :class="[
          `modal-${modalState.size}`,
          {
            'modal-dialog-centered': modalState.centered,
            'modal-dialog-scrollable': modalState.scrollable,
          },
        ]"
      >
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              {{ modalState.title }}
            </h5>

            <button type="button" class="btn-close" aria-label="Tutup" @click="closeModal"></button>
          </div>

          <div class="modal-body">
            <ModalLoading v-if="modalState.loading" />

            <div v-else-if="modalState.error" class="text-center py-4" role="alert">
              <p>{{ modalState.error }}</p>

              <button class="btn btn-primary" @click="$emit('retry')">Coba lagi</button>
            </div>

            <component v-else-if="modalState.component" :is="modalState.component" v-bind="modalState.props" @close="closeModal" />
          </div>
        </div>
      </div>
    </div>

    <div v-if="modalState.visible" class="modal-backdrop fade show" @click="closeModal"></div>
  </Teleport>
</template>
