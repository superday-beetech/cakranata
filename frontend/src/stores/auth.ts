/* frontend/src/stores/auth.ts */
import { defineStore } from "pinia";
import axios from "axios";

import { authApi, type AuthUser, type LoginPayload } from "@modules/auth/api/auth.api";

import { usePermissionStore } from "@/stores/permission";
import { useOrganizationContextStore } from "@/stores/organization";
import { getMessage, extractData } from "@/utils/api";

interface AuthState {
  user: AuthUser | null;
  isAuthenticated: boolean;
  initialized: boolean;
  loading: boolean;
  error: string | null;
}
let sessionPromise: Promise<AuthUser | null> | null = null;
function clearConsole(): void {
  if (typeof console !== "undefined" && typeof console.clear === "function") {
    console.clear();
  }
}

export const useAuthStore = defineStore("auth", {
  state: (): AuthState => ({
    user: null,
    isAuthenticated: false,
    initialized: false,
    loading: false,
    error: null,
  }),

  getters: {
    isLoggedIn: (state) => state.isAuthenticated && !!state.user,
  },

  actions: {
    setUser(user: AuthUser | null) {
      this.user = user;
      this.isAuthenticated = !!user;
    },

    resetAuth() {
      this.user = null;
      this.isAuthenticated = false;
      this.error = null;
    },

    async login(payload: LoginPayload) {
      this.loading = true;
      this.error = null;
      try {
        const res = await authApi.login(payload);
        const data = extractData<any>(res.data);

        // Backend: jika sudah login -> data.authenticated = true, tanpa user
        // Backend: login sukses -> data.user (Result object / user)
        // Apapun bentuknya, ambil profil lengkap dari /me agar konsisten.
        const user = await this.fetchUser();

        if (!user) {
          // login "sukses" tapi /me gagal -> anggap gagal
          throw new Error("Gagal mengambil profil setelah login.");
        }
        clearConsole();

        return user;
      } catch (err) {
        if (axios.isAxiosError(err)) {
          this.error = getMessage(err.response?.data?.message);
        } else if (err instanceof Error) {
          this.error = getMessage(err.message);
        } else {
          this.error = getMessage(undefined);
        }
        this.resetAuth();
        throw err;
      } finally {
        this.loading = false;
      }
    },

    async fetchUser() {
      this.loading = true;
      this.error = null;
      try {
        const res = await authApi.me();
        // Backend me() -> data = profile langsung
        const profile = extractData<AuthUser>(res.data);
        this.setUser(profile ?? null);
        return this.user;
      } catch (err) {
        this.resetAuth();
        if (axios.isAxiosError(err) && err.response?.status !== 401) {
          this.error = getMessage(err.response?.data?.message);
        }
        return null;
      } finally {
        this.initialized = true; // wajib: guard route tidak loop
        this.loading = false;
      }
    },

    async initializeSession(force = false): Promise<AuthUser | null> {
      if (this.initialized && !force) {
        return this.user;
      }

      if (!sessionPromise) {
        sessionPromise = this.fetchUser().finally(() => {
          sessionPromise = null;
        });
      }

      return sessionPromise;
    },

    async logout(): Promise<boolean> {
      this.loading = true;
      try {
        await authApi.logout();
      } catch {
        // abaikan; tetap bersihkan state lokal
      } finally {
        this.resetAuth();
        this.initialized = true;
        this.loading = false;

        usePermissionStore().$reset();
        useOrganizationContextStore().$reset();

        clearConsole();
      }
      return true;
    },
  },
});
