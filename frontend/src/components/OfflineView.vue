<script setup lang="ts">
import { computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useOnlineStatus } from "@/composables/useOnlineStatus";

const route = useRoute();
const router = useRouter();
const { isOnline } = useOnlineStatus();

const redirectPath = computed(() => {
  const target = route.query.redirect;

  // Hindari redirect ke alamat eksternal.
  if (typeof target === "string" && target.startsWith("/") && !target.startsWith("//")) {
    return target;
  }

  return "/";
});

async function retry(): Promise<void> {
  if (!isOnline.value) return;

  await router.replace(redirectPath.value);
}

function goHome(): void {
  if (!isOnline.value) return;

  router.replace({
    name: "OfflineHome",
    query: {
      redirect: redirectPath.value,
    },
  });
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-50 px-5 py-10">
    <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
      <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full" :class="isOnline ? 'bg-emerald-50' : 'bg-rose-50'">
        <svg v-if="!isOnline" class="h-10 w-10 text-rose-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
          <path d="M2 8.5a15 15 0 0 1 20 0" />
          <path d="M5 12a10 10 0 0 1 14 0" />
          <path d="M8.5 15.5a5 5 0 0 1 7 0" />
          <path d="M12 19h.01" />
          <path d="m3 3 18 18" />
        </svg>

        <svg v-else class="h-10 w-10 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 7 10 17l-5-5" />
          <path d="M22 12a10 10 0 1 1-5.9-9.1" />
        </svg>
      </div>

      <h1 class="mb-3 text-2xl font-bold text-slate-800">
        {{ isOnline ? "Koneksi Tersedia" : "Anda Sedang Offline" }}
      </h1>

      <p class="mb-7 text-sm leading-6 text-slate-500">
        {{ isOnline ? "Koneksi jaringan terdeteksi. Silakan coba buka kembali halaman yang Anda tuju." : "Koneksi jaringan terputus. Halaman ini membutuhkan koneksi ke server untuk memuat data." }}
      </p>

      <div class="flex flex-col gap-3">
        <button type="button" :disabled="!isOnline" class="w-full rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-slate-300" @click="retry">
          Coba Kembali
        </button>

        <button
          type="button"
          :disabled="!isOnline"
          class="w-full rounded-lg border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
          @click="goHome"
        >
          Kembali ke Beranda
        </button>
      </div>

      <p class="mt-6 text-xs text-slate-400" role="status" aria-live="polite">
        Status jaringan:
        <span :class="isOnline ? 'text-emerald-600' : 'text-rose-600'">
          {{ isOnline ? "Terhubung" : "Terputus" }}
        </span>
      </p>
    </div>
  </div>
</template>
