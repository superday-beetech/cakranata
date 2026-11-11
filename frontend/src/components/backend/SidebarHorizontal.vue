<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";

import { useMenuStore } from "@/stores/menu";
import SidebarHorizontalItem from "./SidebarHorizontalItem.vue";

import type { MenuItem as MenuItemType } from "@/types/menu";

const route = useRoute();
const menuStore = useMenuStore();

// State
const expandedMenus = ref<Array<number | string>>([]);
const suppressHover = ref(false);

// Menu data
const sidebarMenus = computed(() => menuStore.menus.sidebar);

// Normalize URL
function normalizePath(url?: string | null): string {
  if (!url || url === "#" || url === "divider") {
    return "";
  }

  const value = url.trim();

  if (/^(https?:)?\/\//i.test(value)) {
    return "";
  }

  const path = value.split(/[?#]/)[0];

  return ("/" + path.replace(/^\/+/, "")).replace(/\/+$/, "") || "/";
}

// Active URL
function isActiveUrl(url?: string | null): boolean {
  const path = normalizePath(url);
  const currentPath = route.path.replace(/\/+$/, "") || "/";

  return !!path && currentPath === path;
}

// Find active menu hierarchy
function findActivePath(items: MenuItemType[], ancestors: Array<number | string> = []): Array<number | string> | null {
  for (const item of items) {
    if (isActiveUrl(item.url)) {
      return item.children?.length ? [...ancestors, item.id] : ancestors;
    }

    if (item.children?.length) {
      const found = findActivePath(item.children, [...ancestors, item.id]);

      if (found !== null) {
        return found;
      }
    }
  }

  return null;
}

// Synchronize active hierarchy
function syncActiveParents(): void {
  expandedMenus.value = findActivePath(sidebarMenus.value) ?? [];
}

// Toggle menu hierarchy
function toggleMenu(id: number | string, ancestors: Array<number | string> = []): void {
  // User interaction re-enables hover
  suppressHover.value = false;

  const currentPath = expandedMenus.value.map(String);
  const targetPath = [...ancestors, id].map(String);

  // Check whether the clicked menu is already open,
  // including when one of its descendants is expanded.
  const isAlreadyOpen = currentPath.length >= targetPath.length && targetPath.every((value, index) => currentPath[index] === value);

  // Close the clicked branch, or open its path.
  expandedMenus.value = isAlreadyOpen ? [...ancestors] : [...ancestors, id];
}

// Navigation completed / selected
function handleNavigate(): void {
  suppressHover.value = true;
  expandedMenus.value = [];
}

// Restore hover after leaving sidebar
function enableHover(): void {
  suppressHover.value = false;
}

// Retry loading menus
async function retryMenus(): Promise<void> {
  try {
    await menuStore.load({ force: true });
    syncActiveParents();
  } catch {
    // Error is handled by MenuStore.
  }
}

// Close dropdowns after route changes
watch(
  () => route.fullPath,
  () => {
    suppressHover.value = true;
    expandedMenus.value = [];
  },
);

// Synchronize when menu data changes
watch(
  sidebarMenus,
  () => {
    syncActiveParents();
  },
  {
    deep: true,
    immediate: true,
  },
);

// Initial menu loading
onMounted(() => {
  void menuStore.load().catch(() => {});
});
</script>

<template>
  <aside class="left-sidebar with-horizontal">
    <!-- Sidebar scroll -->
    <div>
      <!-- Sidebar navigation -->
      <nav id="sidebarnavh" class="sidebar-nav scroll-sidebar container-fluid" :class="{ 'menu-closed': suppressHover }" @pointerleave="enableHover">
        <ul id="sidebarnav">
          <!-- Loading -->
          <li v-if="menuStore.loading && !menuStore.loaded" class="px-3 py-3">
            <div class="d-flex align-items-center gap-2">
              <span class="spinner-border spinner-border-sm" role="status"></span>

              <span class="hide-menu"> Memuat menu... </span>
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

          <!-- Dynamic Menu -->
          <template v-else>
            <SidebarHorizontalItem v-for="item in sidebarMenus" :key="item.id" :item="item" :level="0" :expanded-menus="expandedMenus" @toggle="toggleMenu" @navigate="handleNavigate" />
          </template>
        </ul>
      </nav>
    </div>
  </aside>
</template>

<style scoped>
#sidebarnavh.menu-closed :deep(.sidebar-item:hover > ul.collapse) {
  display: none !important;
}
</style>
