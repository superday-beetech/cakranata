<script setup lang="ts">
import { computed, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import PrintTemplateList from "./PrintTemplateList.vue";
import PrintTemplateDesigner from "./PrintTemplateDesigner.vue";
import PrintTemplateShow from "./PrintTemplateShow.vue";

const route = useRoute();
const router = useRouter();

const mode = computed(() => String(route.query.mode ?? "list"));
const id = computed<number | null>(() => {
  const v = route.query.id;
  const n = v ? Number(v) : null;
  return n && !isNaN(n) ? n : null;
});

watch(
  () => [mode.value, id.value] as const,
  ([m, i]) => {
    if ((m === "edit" || m === "show") && !i) {
      router.replace({ path: route.path });
    }
  },
  { immediate: true },
);
</script>

<template>
  <PrintTemplateList v-if="mode === 'list'" />
  <PrintTemplateDesigner v-else-if="mode === 'create' || mode === 'edit'" :key="`designer-${id ?? 'new'}`" :id="id" />
  <PrintTemplateShow v-else-if="mode === 'show'" :key="`show-${id}`" :id="id" />
  <div v-else class="container-fluid py-5 text-center">
    <h4>Mode tidak dikenal: {{ mode }}</h4>
    <router-link :to="{ path: route.path }" class="btn btn-primary mt-3">Kembali ke Daftar</router-link>
  </div>
</template>
