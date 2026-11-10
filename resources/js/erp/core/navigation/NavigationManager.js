import { useMenuStore } from "../../stores/menu.js";
import { registerDynamicRoutes, clearDynamicRoutes } from "../routing/DynamicRouteManager.js";

let routesRegistered = false;

export async function initializeNavigation(router) {
  const menuStore = useMenuStore();

  if (routesRegistered && menuStore.loaded) {
    return menuStore.menus;
  }

  const menus = await menuStore.load();

  registerDynamicRoutes(router, menus);

  routesRegistered = true;

  return menus;
}

export function clearNavigation(router) {
  clearDynamicRoutes(router);

  const menuStore = useMenuStore();
  menuStore.clear();

  routesRegistered = false;
}
