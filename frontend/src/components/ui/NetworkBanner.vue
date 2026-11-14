<script setup lang="ts">
import { ref, onMounted, onUnmounted } from "vue";

const isOnline = ref<boolean>(navigator.onLine);
const showBanner = ref<boolean>(!navigator.onLine);

let hideTimer: number | null = null;

function clearHideTimer(): void {
  if (hideTimer !== null) {
    window.clearTimeout(hideTimer);
    hideTimer = null;
  }
}

function handleOnline(): void {
  isOnline.value = true;
  showBanner.value = true;

  clearHideTimer();

  hideTimer = window.setTimeout(() => {
    showBanner.value = false;
    hideTimer = null;
  }, 3000);
}

function handleOffline(): void {
  isOnline.value = false;
  showBanner.value = true;

  clearHideTimer();
}

onMounted(() => {
  window.addEventListener("online", handleOnline);
  window.addEventListener("offline", handleOffline);
});

onUnmounted(() => {
  window.removeEventListener("online", handleOnline);
  window.removeEventListener("offline", handleOffline);

  clearHideTimer();
});
</script>

<template>
  <transition
    enter-active-class="transform transition duration-300 ease-out"
    enter-from-class="-translate-y-full opacity-0"
    enter-to-class="translate-y-0 opacity-100"
    leave-active-class="transform transition duration-200 ease-in"
    leave-from-class="translate-y-0 opacity-100"
    leave-to-class="-translate-y-full opacity-0"
  >
    <div
      v-if="showBanner"
      class="fixed top-0 left-0 right-0 z-50 px-4 py-2.5 text-center text-sm font-medium shadow-md flex items-center justify-center gap-2 transition-colors duration-300"
      :class="isOnline ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white'"
      role="status"
      aria-live="polite"
    >
      <span class="inline-block w-2.5 h-2.5 rounded-full" :class="isOnline ? 'bg-emerald-200' : 'bg-rose-200 animate-pulse'" />

      <span>
        {{ isOnline ? "Koneksi jaringan tersedia kembali. Status server belum dipastikan." : "Koneksi jaringan terputus. Perubahan belum tentu tersimpan." }}
      </span>
    </div>
  </transition>
</template>
