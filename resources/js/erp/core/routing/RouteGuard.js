import { useAuthStore } from "../../stores/auth.js";
import { initializeNavigation, clearNavigation } from "../navigation/NavigationManager.js";

export function installRouteGuard(router) {
  router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (!auth.initialized) {
      await auth.restoreSession();
    }

    if (!auth.isAuthenticated) {
      clearNavigation(router);

      if (to.matched.some((record) => record.meta.requiresAuth)) {
        return {
          name: "login",
          query: {
            redirect: to.fullPath,
          },
          replace: true,
        };
      }

      return true;
    }

    if (to.matched.some((record) => record.meta.guestOnly)) {
      return {
        name: "dashboard",
        replace: true,
      };
    }

    await initializeNavigation(router);

    // Jika URL awal belum dikenali karena route dinamis
    // belum didaftarkan, cocokkan ulang setelah registrasi.
    const resolved = router.resolve(to.fullPath);

    const hasDynamicRoute = resolved.matched.some((record) => record.meta.dynamic);

    const alreadyMatchedDynamic = to.matched.some((record) => record.meta.dynamic);

    if (hasDynamicRoute && !alreadyMatchedDynamic) {
      return {
        path: to.fullPath,
        replace: true,
      };
    }

    return true;
  });
}
