<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import { useNavigationStore } from "@/stores/navigation";

import type { NavigationMenu, MenuFormPayload, MenuPosition, MenuSection } from "@/types/navigation";

import { offcanvas } from "@/utils/offcanvas";
import { notify } from "@/utils/notification";

const props = defineProps<{
  menu: NavigationMenu | null;
  roles: string[];
  parent_id?: string | number | null;
  parentOptions: NavigationMenu[];
  position: MenuPosition;
  section: MenuSection;
  onSaved?: () => void | Promise<void>;
}>();

const store = useNavigationStore();

const submitting = ref(false);
const validationError = ref("");

const form = reactive({
  id: "",
  title: "",
  url: "",
  icon: "",
  //   parent_id: "",
  parent_id: props.menu ? props.menu.parent_id : (props.parent_id ?? ""),
  position: "sidebar" as MenuPosition,
  section: "backend" as MenuSection,
  is_active: true,
  groups: [] as string[],
});

const isEdit = computed(() => Boolean(form.id));

// Mengurutkan parent menu berdasarkan sequence dan menyusun struktur hirarki dengan indentasi
// Mengurutkan parent menu secara rekursif berdasarkan sequence dan kedalaman level
const availableParents = computed(() => {
  const items = props.parentOptions.filter((item) => item.id !== form.id);

  // Kelompokkan item berdasarkan parent_id
  const childrenMap = new Map<string, NavigationMenu[]>();
  items.forEach((item) => {
    const parentId = item.parent_id && item.parent_id !== "0" ? item.parent_id : "root";
    const list = childrenMap.get(parentId) || [];
    list.push(item);
    childrenMap.set(parentId, list);
  });

  // Urutkan tiap grup berdasarkan sequence
  childrenMap.forEach((list) => {
    list.sort((a, b) => Number(a.sequence ?? 0) - Number(b.sequence ?? 0));
  });

  const hierarchicalList: { item: NavigationMenu; level: number }[] = [];

  // Fungsi rekursif untuk menelusuri cabang secara mendalam
  function flattenTree(parentId: string, level: number) {
    const children = childrenMap.get(parentId) || [];
    children.forEach((child) => {
      hierarchicalList.push({ item: child, level });
      // Panggil rekursif untuk anak dari child ini
      flattenTree(child.id, level + 1);
    });
  }

  // Mulai dari level root (parent utama)
  flattenTree("root", 0);

  // Fallback untuk item yang parent_id-nya tidak ditemukan (yatim piatu)
  items.forEach((item) => {
    if (!hierarchicalList.some((h) => h.item.id === item.id)) {
      hierarchicalList.push({ item, level: 0 });
    }
  });

  return hierarchicalList;
});

function resetForm() {
  form.id = "";
  form.title = "";
  form.url = "";
  form.icon = "";
  // Saat reset, gunakan props.parent_id jika ada (untuk tambah submenu), jika tidak kosongkan
  form.parent_id = props.parent_id ?? "";
  form.position = props.position;
  form.section = props.section;
  form.is_active = true;
  form.groups = [];
  validationError.value = "";
}

function populateForm(menu: NavigationMenu | null) {
  resetForm();

  if (!menu) {
    // Jika mode tambah, pastikan parent_id mengambil dari props.parent_id terbaru
    form.parent_id = props.parent_id ?? "";
    return;
  }

  form.id = menu.id;
  form.title = menu.title;
  form.url = menu.url;
  form.icon = menu.icon;
  form.parent_id = menu.parent_id === "0" ? "" : menu.parent_id;
  form.position = menu.position;
  form.section = menu.section;
  form.is_active = menu.is_active === "1";
  form.groups = [...(menu.selected_groups ?? [])];
}

watch(
  () => props.parent_id,
  (newVal) => {
    if (!props.menu) {
      form.parent_id = newVal ?? "";
    }
  },
);

watch(
  () => props.menu,
  (menu) => populateForm(menu),
  { immediate: true },
);

