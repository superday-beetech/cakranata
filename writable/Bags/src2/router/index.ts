import {
    createRouter,
    createWebHistory,
    type RouteRecordRaw,
} from "vue-router";

import pinia from "@/stores";

import { useAuthStore } from "@/stores/auth";
import { usePermissionStore } from "@/stores/permission";
// Otomatis menemukan seluruh router.ts di setiap module.
import { useOrganizationContextStore } from
    "@/modules/organization/stores/organizationContextStore";
const moduleRouters = import.meta.glob<RouteRecordRaw[]>(
    "../modules/*/router.ts",
    {
        eager: true,
        import: "default",
    }
);

// Gabungkan seluruh route module.
const moduleRoutes = Object.values(moduleRouters).flat();

const routes: RouteRecordRaw[] = [
    ...moduleRoutes,

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
    path: "/organization/select",
    name: "OrganizationSelection",
    component: () => import(
        "@/modules/organization/master/views/OrganizationSelectionView.vue"
    ),
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
/*
router.beforeEach(async (to) => {
    const auth = useAuthStore(pinia);

    const isOrganizationSelection =
        to.name === "OrganizationSelection";

    // Jika status sudah diketahui dari proses login SPA.
    if (
        to.meta.guestOnly &&
        auth.isAuthenticated
    ) {
        return { name: "Dashboard" };
    }

    // Halaman publik tidak memerlukan pemeriksaan API.
    if (to.name === "Home" || to.name === "Login") {
        return true;
    }

    const permission = usePermissionStore(pinia);

    if (!auth.initialized) {
        await auth.initializeSession();
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return {
            path: "/login",
            query: { redirect: to.fullPath },
        };
    }
    if (to.meta.permission && auth.isAuthenticated) {
        try {
            await permission.load();

            const required = Array.isArray(to.meta.permission)
                ? to.meta.permission
                : [to.meta.permission];

            if (!permission.hasAllPermissions(required)) {
                return { name: "Forbidden" };
            }
        } catch {
            return { name: "Forbidden" };
        }
    }

    return true;
}); */


router.beforeEach(async (to) => {
    const auth = useAuthStore(pinia);

    const isOrganizationSelection =
        to.name === "OrganizationSelection";

    // Jika user sudah login dan membuka halaman guest.
    if (to.meta.guestOnly && auth.isAuthenticated) {
        return { name: "Dashboard" };
    }

    // Halaman publik tidak memerlukan pemeriksaan API.
    if (to.name === "Home" || to.name === "Login") {
        return true;
    }

    // 1. Pastikan session autentikasi sudah diperiksa.
    if (!auth.initialized) {
        await auth.initializeSession();
    }

    // 2. Periksa autentikasi.
    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return {
            path: "/login",
            query: { redirect: to.fullPath },
        };
    }

    if (!auth.isAuthenticated) {
        return true;
    }

    // 3. Inisialisasi Organization Context.
    const organizationContext =
        useOrganizationContextStore(pinia);

    try {
        if (!organizationContext.initialized) {
            await organizationContext.initialize();
        }
    } catch (error) {
        console.error(
            "[Organization Context] Initialization failed:",
            error
        );

        // Arahkan ke halaman pemilihan organisasi agar
        // pengguna dapat melihat pesan kesalahan.
        if (!isOrganizationSelection) {
            return { name: "OrganizationSelection" };
        }

        return true;
    }

    // 4. User belum memiliki organisasi aktif.
    // Semua halaman backend diarahkan ke halaman pemilihan.
    if (
        !organizationContext.hasContext &&
        !isOrganizationSelection
    ) {
        return { name: "OrganizationSelection" };
    }

    // 5. Pemeriksaan permission.
    // Halaman pemilihan organisasi tidak membutuhkan
    // permission modul bisnis.
    if (
        !isOrganizationSelection &&
        to.meta.permission
    ) {
        const permission = usePermissionStore(pinia);

        try {
            await permission.load();

            const required = Array.isArray(to.meta.permission)
                ? to.meta.permission
                : [to.meta.permission];

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
