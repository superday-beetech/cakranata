<script setup lang="ts">
import { computed, defineAsyncComponent } from "vue";
import { useRoute } from "vue-router";

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
  <component :is="currentLayout">
    <RouterView />
  </component>
</template>
