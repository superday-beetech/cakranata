import { resolvePage } from "./PageResolver.js";

const registeredRoutes = new Set();

function normalizePath(url) {
  const value = String(url || "").trim();

  if (!value || value === "#" || value === "divider" || /^(https?:\/\/|\/\/|mailto:|tel:)/i.test(value)) {
    return "";
  }

  return value.split("?")[0].replace(/^\/+|\/+$/g, "");
}

function collectPages(items, result = []) {
  for (const item of items || []) {
    if (Number(item.is_active) !== 1 || item.is_divider === true) {
      continue;
    }

    const children = Array.isArray(item.children) ? item.children : [];

    // Parent dengan submenu hanya menjadi wadah navigasi.
    if (children.length > 0) {
      collectPages(children, result);
      continue;
    }

    const path = normalizePath(item.url);

    if (!path) continue;

    const component = resolvePage(path);

    if (!component) {
      console.warn(`[DynamicRouter] Komponen tidak ditemukan untuk: ${path}`);
      continue;
    }

    result.push({
      id: item.id,
      path,
      name: `menu-${item.id}`,
      title: item.title,
      component,
    });
  }

  return result;
}

export function registerDynamicRoutes(router, menus) {
  clearDynamicRoutes(router);

  const pages = collectPages(menus);
  const registeredPaths = new Set();

  for (const page of pages) {
    if (registeredPaths.has(page.path)) {
      console.warn(`[DynamicRouter] URL duplikat: ${page.path}`);
      continue;
    }

    registeredPaths.add(page.path);

    router.addRoute("app-layout", {
      path: page.path,
      name: page.name,
      component: page.component,
      meta: {
        dynamic: true,
        menuId: page.id,
        title: page.title,
        requiresAuth: true,
      },
    });

    registeredRoutes.add(page.name);
  }

  return pages;
}

export function clearDynamicRoutes(router) {
  for (const name of registeredRoutes) {
    if (router.hasRoute(name)) {
      router.removeRoute(name);
    }
  }

  registeredRoutes.clear();
}
