<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import { useMenuStore } from "@/stores/menu";
import type { MenuItem } from "@/types/menu";

const emit = defineEmits<{
  close: [];
}>();

const router = useRouter();
const menuStore = useMenuStore();

const keyword = ref("");
const searchInput = ref<HTMLInputElement | null>(null);

const allMenus = computed<MenuItem[]>(() => {
  const result: MenuItem[] = [];

  function collect(items: MenuItem[]) {
    for (const item of items) {
      result.push(item);

      if (item.children?.length) {
        collect(item.children);
      }
    }
  }

  collect(menuStore.menus.sidebar);
  collect(menuStore.menus.apps);
  collect(menuStore.menus.quick_links);
  collect(menuStore.menus.topbar);

  // Hilangkan duplikasi berdasarkan ID.
  return [...new Map(result.map((item) => [item.id, item])).values()];
});

const results = computed(() => {
  const query = keyword.value.trim().toLowerCase();

  if (!query) return [];

  return allMenus.value
    .filter((item) => {
      const title = item.title?.toLowerCase() ?? "";
      const url = item.url?.toLowerCase() ?? "";

      return title.includes(query) || url.includes(query);
    })
    .slice(0, 10);
});

function navigate(item: MenuItem) {
  if (!item.url) return;

  router.push(item.url.startsWith("/") ? item.url : `/${item.url}`);
  emit("close");
}

function handleKeydown(event: KeyboardEvent) {
  if (event.key === "Escape") {
    emit("close");
  }
}

onMounted(async () => {
  await nextTick();
  searchInput.value?.focus();

  window.addEventListener("keydown", handleKeydown);
});

onUnmounted(() => {
  window.removeEventListener("keydown", handleKeydown);
});
</script>

<template>
  <div class="p-3">
    <div class="input-group mb-3">
      <span class="input-group-text bg-transparent">
        <i class="ti ti-search fs-5"></i>
      </span>

      <input ref="searchInput" v-model="keyword" type="search" class="form-control" placeholder="Cari halaman atau menu..." autocomplete="off" />

      <button class="btn btn-outline-secondary" type="button" @click="emit('close')">
        <i class="ti ti-x"></i>
      </button>
    </div>

    <div v-if="!keyword.trim()" class="text-center py-5 text-muted">
      <i class="ti ti-search fs-1 d-block mb-2"></i>
      <div class="fw-semibold">Pencarian Global</div>
      <small>Ketik nama halaman atau menu yang ingin dicari.</small>
    </div>

    <div v-else-if="results.length">
      <div class="small text-muted mb-2">{{ results.length }} hasil ditemukan</div>

      <div class="list-group list-group-flush">
        <button v-for="item in results" :key="item.id" type="button" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3" @click="navigate(item)">
          <span class="avatar avatar-sm rounded bg-light-primary text-primary d-flex align-items-center justify-content-center">
            <i :class="item.icon || 'ti ti-file'"></i>
          </span>

          <span class="flex-grow-1 text-start">
            <span class="d-block fw-semibold">{{ item.title }}</span>
            <small class="text-muted">{{ item.url }}</small>
          </span>

          <i class="ti ti-arrow-up-right text-muted"></i>
        </button>
      </div>
    </div>

    <div v-else class="text-center py-5 text-muted">
      <i class="ti ti-search-off fs-1 d-block mb-2"></i>
      <div class="fw-semibold">Tidak ditemukan</div>
      <small>Coba gunakan kata kunci lainnya.</small>
    </div>
  </div>
</template>
