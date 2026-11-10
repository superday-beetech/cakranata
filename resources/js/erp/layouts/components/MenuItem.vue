<script setup>
import { computed } from "vue";
import { RouterLink, useRoute } from "vue-router";

defineOptions({ name: "MenuItem" });

const props = defineProps({
  item: { type: Object, required: true },
  level: { type: Number, default: 0 },
  expandedMenus: { type: Array, default: () => [] },
});

const emit = defineEmits(["toggle", "navigate"]);
const route = useRoute();

const children = computed(() => (Array.isArray(props.item.children) ? props.item.children : []));

const hasChildren = computed(() => children.value.length > 0);

const isExpanded = computed(() => props.expandedMenus.includes(props.item.id));

function isDivider(item) {
  return item.url === "divider" || Number(item.is_divider) === 1;
}

function normalizeUrl(url) {
  if (!url || url === "#" || url === "divider") return "";

  const value = String(url).trim();

  if (/^(https?:)?\/\//i.test(value)) return value;

  return "/" + value.replace(/^\/+/, "");
}

function isExternal(url) {
  return /^(https?:)?\/\//i.test(String(url || "").trim());
}

function isActive(url) {
  const path = normalizeUrl(url);
  if (!path || isExternal(path)) return false;

  return route.path.replace(/\/+$/, "") === path.replace(/\/+$/, "");
}

function hasActiveDescendant(item) {
  return (item.children || []).some((child) => isActive(child.url) || hasActiveDescendant(child));
}

const active = computed(() => isActive(props.item.url) || hasActiveDescendant(props.item));

const submenuClass = computed(() => {
  if (props.level === 0) return "first-level";
  if (props.level === 1) return "two-level";
  return "three-level";
});

function toggle() {
  if (hasChildren.value) emit("toggle", props.item.id);
}

function navigate() {
  emit("navigate");
}

function forwardToggle(id) {
  emit("toggle", id);
}

function forwardNavigate() {
  emit("navigate");
}
</script>

<template>
  <!-- Group heading -->
  <li v-if="isDivider(item)" class="nav-small-cap">
    <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
    <span class="hide-menu">{{ item.title }}</span>
  </li>

  <!-- Menu -->
  <li v-else class="sidebar-item" :class="{ selected: active }">
    <!-- Parent link -->
    <a v-if="hasChildren" href="javascript:void(0)" class="sidebar-link has-arrow" :class="{ active: active }" :aria-expanded="String(isExpanded)" @click.prevent="toggle">
      <i v-if="level === 0 && item.icon" :class="['aside-icon', item.icon]"></i>

      <span v-else-if="level > 0" class="sidebar-icon"></span>

      <span class="hide-menu" :class="{ 'ps-1': level === 0 }">
        {{ item.title }}
      </span>
    </a>

    <!-- External link -->
    <a v-else-if="isExternal(item.url)" :href="normalizeUrl(item.url)" class="sidebar-link" target="_blank" rel="noopener noreferrer">
      <i v-if="level === 0 && item.icon" :class="['aside-icon', item.icon]"></i>
      <span v-else-if="level > 0" class="sidebar-icon"></span>

      <span class="hide-menu" :class="{ 'ps-1': level === 0 }">
        {{ item.title }}
      </span>
    </a>

    <!-- Internal Vue Router link -->
    <RouterLink v-else-if="normalizeUrl(item.url)" :to="normalizeUrl(item.url)" class="sidebar-link" :class="{ active: isActive(item.url) }" @click="navigate">
      <i v-if="level === 0 && item.icon" :class="['aside-icon', item.icon]"></i>
      <!-- <span v-else-if="level > 0" class="sidebar-icon">a</span> -->
      <div v-else-if="level > 0" class="round-16 d-flex align-items-center justify-content-center">
        <i class="ti ti-circle"></i>
      </div>
      <span class="hide-menu" :class="{ 'ps-1': level === 0 }">
        {{ item.title }}
      </span>
    </RouterLink>

    <!-- Item without URL -->
    <span v-else class="sidebar-link link-disabled">
      <i v-if="level === 0 && item.icon" :class="['aside-icon', item.icon]"></i>
      <span v-else-if="level > 0" class="sidebar-icon"></span>

      <span class="hide-menu" :class="{ 'ps-1': level === 0 }">
        {{ item.title }}
      </span>
    </span>

    <!-- Nested submenu -->
    <ul v-if="hasChildren" :class="['collapse', submenuClass]" :aria-expanded="String(isExpanded)" :style="{ display: isExpanded ? 'block' : '' }">
      <MenuItem v-for="child in children" :key="child.id" :item="child" :level="level + 1" :expanded-menus="expandedMenus" @toggle="forwardToggle" @navigate="forwardNavigate" />
    </ul>
  </li>
</template>
