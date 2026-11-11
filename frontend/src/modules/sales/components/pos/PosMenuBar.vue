<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from "vue";

type PosMenuItem = {
  id?: string;
  label?: string;
  shortcut?: string;
  disabled?: boolean;
  separator?: boolean;
  checked?: boolean;
};

type PosMenu = { id: string; label: string; items: PosMenuItem[] };

const props = defineProps<{
  menus: PosMenu[];
  clock?: string;
  greeting?: string;
  cashier?: string;
  fullscreen?: boolean;
  compact?: boolean;
  workspace?: string;
  userTitle?: string;
}>();

const emit = defineEmits<{ action: [id: string] }>();

const root = ref<HTMLElement | null>(null);
const openId = ref<string | null>(null);

function toggle(id: string): void {
  openId.value = openId.value === id ? null : id;
}

// Seperti Windows: saat satu menu terbuka, hover ke menu lain langsung berpindah.
function hover(id: string): void {
  if (openId.value) openId.value = id;
}

function pick(item: PosMenuItem): void {
  if (item.disabled || !item.id) return;
  openId.value = null;
  emit("action", item.id);
}

function onDocumentDown(event: MouseEvent): void {
  if (openId.value && !root.value?.contains(event.target as Node)) openId.value = null;
}

function onKeydown(event: KeyboardEvent): void {
  if (!openId.value) return;

  if (event.key === "Escape") {
    openId.value = null;
    return;
  }

  if (event.key === "ArrowRight" || event.key === "ArrowLeft") {
    const ids = props.menus.map((menu) => menu.id);
    const step = event.key === "ArrowRight" ? 1 : -1;
    openId.value = ids[(ids.indexOf(openId.value) + step + ids.length) % ids.length];
  }
}

onMounted(() => {
  document.addEventListener("mousedown", onDocumentDown);
  document.addEventListener("keydown", onKeydown);
});

onBeforeUnmount(() => {
  document.removeEventListener("mousedown", onDocumentDown);
  document.removeEventListener("keydown", onKeydown);
});
</script>

<template>
  <nav ref="root" class="pos-menubar" role="menubar" aria-label="Menu kasir">
    <div v-for="menu in menus" :key="menu.id" class="pos-menu">
      <button type="button" class="pos-menu-btn" :class="{ 'is-open': openId === menu.id }" role="menuitem" aria-haspopup="true" :aria-expanded="openId === menu.id" @click="toggle(menu.id)" @mouseenter="hover(menu.id)">
        {{ menu.label }}
      </button>

      <ul v-if="openId === menu.id" class="pos-menu-list" role="menu">
        <template v-for="(item, index) in menu.items" :key="index">
          <li v-if="item.separator" class="pos-menu-sep" role="separator"></li>

          <li v-else role="none">
            <button type="button" class="pos-menu-item" role="menuitem" :disabled="item.disabled" @click="pick(item)">
              <span class="pos-menu-check">{{ item.checked ? "✓" : "" }}</span>
              <span class="pos-menu-label">{{ item.label }}</span>
              <kbd v-if="item.shortcut">{{ item.shortcut }}</kbd>
            </button>
          </li>
        </template>
      </ul>
    </div>
    <div class="pos-menubar-end">
      <span v-if="clock" class="pos-menubar-clock" :title="greeting">
        <i class="ti ti-clock"></i>
        {{ clock }}
      </span>

      <button type="button" class="pos-menubar-icon" :title="compact ? 'Tampilkan header' : 'Mode ringkas'" :aria-label="compact ? 'Tampilkan header' : 'Mode ringkas'" @click="emit('action', 'compact')">
        <i :class="compact ? 'ti ti-layout-navbar-expand' : 'ti ti-layout-navbar-collapse'"></i>
      </button>

      <button type="button" class="pos-menubar-icon" :title="fullscreen ? 'Keluar layar penuh' : 'Layar penuh'" :aria-label="fullscreen ? 'Keluar layar penuh' : 'Layar penuh'" @click="emit('action', 'fullscreen')">
        <i :class="fullscreen ? 'ti ti-arrows-minimize' : 'ti ti-arrows-maximize'"></i>
      </button>
      <span v-if="workspace" class="pos-menubar-workspace" title="Organisasi / unit aktif">
        <i class="ti ti-building"></i>
        {{ workspace }}
      </span>
      <span v-if="cashier" class="pos-menubar-user" title="userTitle || 'Kasir yang sedang aktif'">
        <i class="ti ti-user-circle"></i>
        {{ cashier }}
      </span>
    </div>
  </nav>
</template>
