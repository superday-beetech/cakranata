<script setup lang="ts">
import { ref, watch, onMounted, nextTick, computed } from "vue";
import { useRoute, RouterLink } from "vue-router";

import { useMenuStore } from "@/stores/menu";
import MenuItem from "./MenuItem.vue";

import type { MenuItem as MenuItemType } from "@/types/menu";

const route = useRoute();
const menuStore = useMenuStore();

const expandedMenus = ref<Array<number | string>>([]);

const sidebarMenus = computed(() => menuStore.menus.sidebar);

function toggleMenu(id: number | string): void {
  const key = String(id);

  const exists = expandedMenus.value.some((value) => String(value) === key);

  expandedMenus.value = exists ? expandedMenus.value.filter((value) => String(value) !== key) : [...expandedMenus.value, id];
}

function normalizePath(url?: string | null): string {
  if (!url || url === "#" || url === "divider") {
    return "";
  }

  const value = String(url).trim();

  if (/^(https?:)?\/\//i.test(value)) {
    return "";
  }

  return ("/" + value.replace(/^\/+/, "")).replace(/\/+$/, "") || "/";
}

function isActiveUrl(url?: string | null): boolean {
  const path = normalizePath(url);

  if (!path) {
    return false;
  }

  return route.path.replace(/\/+$/, "") === path;
}

function hasActiveDescendant(item: MenuItemType): boolean {
  return (item.children || []).some((child) => isActiveUrl(child.url) || hasActiveDescendant(child));
}

function syncActiveParents(): void {
  const activeIds: Array<number | string> = [];

  function scan(items: MenuItemType[]): void {
    for (const item of items) {
      if (item.children?.length) {
        scan(item.children);

        if (hasActiveDescendant(item)) {
          activeIds.push(item.id);
        }
      }
    }
  }

  scan(sidebarMenus.value);

  expandedMenus.value = [...new Set([...expandedMenus.value, ...activeIds])];
}

async function retryMenus(): Promise<void> {
  try {
    await menuStore.load({ force: true });
    syncActiveParents();
  } catch {
    // Error sudah disimpan oleh MenuStore.
  }
}

watch(
  () => route.fullPath,
  async () => {
    syncActiveParents();
    await nextTick();
  },
);

watch(sidebarMenus, () => syncActiveParents(), {
  deep: true,
  immediate: true,
});

onMounted(() => {
  void menuStore.load().catch(() => {});
});
</script>

<template>
  <aside class="left-sidebar with-vertical">
    <!-- Brand -->
    <div class="brand-logo d-flex align-items-center justify-content-between">
      <RouterLink to="/dashboard" class="text-nowrap logo-img">
        <span class="fw-bold fs-5">ACN</span>
      </RouterLink>

      <a href="#" class="sidebartoggler ms-auto text-decoration-none fs-5 d-block d-xl-none" aria-label="Tutup sidebar">
        <i class="ti ti-x"></i>
      </a>
    </div>

    <!-- Sidebar navigation -->
    <nav class="sidebar-nav scroll-sidebar" data-simplebar>
      <ul id="sidebarnav">
        <!-- Loading -->
        <li v-if="menuStore.loading && !menuStore.loaded" class="px-3 py-3">
          <div class="d-flex align-items-center gap-2">
            <span class="spinner-border spinner-border-sm" role="status"></span>

            <span class="hide-menu">Memuat menu...</span>
          </div>
        </li>

        <!-- Error -->
        <li v-else-if="menuStore.error" class="px-3 py-3">
          <div class="text-danger mb-2">
            <i class="ti ti-alert-circle me-1"></i>
            Gagal memuat menu.
          </div>

          <button type="button" class="btn btn-sm btn-outline-primary" :disabled="menuStore.loading" @click="retryMenus">
            <i class="ti ti-refresh me-1"></i>
            Coba Lagi
          </button>
        </li>

        <!-- Empty -->
        <li v-else-if="menuStore.loaded && sidebarMenus.length === 0" class="px-3 py-3">
          <span class="text-muted hide-menu"> Belum ada menu tersedia. </span>
        </li>

        <!-- Menu items -->
        <template v-else>
          <MenuItem v-for="item in sidebarMenus" :key="item.id" :item="item" :level="0" :expanded-menus="expandedMenus" @toggle="toggleMenu" />
        </template>
      </ul>
    </nav>
  </aside>
</template>
