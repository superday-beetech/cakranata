/* import type { Component } from "vue";
import type { RouteRecordRaw } from "vue-router";

const pages = import.meta.glob<Component>(
    "../modules//views//.vue"
);

export function resolveDynamicPage(url: string): RouteRecordRaw {
    const segments = url
        .replace(/^\/+|\/+$/g, "")
        .split("/");

    const page = segments.at(-1)?.toLowerCase() ?? "";

    const matches = Object.entries(pages).filter(([path]) => {
        const parts = path.split("/");

        const moduleName = parts[2]?.toLowerCase();
        const filename = parts.at(-1)
            ?.replace(/\.vue$/i, "")
            .toLowerCase();

        const moduleMatches =
            moduleName === page || moduleName === `${page}s`;

        const componentMatches =
            filename === `${page}view` ||
            filename === `${page}sview`;

        return moduleMatches && componentMatches;
    });

    if (matches.length !== 1) {
        return {
            path: url,
            name: `DynamicNotFound_${page}`,
            component: () => import("@/components/NotFoundView.vue"),
        };
    }

    const [componentPath, loader] = matches[0];

    return {
        path: url,
        name: `Dynamic_${page}`,
        component: loader,
        meta: {
            layout: "backend",
            requiresAuth: true,
            componentPath,
        },
    };
}
 */



import type { Component } from "vue";
import type { RouteRecordRaw } from "vue-router";

const pages = import.meta.glob<Component>(
    "../modules/*/views/**/*.vue"
);

export function resolveDynamicPage(url: string): RouteRecordRaw {
    const segments = url
        .replace(/^\/+|\/+$/g, "")
        .split("/")
        .filter(Boolean);

    const moduleName = segments[0]?.toLowerCase() ?? "";
    const pageName = segments[1]?.toLowerCase() ?? "";

    const matches = Object.entries(pages).filter(([path]) => {
        const match = path.match(
            /^\.\.\/modules\/([^/]+)\/views\/(.+)\.vue$/i
        );

        if (!match) return false;

        const [, folder, relativePath] = match;

        const filename = relativePath
            .split("/")
            .at(-1)
            ?.replace(/\.vue$/i, "")
            .toLowerCase();

        const currentModule = folder.toLowerCase();

        // URL satu segmen: cari nama view berdasarkan nama module.
        // URL dua segmen: cari nama view berdasarkan segmen kedua.
        const expectedPage = pageName || moduleName;

        return (
            currentModule === moduleName &&
            filename === `${expectedPage}view`
        );
    });

    if (matches.length !== 1) {
        return {
            path: url,
            name: `DynamicNotFound_${moduleName}_${pageName}`,
            component: () => import("@/components/NotFoundView.vue"),
            meta: {
                layout: moduleName === "login" ? "auth" : "backend",
                requiresAuth: moduleName !== "login",
            },
        };
    }

    const [componentPath, loader] = matches[0];

    return {
        path: url,
        name: `Dynamic_${moduleName}_${pageName || moduleName}`,
        component: loader,
        meta: {
            layout: moduleName === "login" ? "auth" : "backend",
            requiresAuth: moduleName !== "login",
            componentPath,
            module: moduleName,
            page: pageName || moduleName,
        },
    };
}
