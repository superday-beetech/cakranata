<script setup lang="ts">
import { ref, watch, onMounted, computed } from "vue";
import { useRoute, RouterLink } from "vue-router";

import { useMenuStore } from "@/stores/menu";
import MenuItem from "./MenuItem.vue";

import type { MenuItem as MenuItemType } from "@/types/menu";

const route = useRoute();
const menuStore = useMenuStore();

const expandedMenus = ref<Array<number | string>>([]);

const sidebarMenus = computed(() => menuStore.menus.sidebar);

/**
 * Toggle submenu.
 * Hanya satu jalur menu yang terbuka.
 */
function toggleMenu(id: number | string, ancestors: Array<number | string> = []): void {
  const isCurrentlyExpanded = String(expandedMenus.value[expandedMenus.value.length - 1]) === String(id);

  expandedMenus.value = isCurrentlyExpanded ? [...ancestors] : [...ancestors, id];
}

/**
 * Normalize internal URL.
 */
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

/**
 * Check active route.
 */
function isActiveUrl(url?: string | null): boolean {
  const path = normalizePath(url);
  const currentPath = route.path.replace(/\/+$/, "") || "/";

  return !!path && currentPath === path;
}

/**
 * Find active menu path.
 */
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

/**
 * Automatically expand active menu path.
 */
function syncActiveParents(): void {
  expandedMenus.value = findActivePath(sidebarMenus.value) ?? [];
}

/**
 * Close sidebar on mobile.
 * Modernize uses show-sidebar on #main-wrapper.
 */
function closeSidebar(): void {
  document.getElementById("main-wrapper")?.classList.remove("show-sidebar");
}

/**
 * Menu navigation completed.
 */
function handleNavigate(): void {
  closeSidebar();
}

/**
 * Retry loading menus.
 */
async function retryMenus(): Promise<void> {
  try {
    await menuStore.load({ force: true });
    syncActiveParents();
  } catch {
    // Error sudah ditangani oleh MenuStore.
  }
}

/**
 * Watch route changes.
 */
watch(
  () => route.fullPath,
  () => {
    syncActiveParents();
    closeSidebar();
  },
);

/**
 * Watch menu changes.
 */
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

/**
 * Initial menu loading.
 */
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

      <a href="javascript:void(0)" class="sidebartoggler ms-auto text-decoration-none fs-5 d-block d-xl-none" aria-label="Tutup sidebar" @click.prevent="closeSidebar">
        <i class="ti ti-x"></i>
      </a>
    </div>

    <!-- Sidebar Navigation -->
    <nav class="sidebar-nav scroll-sidebar" data-simplebar>
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
          <MenuItem v-for="item in sidebarMenus" :key="item.id" :item="item" :level="0" :expanded-menus="expandedMenus" @toggle="toggleMenu" @navigate="handleNavigate" />
        </template>
      </ul>
    </nav>
  </aside>
</template>
