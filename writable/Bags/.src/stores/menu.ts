
import { defineStore } from "pinia";
import api from "@/api/client";

import { useAuthStore } from "@/stores/auth";
import type { MenuItem, MenuState, MenuCollection } from "@/types/menu";

let menuRequest: Promise<MenuCollection> | null = null;

const emptyMenus = (): MenuCollection => ({
    sidebar: [],
    apps: [],
    quick_links: [],
    topbar: [],
});

export const useMenuStore = defineStore("menu", {
    state: (): MenuState => ({
        menus: emptyMenus(),
        loading: false,
        loaded: false,
        error: null,
    }),

    actions: {
        async load(
            options: { force?: boolean } = {}
        ): Promise<MenuCollection> {
            const { force = false } = options;
            const auth = useAuthStore();

            // Guest tidak perlu memuat menu backend.
            if (!auth.isAuthenticated) {
                this.clear();
                return emptyMenus();
            }

            if (this.loaded && !force) {
                return this.menus;
            }

            if (menuRequest) {
                return menuRequest;
            }

            this.loading = true;
            this.error = null;

            menuRequest = (async () => {
                try {
                    const response = await api.get("/navigation");

                    const result = response.data;
                    const menus = result?.data?.menus;

                    if (!menus || typeof menus !== "object" || Array.isArray(menus)) {
                        throw new Error("Format respons API menu tidak valid.");
                    }

                    this.menus = {
                        sidebar: menus.sidebar ?? [],
                        apps: menus.apps ?? [],
                        quick_links: menus.quick_links ?? [],
                        topbar: menus.topbar ?? [],
                    };

                    this.loaded = true;

                    return this.menus;
                } catch (error: unknown) {
                    this.error =
                        error instanceof Error
                            ? error.message
                            : "Gagal memuat menu.";

                    throw error;
                } finally {
                    this.loading = false;
                    menuRequest = null;
                }
            })();

            return menuRequest;
        },

        clear(): void {
            this.menus = emptyMenus();
            this.loaded = false;
            this.loading = false;
            this.error = null;
        },
    },
});