function validate(): boolean {
  validationError.value = "";

  if (!form.title.trim()) {
    validationError.value = "Judul menu wajib diisi.";
    return false;
  }

  if (form.id && form.parent_id === form.id) {
    validationError.value = "Menu tidak dapat menjadi parent untuk dirinya sendiri.";
    return false;
  }

  return true;
}

async function submit() {
  if (!validate() || submitting.value) return;

  submitting.value = true;

  try {
    const payload: MenuFormPayload = {
      id: form.id || undefined,
      title: form.title.trim(),
      url: form.url.trim(),
      icon: form.icon.trim(),
      parent_id: form.parent_id ? String(form.parent_id) : "0",
      position: form.position,
      section: form.section,
      is_active: form.is_active,
      groups: [...form.groups],
    };

    await store.saveMenu(payload);

    notify.success(isEdit.value ? "Menu berhasil diperbarui." : "Menu berhasil ditambahkan.");

    await props.onSaved?.();
    offcanvas.close();
  } catch (error) {
    notify.error(error instanceof Error ? error.message : "Gagal menyimpan menu.");
  } finally {
    submitting.value = false;
  }
}

function close() {
  offcanvas.close();
}
</script>

<template>
  <form class="menu-form" @submit.prevent="submit">
    <div class="mb-3">
      <label class="form-label" for="navigation-title"> Judul Menu <span class="text-danger">*</span> </label>

      <input id="navigation-title" v-model="form.title" type="text" class="form-control" placeholder="Contoh: Dashboard" maxlength="150" required />
    </div>

    <div class="mb-3">
      <label class="form-label" for="navigation-url"> URL </label>

      <input id="navigation-url" v-model="form.url" type="text" class="form-control" placeholder="/backend/dashboard" />
    </div>

    <div class="mb-3">
      <label class="form-label" for="navigation-icon"> Icon </label>

      <input id="navigation-icon" v-model="form.icon" type="text" class="form-control" placeholder="ti ti-home" />

      <small class="text-muted"> Gunakan class ikon yang tersedia pada theme. </small>
    </div>

    <div class="mb-3">
      <label class="form-label" for="navigation-parent"> Parent Menu </label>

      <select id="navigation-parent" v-model="form.parent_id" class="form-select">
        <option value="">Menu Utama</option>

        <option v-for="{ item, level } in availableParents" :key="item.id" :value="item.id">
          {{ level > 0 ? "— ".repeat(level) + " " + item.title : item.title }}
        </option>
      </select>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label" for="navigation-position"> Posisi </label>

        <select id="navigation-position" v-model="form.position" class="form-select">
          <option value="sidebar">Sidebar</option>
          <option value="topbar">Topbar</option>
          <option value="quick_links">Quick Links</option>
          <option value="apps">Apps</option>
          <option value="down">Down</option>
        </select>
      </div>

      <div class="col-md-6 mb-3">
        <label class="form-label" for="navigation-section"> Section </label>

        <select id="navigation-section" v-model="form.section" class="form-select">
          <option value="backend">Backend</option>
          <option value="frontend">Frontend</option>
        </select>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label d-block">Hak Akses Group</label>

      <div v-if="roles.length === 0" class="text-muted small">Belum ada group tersedia.</div>

      <div v-for="role in roles" :key="role" class="form-check">
        <input :id="`navigation-group-${role}`" v-model="form.groups" class="form-check-input" type="checkbox" :value="role" />

        <label class="form-check-label" :for="`navigation-group-${role}`">
          {{ role }}
        </label>
      </div>
    </div>

    <div class="form-check form-switch mb-4">
      <input id="navigation-active" v-model="form.is_active" class="form-check-input" type="checkbox" role="switch" />

      <label class="form-check-label" for="navigation-active"> Menu Aktif </label>
    </div>

    <div v-if="validationError" class="alert alert-danger py-2" role="alert">
      {{ validationError }}
    </div>

    <div class="d-flex justify-content-end gap-2 border-top pt-3">
      <button type="button" class="btn btn-light" :disabled="submitting" @click="close">Batal</button>

      <button type="submit" class="btn btn-primary" :disabled="submitting">
        <span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>

        {{ submitting ? "Menyimpan..." : "Simpan Menu" }}
      </button>
    </div>
  </form>
</template>
