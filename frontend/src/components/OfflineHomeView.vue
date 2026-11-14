<script setup lang="ts">
import { computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useOnlineStatus } from "@/composables/useOnlineStatus";

const route = useRoute();
const router = useRouter();
const { isOnline } = useOnlineStatus();

const redirectPath = computed(() => {
  const target = route.query.redirect;

  if (typeof target === "string" && target.startsWith("/") && !target.startsWith("//")) {
    return target;
  }

  return "/";
});

const connectionLabel = computed(() => (isOnline.value ? "Jaringan terdeteksi" : "Tidak ada koneksi jaringan"));

async function retryConnection(): Promise<void> {
  if (!isOnline.value) return;

  await router.replace(redirectPath.value);
}

function backToOfflineStatus(): void {
  router.replace({
    name: "Offline",
    query: {
      redirect: redirectPath.value,
    },
  });
}
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-5 py-10">
    <div class="mx-auto flex min-h-[80vh] w-full max-w-5xl flex-col">
      <!-- Header -->
      <header class="flex items-center justify-between gap-4">
        <div>
          <div class="text-xl font-bold tracking-wide text-slate-800">CAKRA</div>
          <p class="mt-1 text-xs text-slate-500">Integrated Management System</p>
        </div>

        <div class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-medium" :class="isOnline ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700'">
          <span class="h-2 w-2 rounded-full" :class="isOnline ? 'bg-emerald-500' : 'bg-amber-500'" />
          {{ connectionLabel }}
        </div>
      </header>

      <!-- Main content -->
      <section class="my-auto py-16">
        <div class="mx-auto max-w-2xl text-center">
          <div class="mx-auto mb-7 flex h-24 w-24 items-center justify-center rounded-3xl" :class="isOnline ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600'">
            <svg v-if="!isOnline" xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M2 8.82a15 15 0 0 1 20 0" />
              <path d="M5 12.86a10 10 0 0 1 9.5-2.1" />
              <path d="M8.5 16.43a5 5 0 0 1 3.5-1.43" />
              <path d="M12 20h.01" />
              <path d="m2 2 20 20" />
            </svg>

            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M5 12.55a11 11 0 0 1 14.08 0" />
              <path d="M8.53 16.11a6 6 0 0 1 6.95 0" />
              <path d="M12 20h.01" />
              <path d="M2 8.82a16 16 0 0 1 20 0" />
            </svg>
          </div>

          <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-slate-400">Offline Workspace</p>

          <h1 class="text-3xl font-bold text-slate-800 sm:text-4xl">Anda berada di Beranda Offline</h1>

          <p class="mx-auto mt-5 max-w-xl text-base leading-7 text-slate-500">Aplikasi sedang tidak dapat terhubung ke jaringan. Anda masih dapat melihat halaman ini, tetapi layanan yang membutuhkan server belum dapat digunakan.</p>

          <div class="mt-8 rounded-xl border border-slate-200 bg-white p-5 text-left">
            <div class="flex items-start gap-3">
              <div class="mt-0.5 text-amber-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.86a2 2 0 0 0-3.4 0Z" />
                </svg>
              </div>

              <div>
                <h2 class="text-sm font-semibold text-slate-800">Perhatian</h2>
                <p class="mt-1 text-sm leading-6 text-slate-500">Jangan menganggap transaksi telah tersimpan ketika koneksi terputus. Periksa status transaksi setelah koneksi kembali sebelum mengulang prosesnya.</p>
              </div>
            </div>
          </div>

          <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
            <button
              type="button"
              :disabled="!isOnline"
              @click="retryConnection"
              class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-40"
            >
              Coba Hubungkan Kembali
            </button>

            <button type="button" @click="backToOfflineStatus" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
              Status Offline
            </button>
          </div>
        </div>
      </section>

      <!-- Footer -->
      <footer class="border-t border-slate-200 pt-5 text-center">
        <p class="text-xs text-slate-400">CAKRA Integrated Management System</p>
        <p class="mt-1 text-xs text-slate-400">Offline Interface</p>
      </footer>
    </div>
  </main>
</template>
