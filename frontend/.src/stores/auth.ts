import { defineStore } from "pinia";
import axios from "axios";

import {
    authApi,
    type AuthUser,
    type LoginPayload,
    type ApiResponse,
} from "@modules/auth/api/auth.api";

import { usePermissionStore } from "@/stores/permission";
import { useOrganizationContextStore } from "@/modules/organization/stores/organizationContextStore";
interface AuthState {
    user: AuthUser | null;
    isAuthenticated: boolean;
    initialized: boolean;
    loading: boolean;
    error: string | null;
}

function getMessage(message: string | string[] | undefined): string {
    if (Array.isArray(message)) {
        return message.join(", ");
    }

    return message ?? "Terjadi kesalahan.";
}

export const useAuthStore = defineStore("auth", {
    state: (): AuthState => ({
        user: null,
        isAuthenticated: false,
        initialized: false,
        loading: false,
        error: null,
    }),

    actions: {
        async login(payload: LoginPayload): Promise<boolean> {
            this.loading = true;
            this.error = null;

            try {
                const response = await authApi.login(payload);
                const result = response.data;

                if (result.error) {
                    this.error = getMessage(result.message);
                    return false;
                }

                this.user = result.data?.user ?? null;
                this.isAuthenticated = true;
                this.initialized = true;

                return true;
            } catch (error: unknown) {
                if (axios.isAxiosError<ApiResponse>(error)) {
                    this.error = getMessage(
                        error.response?.data?.message
                    );
                } else {
                    this.error = "Login gagal diproses.";
                }

                return false;
            } finally {
                this.loading = false;
            }
        },

        async logout(): Promise<boolean> {
    this.loading = true;
    this.error = null;

    try {
        await authApi.logout();

        this.clearAuth();

        return true;
    } catch (error: unknown) {
        if (
            axios.isAxiosError(error) &&
            error.response?.status === 401
        ) {
            this.clearAuth();
            return true;
        }

        this.error = "Logout gagal diproses.";
        return false;
    } finally {
        this.loading = false;
    }
},

       async initializeSession(): Promise<boolean> {
    if (this.initialized) {
        return this.isAuthenticated;
    }

    this.loading = true;

    try {
        const response = await authApi.me();
        const result = response.data;

        // API mengembalikan profil langsung di data.
        const user = result.data as AuthUser | undefined;

        if (result.error || !user || !user.id) {
            this.clearAuth();
            return false;
        }

        this.user = user;
        this.isAuthenticated = true;

        return true;
    } catch {
        this.clearAuth();
        return false;
    } finally {
        this.initialized = true;
        this.loading = false;
    }
},

        setUser(user: AuthUser | null): void {
            this.user = user;
            this.isAuthenticated = user !== null;
            this.initialized = true;
        },

        clearAuth(): void {
            this.user = null;
            this.isAuthenticated = false;
            this.initialized = true;

            usePermissionStore().reset();
             useOrganizationContextStore().reset();
        },
    },
});
