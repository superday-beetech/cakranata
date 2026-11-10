import { defineStore } from "pinia";
import { fetchMenus } from "../core/navigation/MenuServices.js";

let menuRequest = null;

export const useMenuStore = defineStore("menu", {
  state: () => ({
    menus: [],
    loading: false,
    loaded: false,
    error: null,
  }),

  actions: {
    async load({ force = false } = {}) {
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
          const menus = await fetchMenus();
          //   console.log("[MenuStore] Data diterima:", menus);

          this.menus = menus;
          this.loaded = true;

          //   console.log("[MenuStore] State:", this.menus);
          this.menus = menus;
          this.loaded = true;

          return menus;
        } catch (error) {
          this.error = error.message;
          throw error;
        } finally {
          this.loading = false;
          menuRequest = null;
        }
      })();

      return menuRequest;
    },

    clear() {
      this.menus = [];
      this.loaded = false;
      this.loading = false;
      this.error = null;
    },
  },
});
