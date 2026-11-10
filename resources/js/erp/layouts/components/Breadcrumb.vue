<script setup>
import { computed } from "vue";
import { useRoute, RouterLink } from "vue-router";

const route = useRoute();

const breadcrumbs = computed(() => {
  const matched = route.matched.filter((item) => item.meta?.title && item.meta?.breadcrumb !== false);

  return matched.map((item) => ({
    title: item.meta.title,
    url: item.path,
  }));
});
</script>

<template>
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item">
        <RouterLink to="/">Home</RouterLink>
      </li>

      <li v-for="(item, index) in breadcrumbs" :key="item.url" class="breadcrumb-item" :class="{ active: index === breadcrumbs.length - 1 }" :aria-current="index === breadcrumbs.length - 1 ? 'page' : undefined">
        <RouterLink v-if="index < breadcrumbs.length - 1" :to="item.url">
          {{ item.title }}
        </RouterLink>

        <span v-else>{{ item.title }}</span>
      </li>
    </ol>
  </nav>
</template>
