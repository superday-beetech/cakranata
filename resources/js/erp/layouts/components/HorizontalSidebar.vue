<script setup>
import { ref, onMounted, watch } from "vue";
import { useRoute } from "vue-router";
import { fetchMenus } from "../../services/menuServices.js";
import MenuItem from "./MenuItem.vue";

const route = useRoute();

const menus = ref([]);
const expandedMenus = ref([]);
const loadingMenus = ref(false);
const menuError = ref("");

function isExpanded(id) {
  return expandedMenus.value.includes(id);
}

function toggleMenu(id) {
  expandedMenus.value = isExpanded(id) ? expandedMenus.value.filter((value) => value !== id) : [...expandedMenus.value, id];
}

function isActiveUrl(url) {
  if (!url || url === "#" || url === "divider") return false;

  const value = String(url).trim();

  if (/^(https?:)?\/\//i.test(value)) return false;

  const path = "/" + value.replace(/^\/+/, "");

  return route.path.replace(/\/+$/, "") === path.replace(/\/+$/, "");
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

  scan(menus.value);

  // Pertahankan dropdown yang dibuka pengguna.
  expandedMenus.value = [...new Set([...expandedMenus.value, ...activeIds])];
}

async function loadMenus() {
  loadingMenus.value = true;
  menuError.value = "";

  try {
    const result = await fetchMenus();

    menus.value = Array.isArray(result) ? result : [];
    expandedMenus.value = [];
    syncActiveParents();
  } catch (error) {
    menuError.value = error.message || "Gagal memuat menu.";
    console.error("Horizontal menu:", error);
  } finally {
    loadingMenus.value = false;
  }
}

watch(() => route.path, syncActiveParents);

onMounted(loadMenus);
</script>

<template>
  <aside class="left-sidebar with-horizontal">
    <div>
      <nav id="sidebarnavh" class="sidebar-nav scroll-sidebar container-fluid" aria-label="Navigasi utama">
        <div v-if="loadingMenus" class="p-3">Memuat menu...</div>

        <div v-else-if="menuError" class="p-3">
          {{ menuError }}
          <button type="button" class="btn btn-sm btn-primary ms-2" @click="loadMenus">Coba lagi</button>
        </div>

        <div v-else-if="menus.length === 0" class="p-3">Belum ada menu.</div>

        <ul v-else class="navbar-nav">
          <MenuItem v-for="item in menus" :key="item.id" :item="item" :expanded-menus="expandedMenus" @toggle="toggleMenu" />
        </ul>
      </nav>
    </div>
  </aside>
</template>
