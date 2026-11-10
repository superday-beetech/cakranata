<script setup lang="ts">
import { computed, defineAsyncComponent } from "vue";
import { useRoute } from "vue-router";

// Layout dimuat sesuai route yang sedang aktif.
// Halaman auth tidak perlu mengunduh dependensi layout backend.
const AuthLayout = defineAsyncComponent(() => import("@/layouts/AuthLayout.vue"));
const BackendLayout = defineAsyncComponent(() => import("@/layouts/BackendLayout.vue"));

const route = useRoute();

const currentLayout = computed(() => (route.meta.layout === "backend" ? BackendLayout : AuthLayout));
</script>

<template>
  <component :is="currentLayout">
    <RouterView />
  </component>
</template>
