import { type RouteRecordRaw } from "vue-router";

// Lazy load — lebih ringan untuk bundle awal
const PrintTemplateList = () => import("@/modules/settings/views/PrintTemplateList.vue");
const PrintTemplateDesigner = () => import("@/modules/settings/views/PrintTemplateDesigner.vue");
const PrintTemplateShow = () => import("@/modules/settings/views/PrintTemplateShow.vue");

const settingsRoutes: RouteRecordRaw[] = [
  // ==================== TEMPLATE CETAK ====================

  // List
  {
    path: "/settings/print-templates",
    name: "settings.print-templates.list",
    component: PrintTemplateList,
    meta: {
      title: "Template Cetak",
      requiresAuth: true,
    },
  },

  // Create — HARUS sebelum :id agar "create" tidak tertangkap sebagai :id
  {
    path: "/settings/print-templates/create",
    name: "settings.print-templates.create",
    component: PrintTemplateDesigner,
    meta: {
      title: "Buat Template Cetak",
      requiresAuth: true,
    },
  },

  // Edit
  {
    path: "/settings/print-templates/:id/edit",
    name: "settings.print-templates.edit",
    component: PrintTemplateDesigner,
    props: true,
    meta: {
      title: "Edit Template Cetak",
      requiresAuth: true,
    },
  },

  // Show (read-only)
  {
    path: "/settings/print-templates/:id/show",
    name: "settings.print-templates.show",
    component: PrintTemplateShow,
    props: true,
    meta: {
      title: "Detail Template Cetak",
      requiresAuth: true,
    },
  },
];

export default settingsRoutes;
