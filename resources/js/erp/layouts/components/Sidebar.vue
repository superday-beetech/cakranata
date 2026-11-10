<script setup>
import { ref, watch, nextTick } from "vue";
import { useRoute } from "vue-router";
import { useMenuStore } from "../../stores/menu.js";
import MenuItem from "./MenuItem.vue";

const route = useRoute();
const menuStore = useMenuStore();

const expandedMenus = ref([]);

function toggleMenu(id) {
  expandedMenus.value = expandedMenus.value.includes(id) ? expandedMenus.value.filter((value) => value !== id) : [...expandedMenus.value, id];
}

function normalizePath(url) {
  if (!url || url === "#" || url === "divider") return "";

  const value = String(url).trim();

  if (/^(https?:)?\/\//i.test(value)) return "";

  return ("/" + value.replace(/^\/+/, "")).replace(/\/+$/, "") || "/";
}

function isActiveUrl(url) {
  const path = normalizePath(url);
  if (!path) return false;

  return route.path.replace(/\/+$/, "") === path;
}

function hasActiveDescendant(item) {
  return (item.children || []).some((child) => isActiveUrl(child.url) || hasActiveDescendant(child));
}

function syncActiveParents() {
  const activeIds = [];

  function scan(items) {
    for (const item of items || []) {
      if (item.children?.length) {
        scan(item.children);

        if (hasActiveDescendant(item)) {
          activeIds.push(item.id);
        }
      }
    }
  }

  scan(menuStore.menus);

  expandedMenus.value = [...new Set([...expandedMenus.value, ...activeIds])];
}

function closeMobileSidebar() {
  const width = window.innerWidth > 0 ? window.innerWidth : screen.width;

  // Perilaku sidebar desktop tidak diubah.
  if (width >= 1300) return;

  const wrapper = document.getElementById("main-wrapper");

  if (wrapper) {
    wrapper.classList.remove("show-sidebar");
  }

  document.querySelectorAll(".sidebarmenu").forEach((el) => {
    el.classList.remove("close");
  });
}

async function retryMenus() {
  try {
    await menuStore.load({ force: true });
    syncActiveParents();
  } catch (error) {
    console.error("Gagal memuat ulang menu:", error);
  }
}

watch(
  () => route.fullPath,
  async () => {
    syncActiveParents();

    await nextTick();
    closeMobileSidebar();
  },
);

watch(() => menuStore.menus, syncActiveParents, { deep: true, immediate: true });
</script>

<template>
  <aside class="left-sidebar with-vertical">
    <div>
      <!-- Brand -->
      <div class="brand-logo d-flex align-items-center justify-content-between">
        <a href="/" class="text-nowrap logo-img">
          <img src="https://bootstrapdemos.adminmart.com/modernize/dist/assets/images/logos/dark-logo.svg" class="dark-logo" alt="Logo Dark" />

          <img src="https://bootstrapdemos.adminmart.com/modernize/dist/assets/images/logos/light-logo.svg" class="light-logo" alt="Logo Light" style="display: none" />
        </a>

        <a href="javascript:void(0)" class="sidebartoggler ms-auto text-decoration-none fs-5 d-block d-xl-none">
          <i class="ti ti-x"></i>
        </a>
      </div>

      <!-- Navigation -->
      <nav class="sidebar-nav scroll-sidebar" data-simplebar>
        <div v-if="menuStore.loading" class="p-3">Memuat menu...</div>

        <div v-else-if="menuStore.error" class="p-3">
          {{ menuStore.error }}

          <button type="button" class="btn btn-sm btn-primary ms-2" @click="retryMenus">Coba lagi</button>
        </div>

        <div v-else-if="menuStore.menus.length === 0" class="p-3">Belum ada menu.</div>

        <ul v-else id="sidebarnav">
          <MenuItem v-for="item in menuStore.menus" :key="item.id" :item="item" :expanded-menus="expandedMenus" @toggle="toggleMenu" />
        </ul>
      </nav>

      <!-- Profile -->
      <div class="fixed-profile p-3 mx-4 mb-2 bg-secondary-subtle rounded mt-3">
        <div class="hstack gap-3">
          <div class="john-img"></div>

          <div class="john-title">
            <h6 class="mb-0 fs-4 fw-semibold">Mathew</h6>

            <span class="fs-2">Designer</span>
          </div>

          <button class="border-0 bg-transparent text-primary ms-auto" tabindex="0" type="button" aria-label="logout" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="logout">
            <i class="ti ti-power fs-6"></i>
          </button>
        </div>
      </div>
    </div>
  </aside>
</template>
