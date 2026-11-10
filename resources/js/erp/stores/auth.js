import { defineStore } from "pinia";
import { siteUrl } from "../utils/url.js";

export const useAuthStore = defineStore("auth", {
  state: () => ({
    user: null,
    accessToken: null,
    authenticated: false,
    loading: false,
    initialized: false,
    error: null,
  }),

  getters: {
    isAuthenticated: (state) => state.authenticated,
    hasAccessToken: (state) => Boolean(state.accessToken),
  },

  actions: {
    setUser(user) {
      this.user = user ?? null;
    },

    setAccessToken(token) {
      this.accessToken = typeof token === "string" && token.trim() ? token.trim() : null;
    },

    clearAuth() {
      this.user = null;
      this.accessToken = null;
      this.authenticated = false;
    },

    async login(credentials) {
      if (this.loading) {
        throw new Error("Proses login sedang berlangsung.");
      }

      if (typeof window.apiFetch !== "function") {
        throw new Error("Global API client belum tersedia.");
      }

      this.loading = true;
      this.error = null;

      try {
        const response = await window.apiFetch(siteUrl("api/v1/auth/auth-token/login"), {
          method: "POST",
          auth: "cookie",
          body: {
            identity: credentials.login.trim(),
            password: credentials.password,
            device_name: "web_erp",
            remember: credentials.remember ?? false,
          },
        });

        if (!response.ok) {
          throw new Error(response.data?.messages?.error ?? response.data?.message ?? response.data?.error ?? `Login gagal (HTTP ${response.status}).`);
        }

        const payload = response.data?.data;

        if (!payload || payload.auth_mode !== "cookie" || !payload.user) {
          throw new Error("Respons login tidak sesuai dengan format autentikasi cookie.");
        }

        // Token berada di HttpOnly cookie, bukan di JSON atau Pinia.
        this.setAccessToken(null);
        this.setUser(payload.user);
        this.authenticated = true;
        this.initialized = true;
        this.error = null;

        return response.data;
      } catch (error) {
        this.clearAuth();
        this.initialized = true;
        this.error = error?.message ?? "Terjadi kesalahan saat login.";

        throw error;
      } finally {
        this.loading = false;
      }
    },

    async restoreSession() {
      if (this.initialized) {
        return this.authenticated;
      }

      if (typeof window.apiFetch !== "function") {
        throw new Error("Global API client belum tersedia.");
      }

      this.loading = true;

      try {
        const response = await window.apiFetch(siteUrl("api/v1/auth/auth-token/me"), {
          method: "GET",
          auth: "cookie",
        });

        if (!response.ok) {
          this.clearAuth();
          return false;
        }

        const payload = response.data?.data;
        const user = payload?.user ?? payload;

        if (!user || !user.id) {
          this.clearAuth();
          return false;
        }

        this.setUser(user);
        this.setAccessToken(null);
        this.authenticated = true;

        return true;
      } catch {
        this.clearAuth();
        return false;
      } finally {
        this.initialized = true;
        this.loading = false;
      }
    },

    logoutLocal() {
      this.clearAuth();
      this.error = null;
      this.initialized = true;
    },

    reset() {
      this.clearAuth();
      this.error = null;
      this.initialized = false;
      this.loading = false;
    },
  },
});
