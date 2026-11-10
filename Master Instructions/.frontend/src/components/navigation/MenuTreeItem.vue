<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, watch, nextTick } from "vue";
import Sortable from "sortablejs";
// import "@/assets/css/navigation.css";
import type { NavigationMenu } from "@/types/navigation";

defineOptions({
  name: "MenuTreeItem",
});

const props = defineProps<{
  items: NavigationMenu[];
  depth?: number;
}>();

const emit = defineEmits<{
  edit: [menu: NavigationMenu];
  delete: [menu: NavigationMenu];
  treeChange: [];
}>();

const listElement = ref<HTMLElement | null>(null);
let sortable: Sortable | null = null;

function initSortable() {
  if (!listElement.value || sortable) return;

  sortable = Sortable.create(listElement.value, {
    group: {
      name: "navigation-menu",
      pull: true,
      put: true,
    },

    animation: 180,
    handle: ".menu-drag-handle",
    draggable: ".menu-item",
    filter: ".menu-empty-hint",
    preventOnFilter: false,

    fallbackOnBody: true,
    fallbackTolerance: 5,
    swapThreshold: 0.65,

    ghostClass: "menu-sortable-ghost",
    chosenClass: "menu-sortable-chosen",
    dragClass: "menu-sortable-drag",

    onMove(evt) {
      // Hindari memasukkan menu ke dalam dirinya sendiri.
      const draggedId = evt.dragged.dataset.menuId;
      const target = evt.to.closest(`[data-menu-id="${draggedId}"]`);

      if (target) return false;

      return true;
    },

    onEnd() {
      emit("treeChange");
    },
  });
}

onMounted(() => {
  initSortable();
});

watch(
  () => props.items.length,
  async () => {
    await nextTick();

    if (!sortable) {
      initSortable();
    }
  },
);

onBeforeUnmount(() => {
  sortable?.destroy();
  sortable = null;
});
</script>

<template>
  <ul ref="listElement" class="menu-sortable-list" :class="{ 'menu-sortable-root': depth === 0 }" :data-depth="depth ?? 0">
    <li v-for="menu in items" :key="menu.id" class="menu-item" :data-menu-id="menu.id">
      <div class="menu-item-card">
        <div class="menu-item-main">
          <button type="button" class="btn btn-sm btn-link text-secondary menu-drag-handle" title="Geser untuk mengubah urutan" aria-label="Geser menu">
            <i class="ti ti-grip-vertical"></i>
          </button>

          <span class="menu-item-icon">
            <i :class="menu.icon || 'ti ti-circle'"></i>
          </span>

          <div class="menu-item-info">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <strong>{{ menu.title }}</strong>

              <span v-if="menu.is_active !== '1'" class="badge bg-secondary-subtle text-secondary"> Nonaktif </span>

              <span v-if="menu.is_divider === '1'" class="badge bg-info-subtle text-info"> Divider </span>
              <small class="text-muted">
                {{ menu.url || "Tidak ada URL" }}
              </small>
            </div>
          </div>
        </div>

        <div class="menu-item-actions">
          <span class="badge bg-light text-dark"> {{ menu.children?.length ?? 0 }} submenu </span>

          <button type="button" class="btn btn-sm btn-outline-primary" title="Edit menu" @click="emit('edit', menu)">
            <i class="ti ti-edit"></i>
          </button>

          <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus menu" @click="emit('delete', menu)">
            <i class="ti ti-trash"></i>
          </button>
        </div>
      </div>

      <!-- Nested list selalu tersedia agar bisa menerima drop -->
      <MenuTreeItem :items="menu.children ?? []" :depth="(depth ?? 0) + 1" @edit="emit('edit', $event)" @delete="emit('delete', $event)" @tree-change="emit('treeChange')" />
    </li>
  </ul>
</template>

