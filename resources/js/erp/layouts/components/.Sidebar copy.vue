<script setup>
import { ref, onMounted, watch, nextTick } from "vue";
import { useRoute } from "vue-router";
import { fetchMenus } from "../../services/menuServices.js";
import MenuItem from "./MenuItem.vue";

const route = useRoute();

const menus = ref([]);
const expandedMenus = ref([]);
const loadingMenus = ref(false);
const menuError = ref("");

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
  return !!path && route.path.replace(/\/+$/, "") === path;
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
        if (hasActiveDescendant(item)) activeIds.push(item.id);
      }
    }
  }

  scan(menus.value);
  expandedMenus.value = [...new Set([...expandedMenus.value, ...activeIds])];
}

async function loadMenus() {
  loadingMenus.value = true;
  menuError.value = "";

  try {
    const result = await fetchMenus();
    menus.value = Array.isArray(result) ? result : [];

    await nextTick();
    syncActiveParents();
  } catch (error) {
    menuError.value = error.message || "Gagal memuat menu.";
    console.error("Sidebar menu:", error);
  } finally {
    loadingMenus.value = false;
  }
}

watch(() => route.path, syncActiveParents);
onMounted(loadMenus);
</script>

<template>
  <aside class="left-sidebar with-vertical">
    <div>
      <div class="brand-logo d-flex align-items-center justify-content-between">
        <a href="../main/index.html" class="text-nowrap logo-img">
          <img src="https://bootstrapdemos.adminmart.com/modernize/dist/assets/images/logos/dark-logo.svg" class="dark-logo" alt="Logo-Dark" />
          <img src="https://bootstrapdemos.adminmart.com/modernize/dist/assets/images/logos/light-logo.svg" class="light-logo" alt="Logo-light" style="display: none" />
        </a>
        <a href="javascript:void(0)" class="sidebartoggler ms-auto text-decoration-none fs-5 d-block d-xl-none">
          <i class="ti ti-x"></i>
        </a>
      </div>
      <nav class="sidebar-nav scroll-sidebar" data-simplebar>
        <div v-if="loadingMenus" class="p-3">Memuat menu...</div>

        <div v-else-if="menuError" class="p-3">
          {{ menuError }}
          <button type="button" class="btn btn-sm btn-primary ms-2" @click="loadMenus">Coba lagi</button>
        </div>

        <div v-else-if="menus.length === 0" class="p-3">Belum ada menu.</div>

        <ul v-else id="sidebarnav">
          <!-- <ul id="sidebarnav"> -->
          <MenuItem v-for="item in menus" :key="item.id" :item="item" :expanded-menus="expandedMenus" @toggle="toggleMenu" />
        </ul>
      </nav>
      <div class="fixed-profile p-3 mx-4 mb-2 bg-secondary-subtle rounded mt-3">
        <div class="hstack gap-3">
          <div class="john-img">
            <!-- <img src="../assets/images/profile/user-1.jpg" class="rounded-circle" width="40" height="40" alt="modernize-img" /> -->
          </div>
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
