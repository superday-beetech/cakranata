<script setup lang="ts">
import { computed, defineAsyncComponent } from "vue";
import { useRoute } from "vue-router";
import NetworkBanner from "@/components/ui/NetworkBanner.vue";
const AuthLayout = defineAsyncComponent(() => import("@/layouts/AuthLayout.vue"));

const BackendLayout = defineAsyncComponent(() => import("@/layouts/BackendLayout.vue"));

const PosLayout = defineAsyncComponent(() => import("@/layouts/PosLayout.vue"));

const route = useRoute();

const currentLayout = computed(() => {
  switch (route.meta.layout) {
    case "backend":
      return BackendLayout;

    case "pos":
      return PosLayout;

    default:
      return AuthLayout;
  }
});
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-900 font-sans">
    <!-- Banner Indikator Jaringan Global -->
    <NetworkBanner />

    <!-- Konten Utama Aplikasi / Router View -->
    <!-- <main class="pt-10">
      <router-view />
    </main> -->
    <component :is="currentLayout">
      <RouterView />
    </component>
  </div>
</template>
