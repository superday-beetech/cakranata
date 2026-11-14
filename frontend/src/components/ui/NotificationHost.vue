<script setup lang="ts">
import { onMounted, onBeforeUnmount } from "vue";
import { useNotificationStore } from "@/composables/useNotification";

const notification = useNotificationStore();
let lastResourceError = 0;

function handleResourceError(event: Event) {
  const target = event.target;

  if (!(target instanceof HTMLScriptElement) && !(target instanceof HTMLLinkElement)) {
    return;
  }

  const url = target instanceof HTMLScriptElement ? target.src : target.href;

  if (!url) return;

  window.dispatchEvent(
    new CustomEvent("app:resource-error", {
      detail: {
        url,
        resource: target instanceof HTMLScriptElement ? "JavaScript" : "CSS",
      },
    }),
  );
}
const icons = {
  success: "ti ti-check",
  error: "ti ti-alert-circle",
  warning: "ti ti-alert-triangle",
  info: "ti ti-info-circle",
};

onMounted(() => {
  window.addEventListener("error", handleResourceError, true);
});

onBeforeUnmount(() => {
  window.removeEventListener("error", handleResourceError, true);
});
</script>

<template>
  <div class="notification-container">
    <TransitionGroup name="notification">
      <div v-for="item in notification.items" :key="item.id" class="notification-item" :class="`notification-${item.type}`">
        <div class="notification-icon">
          <i :class="icons[item.type]"></i>
        </div>

        <div class="notification-content">
          <strong>{{ item.title }}</strong>
          <p>{{ item.message }}</p>
        </div>

        <button v-if="item.closable" type="button" class="notification-close" aria-label="Tutup notifikasi" @click="notification.remove(item.id)">&times;</button>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.notification-container {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 99999;
  width: min(380px, calc(100vw - 40px));
  pointer-events: none;
}

.notification-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 15px;
  margin-bottom: 10px;
  background: var(--bs-body-bg, #fff);
  color: var(--bs-body-color, #333);
  border: 1px solid var(--bs-border-color, #eee);
  border-radius: 8px;
  box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
  pointer-events: auto;
}

.notification-icon {
  font-size: 22px;
}

.notification-success .notification-icon {
  color: #13deb9;
}

.notification-error .notification-icon {
  color: #fa896b;
}

.notification-warning .notification-icon {
  color: #ffae1f;
}

.notification-info .notification-icon {
  color: #539bff;
}

.notification-content {
  flex: 1;
  min-width: 0;
}

.notification-content strong {
  display: block;
  margin-bottom: 4px;
}

.notification-content p {
  margin: 0;
  font-size: 13px;
  overflow-wrap: anywhere;
}

.notification-close {
  border: 0;
  background: transparent;
  color: inherit;
  font-size: 22px;
  line-height: 1;
  cursor: pointer;
  opacity: 0.6;
}

.notification-close:hover {
  opacity: 1;
}

.notification-enter-active,
.notification-leave-active {
  transition: all 0.25s ease;
}

.notification-enter-from,
.notification-leave-to {
  opacity: 0;
  transform: translateX(25px);
}

.notification-leave-active {
  position: absolute;
  width: 100%;
}
</style>
