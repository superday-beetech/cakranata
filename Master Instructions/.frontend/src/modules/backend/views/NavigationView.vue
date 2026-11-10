<script setup lang="ts">
import { computed, onMounted, ref } from "vue";

import { useNavigationStore } from "@/stores/navigation";
import type { MenuHierarchy, MenuPosition, MenuSection, NavigationMenu } from "@/types/navigation";

import MenuTreeItem from "@/components/navigation/MenuTreeItem.vue";
import MenuForm from "@/components/navigation/MenuForm.vue";
import RouteRegistryImport from "@/components/RouteRegistryImport.vue";

import { offcanvas } from "@/utils/offcanvas";
import { modal } from "@/utils/modal";
import { notify } from "@/utils/notification";

const store = useNavigationStore();

const STORAGE_KEY = "acn_navigation_preferences";

interface NavigationPreferences {
  position: MenuPosition;
  section: MenuSection;
}

function getSavedPreferences(): NavigationPreferences {
  const defaults: NavigationPreferences = {
    position: "sidebar",
    section: "backend",
  };

  try {
    const saved = localStorage.getItem(STORAGE_KEY);

    if (!saved) return defaults;

    const parsed = JSON.parse(saved);

    return {
      position: positionOptions.some((option) => option.value === parsed.position) ? parsed.position : defaults.position,

      section: sectionOptions.some((option) => option.value === parsed.section) ? parsed.section : defaults.section,
    };
  } catch {
    return defaults;
  }
}

function savePreferences(): void {
  const preferences: NavigationPreferences = {
    position: store.position,
    section: store.section,
  };

  localStorage.setItem(STORAGE_KEY, JSON.stringify(preferences));
}

const treeContainer = ref<HTMLElement | null>(null);
const positionOptions: { label: string; value: MenuPosition }[] = [
  { label: "Sidebar", value: "sidebar" },
  { label: "Topbar", value: "topbar" },
  { label: "Quick Links", value: "quick_links" },
  { label: "Down", value: "down" },
  { label: "Apps", value: "apps" },
];

const sectionOptions: { label: string; value: MenuSection }[] = [
  { label: "Backend", value: "backend" },
  { label: "Frontend", value: "frontend" },
];

const menuCount = computed(() => store.menus.length);
const activeCount = computed(() => store.menus.filter((menu) => menu.is_active === "1").length);

const parentOptions = computed(() => {
  const excluded = new Set<string>();

  function collectDescendants(menu: NavigationMenu) {
    excluded.add(menu.id);

    for (const child of menu.children ?? []) {
      collectDescendants(child);
    }
  }

  return store.menus.filter((menu) => {
    // Parent options are further filtered for the edited menu in openMenuForm.
    return Boolean(menu.id);
  });
});

let saveInProgress = false;
let saveQueued = false;

function serializeTree(): MenuHierarchy[] {
  const root = treeContainer.value?.querySelector(".menu-sortable-root") as HTMLElement | null;

  if (!root) return [];

  function serializeList(list: HTMLElement, parentId: number): MenuHierarchy[] {
    const menuElements = Array.from(list.children).filter((element): element is HTMLElement => element instanceof HTMLElement && element.classList.contains("menu-item"));

    return menuElements.map((element, index) => {
      const id = Number(element.dataset.menuId);

      const childList = Array.from(element.children).find((child): child is HTMLElement => child instanceof HTMLElement && child.classList.contains("menu-sortable-list"));

      return {
        id,
        parent_id: parentId,
        sequence: index + 1,
        children: childList ? serializeList(childList, id) : [],
      };
    });
  }

  return serializeList(root, 0);
}

function applyHierarchyToStore(tree: MenuHierarchy[]) {
  const menuMap = new Map(store.menus.map((menu) => [String(menu.id), menu]));

  const flattened: NavigationMenu[] = [];

  function walk(nodes: MenuHierarchy[]) {
    for (const node of nodes) {
      const menu = menuMap.get(String(node.id));
      if (!menu) continue;

      flattened.push({
        ...menu,
        parent_id: String(node.parent_id),
        sequence: String(node.sequence),
      });

      walk(node.children ?? []);
    }
  }

  walk(tree);
  store.menus = flattened;
}

async function flushOrderQueue() {
  if (saveInProgress) return;

  saveInProgress = true;

  try {
    while (saveQueued) {
      saveQueued = false;

      const tree = serializeTree();

      // Controller menolak payload JSON kosong.
      if (tree.length === 0) continue;

      await store.updateOrder(tree);

      // Jangan mengganti DOM jika ada drag baru selama request berjalan.
      if (!saveQueued) {
        applyHierarchyToStore(tree);
      }
    }

    notify.success("Urutan menu berhasil disimpan.");
  } catch (error) {
    saveQueued = false;

    notify.error(error instanceof Error ? error.message : "Gagal menyimpan struktur menu.");

    // Kembalikan tampilan ke struktur terakhir yang tersimpan di server.
    try {
      await store.loadNavigation();
    } catch {
      // Error pemuatan sudah ditangani oleh store.
    }
  } finally {
    saveInProgress = false;

    // Menangani perubahan yang masuk tepat saat request terakhir selesai.
    if (saveQueued) {
      void flushOrderQueue();
    }
  }
}

function handleTreeChange() {
  saveQueued = true;
  void flushOrderQueue();
}

