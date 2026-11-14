/* frontend/src/router/index.ts */
import { createRouter, createWebHistory, type RouteRecordRaw } from "vue-router";

import pinia from "@/stores";

import { useAuthStore } from "@/stores/auth";
import { usePermissionStore } from "@/stores/permission";
// Otomatis menemukan seluruh router.ts di setiap module.
import { useOrganizationContextStore } from "@/stores/organization";
const moduleRouters = import.meta.glob<RouteRecordRaw[]>("../modules/*/router.ts", {
  eager: true,
  import: "default",
});

// Gabungkan seluruh route module.
const moduleRoutes = Object.values(moduleRouters).flat();

const routes: RouteRecordRaw[] = [
  ...moduleRoutes,
  {
    path: "/sales/dashboard",
    name: "PharmacyPOSDashboard",
    component: () => import("@/modules/sales/views/DashboardView.vue"),
    meta: {
      layout: "pos",
      requiresAuth: true,
      title: "Dashboard POS",
    },
  },
  {
    path: "/sales/pos",
    name: "PharmacyPOS",
    component: () => import("@components/DynamicPage.vue"),
    meta: {
      layout: "pos",
      requiresAuth: true,
      title: "Point of Sale",
    },
  },
  {
    path: "/403",
    name: "Forbidden",
    component: () => import("@components/ForbiddenView.vue"),
    meta: {
      layout: "backend",
      requiresAuth: true,
    },
  },
  {
    path: "/offline",
    name: "Offline",
    component: () => import("@/components/OfflineView.vue"),
    meta: { layout: "auth" },
  },
  {
    path: "/offline/home",
    name: "OfflineHome",
    component: () => import("@/components/OfflineHomeView.vue"),
    meta: { layout: "auth" },
  },
  {
    path: "/organization/select",
    name: "OrganizationSelection",
    component: () => import("@/modules/auth/views/OrganizationSelectionView.vue"),
    meta: {
      layout: "backend",
      requiresAuth: true,
    },
  },

  // Semua URL yang tidak cocok dengan custom route
  // akan ditangani DynamicPage.
  {
    path: "/:pathMatch(.*)*",
    name: "DynamicPage",
    component: () => import("@components/DynamicPage.vue"),
    meta: {
      layout: "backend",
      requiresAuth: true,
    },
  },
];

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,

  scrollBehavior() {
    return { top: 0 };
  },
});

router.beforeEach(async (to, from) => {
  const auth = useAuthStore(pinia);

  const isOrganizationSelection = to.name === "OrganizationSelection";

  // 1. Halaman yang membutuhkan autentikasi tidak dapat dibuka
  // ketika jaringan terputus karena belum tersedia penyimpanan offline.
  if (!navigator.onLine && to.name !== "Offline" && to.meta.requiresAuth) {
    return {
      name: "Offline",
      query: {
        redirect: to.fullPath,
      },
    };
  }

  // 2. Guest-only: user sudah login → ke Dashboard.
  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: "Dashboard" };
  }

  // 3. Halaman publik tidak perlu cek API.
  if (to.name === "Home" || to.name === "Login") {
    return true;
  }

  // 4. Pastikan session autentikasi sudah diperiksa.
  if (!auth.initialized) {
    await auth.initializeSession();
  }

  // 5. Periksa autentikasi.
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return {
      path: "/login",
      query: { redirect: to.fullPath },
    };
  }

  if (!auth.isAuthenticated) {
    return true;
  }

  // 6. Inisialisasi Organization Context.
  const organizationContext = useOrganizationContextStore(pinia);

  try {
    if (!organizationContext.initialized) {
      await organizationContext.initialize();
    }
  } catch (error) {
    console.error("[Organization Context] Initialization failed:", error);

    if (!isOrganizationSelection) {
      return { name: "OrganizationSelection" };
    }

    return true;
  }

  // 7. User belum punya organisasi aktif.
  if (!organizationContext.hasContext && !isOrganizationSelection) {
    return { name: "OrganizationSelection" };
  }

  // 8. Pemeriksaan permission.
  if (!isOrganizationSelection && to.meta.permission) {
    const permission = usePermissionStore(pinia);

    try {
      await permission.load();

      const required = Array.isArray(to.meta.permission) ? to.meta.permission : [to.meta.permission];

      if (!permission.hasAllPermissions(required)) {
        return { name: "Forbidden" };
      }
    } catch {
      return { name: "Forbidden" };
    }
  }

  return true;
});

export default router;