<style scoped>
/* .menu-sortable-list {
  list-style: none;
  margin: 0;
  padding: 0;
  min-height: 24px;
}

.menu-sortable-root {
  min-height: 40px;
}

.menu-item {
  margin-bottom: 8px;
}

.menu-item-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  border: 1px solid var(--bs-border-color, #dee2e6);
  border-radius: 8px;
  background: var(--bs-body-bg, #fff);
}

.menu-item-main,
.menu-item-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.menu-item-info {
  min-width: 0;
  overflow-wrap: anywhere;
}

.menu-item-icon {
  width: 28px;
  text-align: center;
  font-size: 18px;
}

.menu-drag-handle {
  cursor: grab;
  padding: 4px;
  touch-action: none;
}

.menu-drag-handle:active {
  cursor: grabbing;
}

.menu-item > .menu-sortable-list {
  margin: 8px 0 0 34px;
  padding: 6px 0 6px 14px;
  border-left: 2px dashed var(--bs-border-color, #dee2e6);
  min-height: 10px;
}

.menu-sortable-ghost {
  opacity: 0.35;
}

.menu-sortable-chosen {
  background: var(--bs-primary-bg-subtle, #e7f1ff);
}

@media (max-width: 576px) {
  .menu-item-card {
    align-items: flex-start;
    flex-direction: column;
  }

  .menu-item-actions {
    align-self: flex-end;
  }
} */
.menu-sortable-list {
  list-style: none;
  margin: 0;
  padding: 0;
  min-height: 10px;
}

.menu-sortable-root {
  min-height: 32px;
}

/* Jarak antar menu */
/* .menu-item {
  margin: 0 0 3px 0;
} */

/* Kartu menu */
.menu-item-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  min-height: 42px;
  padding: 6px 10px;
  border: 1px solid var(--bs-border-color, #e5e7eb);
  border-radius: 7px;
  background: var(--bs-body-bg, #fff);
  transition:
    background 0.15s ease,
    border-color 0.15s ease,
    box-shadow 0.15s ease;
}

.menu-item-card:hover {
  border-color: var(--bs-primary-border-subtle, #b6d4fe);
  background: var(--bs-tertiary-bg, #f8fafc);
}

/* Bagian kiri */
.menu-item-main,
.menu-item-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.menu-item-info {
  min-width: 0;
  line-height: 1.3;
}

.menu-item-info strong {
  font-size: 13px;
  font-weight: 600;
  color: var(--bs-emphasis-color);
}

.menu-item-info small {
  display: block;
  margin-top: 1px;
  font-size: 11px;
  color: var(--bs-secondary-color);
}

/* Ikon */
.menu-item-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 27px;
  height: 27px;
  flex-shrink: 0;
  border-radius: 6px;
  background: var(--bs-tertiary-bg, #f1f5f9);
  color: var(--bs-secondary-color);
  font-size: 15px;
}

/* Drag handle */
.menu-drag-handle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 26px;
  padding: 0;
  margin: 0;
  color: var(--bs-secondary-color);
  cursor: grab;
  touch-action: none;
  text-decoration: none;
}

.menu-drag-handle:hover {
  color: var(--bs-primary);
}

.menu-drag-handle:active {
  cursor: grabbing;
}

/* Badge dan action */
.menu-item-actions {
  gap: 5px;
  flex-shrink: 0;
}

.menu-item-actions .badge {
  font-size: 10px;
  font-weight: 500;
  padding: 4px 6px;
  border-radius: 5px;
}

.menu-item-actions .btn {
  width: 27px;
  height: 27px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border-radius: 5px;
}

/* Hierarki submenu: lebih rapat */
.menu-item > .menu-sortable-list {
  margin: 2px 0 0 16px;
  padding: 2px 0 1px 10px;
  border-left: 1px solid var(--bs-border-color, #e5e7eb);
  min-height: 1px;
}

/* Sedikit penanda garis tree */
.menu-item > .menu-sortable-list > .menu-item {
  position: relative;
}

.menu-item > .menu-sortable-list > .menu-item::before {
  content: "";
  position: absolute;
  left: -12px;
  top: 20px;
  width: 8px;
  border-top: 1px solid var(--bs-border-color, #e5e7eb);
}

/* Drag state */
.menu-sortable-ghost .menu-item-card {
  opacity: 0.4;
  border: 1px dashed var(--bs-primary);
  background: var(--bs-primary-bg-subtle);
}

.menu-sortable-chosen .menu-item-card {
  border-color: var(--bs-primary);
  box-shadow: 0 0 0 2px rgba(var(--bs-primary-rgb), 0.08);
}

.menu-sortable-drag .menu-item-card {
  box-shadow: 0 8px 22px rgba(0, 0, 0, 0.12);
}

/* Mobile */
@media (max-width: 576px) {
  .menu-item-card {
    min-height: 40px;
    padding: 5px 7px;
    gap: 5px;
  }

  .menu-item-main,
  .menu-item-actions {
    gap: 5px;
  }

  .menu-item > .menu-sortable-list {
    margin-left: 13px;
    padding-left: 8px;
  }

  .menu-item-actions .badge {
    display: none;
  }
}
</style>
