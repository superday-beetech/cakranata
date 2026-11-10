<!-- <script setup lang="ts">
import { computed, defineAsyncComponent } from "vue";
import { useRoute } from "vue-router";

import { resolveDynamicPage } from "@/router/DynamicPageResolver";
import NotFoundView from "@/components/NotFoundView.vue";

const route = useRoute();

const PageComponent = computed(() => {
  const page = resolveDynamicPage(route.path);

  if (!page.component) {
    return NotFoundView;
  }

  return defineAsyncComponent(page.component as () => Promise<any>);
});
</script>

<template>
  <component :is="PageComponent" />
</template>
 -->

<script setup lang="ts">
import { computed, defineAsyncComponent } from "vue";
import { useRoute } from "vue-router";

import { resolveDynamicPage } from "@/router/DynamicPageResolver";
import NotFoundView from "@/components/NotFoundView.vue";

const route = useRoute();

const PageComponent = computed(() => {
  const loader = resolveDynamicPage(route.path);

  if (!loader) {
    return NotFoundView;
  }

  return defineAsyncComponent(loader);
});
</script>

<template>
  <component :is="PageComponent" />
</template>
