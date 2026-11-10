<script setup lang="ts">
import { computed } from "vue";
import { RouterLink, useRoute } from "vue-router";

import type { MenuItem } from "@/types/menu";

defineOptions({
  name: "SidebarHorizontalItem",
});

interface Props {
  item: MenuItem;
  level?: number;
  expandedMenus?: Array<number | string>;
  parentIds?: Array<number | string>;
}

const props = withDefaults(defineProps<Props>(), {
  level: 0,
  expandedMenus: () => [],
  parentIds: () => [],
});

const emit = defineEmits<{
  toggle: [id: number | string, ancestors: Array<number | string>];
  navigate: [];
}>();

const route = useRoute();

// Children
const children = computed<MenuItem[]>(() => (Array.isArray(props.item.children) ? props.item.children : []));

const hasChildren = computed(() => children.value.length > 0);

// Expanded state
const isExpanded = computed(() => props.expandedMenus.some((id) => String(id) === String(props.item.id)));

// Divider
function isDivider(item: MenuItem): boolean {
  return item.url === "divider" || Number(item.is_divider) === 1;
}

// External URL
function isExternal(url?: string | null): boolean {
  return /^(https?:)?\/\//i.test(String(url || "").trim());
}

// Normalize URL
function normalizeUrl(url?: string | null): string {
  if (!url || url === "#" || url === "divider") {
    return "";
  }

  const value = url.trim();

  if (isExternal(value)) {
    return value;
  }

  return "/" + value.replace(/^\/+/, "");
}

// Active route
function isActive(url?: string | null): boolean {
  const path = normalizeUrl(url);

  if (!path || isExternal(path)) {
    return false;
  }

  const targetPath = path.split(/[?#]/)[0].replace(/\/+$/, "") || "/";

  const currentPath = route.path.replace(/\/+$/, "") || "/";

  return currentPath === targetPath;
}

// Active descendant
function hasActiveDescendant(item: MenuItem): boolean {
  return (item.children || []).some((child) => isActive(child.url) || hasActiveDescendant(child));
}

const active = computed(() => isActive(props.item.url) || hasActiveDescendant(props.item));

// Submenu classes - sesuai Modernize
const submenuClass = computed(() => {
  if (props.level === 0) return "first-level";
  if (props.level === 1) return "second-level";
  return "three-level";
});

// Toggle
function toggle(): void {
  if (hasChildren.value) {
    emit("toggle", props.item.id, props.parentIds);
  }
}

// Navigate
function navigate(): void {
  emit("navigate");
}
</script>

<template>
  <!-- Group Heading -->
  <li v-if="isDivider(item)" class="nav-small-cap">
    <i class="ti ti-dots nav-small-cap-icon fs-4"></i>

    <span class="hide-menu">
      {{ item.title }}
    </span>
  </li>

  <!-- Sidebar Item -->
  <li v-else class="sidebar-item" :class="{ selected: active }">
    <!-- Parent Menu -->
    <a v-if="hasChildren" href="javascript:void(0)" class="sidebar-link has-arrow" :class="{ active: active }" :aria-expanded="isExpanded" @click.prevent="toggle">
      <!-- Parent Icon -->
      <template v-if="level === 0">
        <i v-if="item.icon" :class="[item.icon, 'fs-6', 'aside-icon']"></i>
      </template>

      <!-- Child Icon -->
      <span v-else class="sidebar-icon"></span>

      <span class="hide-menu" :class="{ 'ps-1': level === 0 }">
        {{ item.title }}
      </span>
    </a>

    <!-- External Link -->
    <a v-else-if="isExternal(item.url)" :href="normalizeUrl(item.url)" class="sidebar-link" target="_blank" rel="noopener noreferrer" @click="navigate">
      <template v-if="level === 0">
        <i v-if="item.icon" :class="[item.icon, 'fs-6', 'aside-icon']"></i>
      </template>

      <span v-else class="sidebar-icon"></span>

      <span class="hide-menu" :class="{ 'ps-1': level === 0 }">
        {{ item.title }}
      </span>
    </a>

    <!-- Internal Vue Router Link -->
    <!-- <RouterLink v-else-if="normalizeUrl(item.url)" :to="normalizeUrl(item.url)" class="sidebar-link" :class="{ active: isActive(item.url) }" @click="navigate">
      <template v-if="level === 0">
        <i v-if="item.icon" :class="[item.icon, 'fs-6', 'aside-icon']"></i>
      </template>

      <span v-else class="sidebar-icon"></span>

      <span class="hide-menu" :class="{ 'ps-1': level === 0 }">
        {{ item.title }}
      </span>
    </RouterLink> -->
    <RouterLink v-else-if="normalizeUrl(item.url)" :to="normalizeUrl(item.url)" class="sidebar-link" :class="{ active: isActive(item.url) }" @click="navigate">
      <template v-if="level === 0">
        <i v-if="item.icon" :class="[item.icon, 'fs-6', 'aside-icon']"></i>
      </template>

      <span v-else class="sidebar-icon"></span>
      <span class="hide-menu" :class="{ 'ps-1': level === 0 }">
        {{ item.title }}
      </span>
    </RouterLink>
    <!-- Disabled Item -->
    <span v-else class="sidebar-link link-disabled" aria-disabled="true">
      <template v-if="level === 0">
        <i v-if="item.icon" :class="[item.icon, 'fs-6', 'aside-icon']"></i>
      </template>

      <span v-else class="sidebar-icon"></span>

      <span class="hide-menu" :class="{ 'ps-1': level === 0 }">
        {{ item.title }}
      </span>
    </span>

    <!-- Nested Submenu -->
    <ul v-if="hasChildren" :class="['collapse', submenuClass, { show: isExpanded }]" :aria-expanded="isExpanded">
      <SidebarHorizontalItem
        v-for="child in children"
        :key="child.id"
        :item="child"
        :level="level + 1"
        :expanded-menus="expandedMenus"
        :parent-ids="[...parentIds, item.id]"
        @toggle="(id, ancestors) => emit('toggle', id, ancestors)"
        @navigate="navigate"
      />
    </ul>
  </li>
</template>
