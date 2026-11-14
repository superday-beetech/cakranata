<script setup lang="ts">
import { ref, onMounted, onUnmounted } from "vue";

const isOnline = ref<boolean>(navigator.onLine);
const showBanner = ref<boolean>(!navigator.onLine); // Muncul otomatis jika saat load awal sudah offline
let hideTimer: number | null = null;

const handleOnline = () => {
  isOnline.value = true;
  showBanner.value = true;

  // Jika kembali online, sembunyikan banner secara otomatis setelah 3 detik
  if (hideTimer) clearTimeout(hideTimer);
  hideTimer = window.setTimeout(() => {
    showBanner.value = false;
  }, 3000);
};

const handleOffline = () => {
  isOnline.value = false;
  showBanner.value = true; // Selalu tampilkan banner merah saat offline
  if (hideTimer) clearTimeout(hideTimer);
};

onMounted(() => {
  window.addEventListener("online", handleOnline);
  window.addEventListener("offline", handleOffline);
});

onUnmounted(() => {
  window.removeEventListener("online", handleOnline);
  window.removeEventListener("offline", handleOffline);
  if (hideTimer) clearTimeout(hideTimer);
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
    >
      <!-- Indikator Titik Berdenyut -->
      <span class="inline-block w-2.5 h-2.5 rounded-full" :class="isOnline ? 'bg-emerald-200 animate-ping' : 'bg-rose-200 animate-pulse'"></span>

      <!-- Pesan Dinamis -->
      <span>
        {{ isOnline ? "Koneksi internet kembali terhubung. Menyinkronkan data..." : "Anda sedang offline. Perubahan disimpan secara lokal (Offline-First Mode)." }}
      </span>
    </div>
  </transition>
</template>
