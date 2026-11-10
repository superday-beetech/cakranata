<!-- <script setup>
import { computed } from "vue";
import { useRoute } from "vue-router";

import AuthLayout from "@/layouts/AuthLayout.vue";
import BackendLayout from "@/layouts/BackendLayout.vue";

const route = useRoute();

const layouts = {
  auth: AuthLayout,
  backend: BackendLayout,
};

const layout = computed(() => {
  return layouts[route.meta.layout] || BackendLayout;
});
</script>

<template>
  <RouterView v-slot="{ Component }">
    <component :is="layout">
      <component :is="Component" />
    </component>
  </RouterView>
</template>
 -->

<script setup lang="ts">
import { computed } from "vue";
import { useRoute } from "vue-router";

import AuthLayout from "@/layouts/AuthLayout.vue";
import BackendLayout from "@/layouts/BackendLayout.vue";

const route = useRoute();

const currentLayout = computed(() => {
  return route.meta.layout === "backend" ? BackendLayout : AuthLayout;
});
</script>

<template>
  <component :is="currentLayout">
    <RouterView />
  </component>
</template>