function openMenuForm(menu: NavigationMenu | null = null) {
  const excluded = new Set<string>();

  function collectDescendants(item: NavigationMenu) {
    excluded.add(item.id);

    for (const child of item.children ?? []) {
      collectDescendants(child);
    }
  }

  if (menu) collectDescendants(menu);

  const validParents = store.menus.filter((item) => !excluded.has(item.id));

  offcanvas.open({
    title: menu ? "Edit Menu" : "Tambah Menu",
    component: MenuForm,
    props: {
      menu,
      roles: [...store.roles],
      parentOptions: validParents,
      position: store.position,
      section: store.section,
      onSaved: async () => {
        await store.loadNavigation();
      },
    },
    placement: "end",
    width: "520px",
    backdrop: true,
    scroll: true,
    keyboard: true,
  });
}

async function deleteMenu(menu: NavigationMenu) {
  const confirmed = window.confirm(`Yakin ingin menghapus menu "${menu.title}"?`);

  if (!confirmed) return;

  try {
    await store.deleteMenu(menu.id);
    notify.success("Menu berhasil dihapus.");
  } catch (error) {
    notify.error(error instanceof Error ? error.message : "Gagal menghapus menu.");
  }
}

function openRegistryImport() {
  modal.open({
    title: "Import Route Registry",
    component: RouteRegistryImport,
    props: {
      onSaved: async () => {
        await store.loadNavigation();
      },
    },
    size: "xl",
    centered: true,
    scrollable: true,
    backdrop: true,
    keyboard: true,
  });
}

async function changePosition(value: MenuPosition) {
  if (value === store.position) return;

  try {
    await store.loadNavigation(value, store.section);
    savePreferences();
  } catch {
    notify.error("Gagal memuat posisi menu.");
  }
}

async function changeSection(value: MenuSection) {
  if (value === store.section) return;

  try {
    await store.loadNavigation(store.position, value);
    savePreferences();
  } catch {
    notify.error("Gagal memuat section menu.");
  }
}
onMounted(async () => {
  const preferences = getSavedPreferences();

  try {
    await store.loadNavigation(preferences.position, preferences.section);

    savePreferences();
  } catch {
    notify.error("Gagal memuat data navigasi.");
  }
});
</script>

<template>
  <div class="navigation-view">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <h4 class="mb-1">Manajemen Navigasi</h4>
        <p class="text-muted mb-0">Atur struktur menu, posisi, dan hak akses navigasi.</p>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-outline-primary" @click="openRegistryImport">
          <i class="ti ti-route me-1"></i>
          Import Route Registry
        </button>

        <button type="button" class="btn btn-primary" @click="openMenuForm()">
          <i class="ti ti-plus me-1"></i>
          Tambah Menu
        </button>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
          <div class="card-body">
            <small class="text-muted">Total Menu</small>
            <div class="fs-4 fw-semibold">{{ menuCount }}</div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
          <div class="card-body">
            <small class="text-muted">Menu Aktif</small>
            <div class="fs-4 fw-semibold text-success">
              {{ activeCount }}
            </div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
          <div class="card-body">
            <small class="text-muted">Posisi</small>
            <div class="fs-5 fw-semibold text-capitalize">
              {{ store.position }}
            </div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
          <div class="card-body">
            <small class="text-muted">Section</small>
            <div class="fs-5 fw-semibold text-capitalize">
              {{ store.section }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div>
            <h5 class="card-title mb-1">
              {{ store.title }}
            </h5>
            <small class="text-muted"> Geser menu untuk mengubah urutan atau hierarki. </small>
          </div>

          <div class="d-flex flex-wrap gap-2">
            <select class="form-select form-select-sm" style="width: 135px" :value="store.position" :disabled="store.loading" @change="changePosition(($event.target as HTMLSelectElement).value as MenuPosition)">
              <option v-for="option in positionOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>

            <select class="form-select form-select-sm" style="width: 135px" :value="store.section" :disabled="store.loading" @change="changeSection(($event.target as HTMLSelectElement).value as MenuSection)">
              <option v-for="option in sectionOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <div class="card-body">
        <div v-if="store.loading" class="text-center py-5">
          <div class="spinner-border text-primary"></div>
          <p class="text-muted mt-2 mb-0">Memuat struktur navigasi...</p>
        </div>

        <div v-else-if="store.error" class="alert alert-danger" role="alert">
          {{ store.error }}
          <button type="button" class="btn btn-sm btn-outline-danger ms-2" @click="store.loadNavigation()">Coba lagi</button>
        </div>

        <div v-else-if="store.menuTree.length === 0" class="text-center text-muted py-5">
          <i class="ti ti-menu-2 fs-1 d-block mb-2"></i>
          <h6>Belum ada menu</h6>
          <p class="mb-3">Tambahkan menu baru atau import dari Route Registry.</p>

          <button type="button" class="btn btn-primary btn-sm" @click="openMenuForm()">
            <i class="ti ti-plus me-1"></i>
            Tambah Menu
          </button>
        </div>

        <div v-else ref="treeContainer">
          <MenuTreeItem :items="store.menuTree" :depth="0" @edit="openMenuForm" @delete="deleteMenu" @tree-change="handleTreeChange" />
        </div>
      </div>

      <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted"> {{ menuCount }} menu dalam posisi {{ store.position }}. </small>

        <small class="text-muted">
          <i class="ti ti-arrows-move me-1"></i>
          Drag &amp; drop untuk mengatur hierarki
        </small>
      </div>
    </div>
  </div>
</template>
