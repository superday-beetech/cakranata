<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, watch, nextTick } from "vue";
import Sortable from "sortablejs";
import type { NavigationMenu } from "@/types/navigation";

defineOptions({
  name: "MenuTreeItem",
});

interface Props {
  items: NavigationMenu[];
  depth?: number;
}

const props = withDefaults(defineProps<Props>(), {
  depth: 0,
});

const emit = defineEmits<{
  (e: "add", menu: NavigationMenu): void;
  (e: "edit", menu: NavigationMenu): void;
  (e: "delete", menu: NavigationMenu): void;
  (e: "treeChange"): void;
}>();

const listElement = ref<HTMLElement | null>(null);
let sortableInstance: Sortable | null = null;

function initSortable() {
  if (!listElement.value || sortableInstance) return;

  sortableInstance = Sortable.create(listElement.value, {
    group: {
      name: "navigation-menu-tree",
      pull: true,
      put: true,
    },
    animation: 200,
    easing: "cubic-bezier(0.2, 1, 0.1, 1)",
    handle: ".menu-drag-handle",
    draggable: ".menu-item",
    filter: ".menu-empty-hint",
    preventOnFilter: false,
    fallbackOnBody: true,
    fallbackTolerance: 4,
    swapThreshold: 0.65,
    ghostClass: "menu-sortable-ghost",
    chosenClass: "menu-sortable-chosen",
    dragClass: "menu-sortable-drag",
    onMove(evt) {
      const draggedId = (evt.dragged as HTMLElement).dataset.menuId;
      const isDroppingInsideSelf = Boolean((evt.to as HTMLElement).closest(`[data-menu-id="${draggedId}"]`));

      return !isDroppingInsideSelf;
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
    if (!sortableInstance) {
      initSortable();
    }
  },
);

onBeforeUnmount(() => {
  sortableInstance?.destroy();
  sortableInstance = null;
});
</script>

<template>
  <ul ref="listElement" class="menu-tree-list" :class="{ 'menu-tree-root': depth === 0 }" :data-depth="depth">
    <li v-for="menu in items" :key="menu.id" class="menu-item" :data-menu-id="menu.id">
      <div class="menu-item-card">
        <!-- Main Content -->
        <div class="menu-item-main">
          <button type="button" class="menu-drag-handle" title="Geser posisi menu" aria-label="Geser posisi menu">
            <i class="ti ti-grip-vertical"></i>
          </button>

          <div class="menu-item-icon">
            <i :class="menu.icon || 'ti ti-circle-small'"></i>
          </div>

          <div class="menu-item-info">
            <div class="menu-item-header">
              <span class="menu-title">{{ menu.title }}</span>

              <span v-if="menu.is_active !== '1'" class="status-badge badge-inactive"> Nonaktif </span>

              <span v-if="menu.is_divider === '1'" class="status-badge badge-divider"> Divider </span>

              <small class="menu-url" :title="menu.url || 'Tidak ada URL'">
                {{ menu.url || "Tanpa URL" }}
              </small>
            </div>
          </div>
        </div>

        <!-- Action Controls -->
        <div class="menu-item-actions">
          <span class="submenu-count-tag"> {{ menu.children?.length ?? 0 }} submenu </span>

          <div class="action-buttons">
            <button type="button" class="btn-action btn-add btn-sm" title="Tambah Submenu" aria-label="Tambah Submenu" @click="emit('add', menu)">
              <i class="ti ti-plus"></i>
            </button>

            <button type="button" class="btn-action btn-edit btn-sm" title="Edit Menu" aria-label="Edit Menu" @click="emit('edit', menu)">
              <i class="ti ti-edit"></i>
            </button>

            <button type="button" class="btn-action btn-delete" title="Hapus Menu" aria-label="Hapus Menu" @click="emit('delete', menu)">
              <i class="ti ti-trash"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Recursive Sub-tree -->
      <MenuTreeItem :items="menu.children ?? []" :depth="depth + 1" @add="(item) => emit('add', item)" @edit="(item) => emit('edit', item)" @delete="(item) => emit('delete', item)" @tree-change="emit('treeChange')" />
    </li>
  </ul>
</template>
<style scoped>
/* Tree Container Base */
.menu-tree-list {
  list-style: none;
  margin: 0;
  padding: 0;
  min-height: 6px;
}

.menu-tree-root {
  min-height: 26px;
}

.menu-item {
  position: relative;
  /* margin-bottom: 2px; */
}

/* Card Design Enterprise */
.menu-item-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-height: 28px;
  padding: 2px 2px;
  background: var(--bs-body-bg, #ffffff);
  border: 1px solid var(--bs-border-color-translucent, #eaecf0);
  border-radius: 6px;
  box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.menu-item-card:hover {
  border-color: var(--bs-primary-border-subtle, #b2ddff);
  background: var(--bs-tertiary-bg, #f9fafb);
  box-shadow: 0 2px 4px rgba(16, 24, 40, 0.06);
}

/* Sub-tree Hierarchy & Tree Lines (Diperbaiki agar rapi & proporsional) */
.menu-item > .menu-tree-list {
  /* margin-top: 2px; */
  /* margin-bottom: 4px; */
  margin-left: 20px;
  padding-left: 16px;
  border-left: 1px dashed var(--bs-border-color, #d0d5dd);
}

.menu-item > .menu-tree-list > .menu-item:first-child {
  margin-top: 4px; /* Memberikan ruang khusus untuk item anak paling atas */
}

.menu-item > .menu-tree-list > .menu-item::before {
  content: "";
  position: absolute;
  left: -14px;
  top: 18px; /* Diatur pas di tengah tinggi card (36px / 2) */
  width: 14px;
  border-top: 1px dashed var(--bs-border-color, #d0d5dd);
}

/* Main Section */
.menu-item-main {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  flex: 1;
}

.menu-drag-handle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--bs-secondary-color, #98a2b3);
  cursor: grab;
  border-radius: 4px;
  transition:
    color 0.15s ease,
    background 0.15s ease;
}

.menu-drag-handle:hover {
  color: var(--bs-primary, #0052cc);
  background: var(--bs-secondary-bg, #f2f4f7);
}

.menu-drag-handle:active {
  cursor: grabbing;
}

.menu-item-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  flex-shrink: 0;
  border-radius: 4px;
  color: var(--bs-body-color, #344054);
  font-size: 12px;
}

/* Memperbaiki struktur info & header agar sejajar horizontal */
.menu-item-info {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  flex: 1;
}

.menu-item-header {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: nowrap;
}

.menu-title {
  font-size: 12px;
  font-weight: 600;
  color: var(--bs-heading-color, #101828);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.menu-url {
  font-size: 11px;
  color: var(--bs-secondary-color, #667085);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 280px;
}

/* Badges & Tags */
.status-badge {
  font-size: 10px;
  font-weight: 500;
  padding: 1px 6px;
  border-radius: 4px;
  line-height: 1.4;
  flex-shrink: 0;
}

.badge-inactive {
  background: var(--bs-secondary-bg-subtle, #f2f4f7);
  color: var(--bs-secondary, #667085);
}

.badge-divider {
  background: var(--bs-info-bg-subtle, #e0f2fe);
  color: var(--bs-info-text-emphasis, #0369a1);
}

.submenu-count-tag {
  font-size: 11px;
  font-weight: 500;
  color: var(--bs-secondary-color, #667085);
  background: var(--bs-secondary-bg, #f2f4f7);
  padding: 2px 8px;
  border-radius: 12px;
  flex-shrink: 0;
}

/* Actions Section */
.menu-item-actions {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
}

.action-buttons {
  display: flex;
  align-items: center;
  gap: 4px;
}

.btn-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  padding: 0;
  border: 1px solid transparent;
  border-radius: 6px;
  background: var(--bs-body-bg, #ffffff);
  color: var(--bs-body-color, #344054);
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-action:hover {
  background: var(--bs-tertiary-bg, #f9fafb);
}

.btn-add:hover {
  color: var(--bs-primary, #0969da);
  border-color: var(--bs-primary-border-subtle, #54a0ff);
}

.btn-edit:hover {
  color: var(--bs-warning-text-emphasis, #b45309);
  border-color: var(--bs-warning-border-subtle, #fde047);
}

.btn-delete:hover {
  color: var(--bs-danger, #d92d20);
  border-color: var(--bs-danger-border-subtle, #fda29b);
  background: var(--bs-danger-bg-subtle, #fef3f2);
}

/* Drag State Styling */
.menu-sortable-ghost .menu-item-card {
  opacity: 0.35;
  border: 1px dashed var(--bs-primary, #0969da);
  background: var(--bs-primary-bg-subtle, #eff6ff);
}

.menu-sortable-chosen .menu-item-card {
  border-color: var(--bs-primary, #0969da);
  box-shadow: 0 0 0 3px rgba(9, 105, 218, 0.12);
}

.menu-sortable-drag .menu-item-card {
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
  transform: scale(1.01);
}

/* Responsive adjustments */
@media (max-width: 576px) {
  .submenu-count-tag {
    display: none;
  }

  .menu-url {
    max-width: 120px;
  }
}
</style>
