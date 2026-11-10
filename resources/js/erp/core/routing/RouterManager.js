import { createRouter, createWebHistory } from "vue-router";
import { routes } from "./routes.js";
import { installRouteGuard } from "./RouteGuard.js";
import { installRouteTitleManager } from "./RouteTitleManager.js";

export function createAppRouter() {
  const router = createRouter({
    history: createWebHistory("/erp/"),
    routes,
    scrollBehavior() {
      return { top: 0 };
    },
  });

  installRouteGuard(router);
  installRouteTitleManager(router);

  return router;
}
