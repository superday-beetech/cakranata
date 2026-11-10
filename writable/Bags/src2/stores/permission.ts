/* import { defineStore } from "pinia";

interface PermissionState {
    permissions: string[];
    loaded: boolean;
    loading: boolean;
}

export const usePermissionStore = defineStore("permission", {
    state: (): PermissionState => ({
        permissions: [],
        loaded: false,
        loading: false,
    }),

    getters: {
        hasPermission: (state) => (permission: string) =>
            state.permissions.includes(permission),

        hasAnyPermission: (state) => (permissions: string[]) =>
            permissions.some((permission) =>
                state.permissions.includes(permission)
            ),

        hasAllPermissions: (state) => (permissions: string[]) =>
            permissions.every((permission) =>
                state.permissions.includes(permission)
            ),
    },

    actions: {
        setPermissions(permissions: string[]) {
            this.permissions = permissions;
            this.loaded = true;
        },

        reset() {
            this.permissions = [];
            this.loaded = false;
            this.loading = false;
        },
    },
});
 */


import { defineStore } from "pinia";
import api from "@/api/client";
import type { ApiResponse } from "@/types/types";

interface PermissionState {
    permissions: string[];
    loaded: boolean;
    loading: boolean;
}

export const usePermissionStore = defineStore("permission", {
    state: (): PermissionState => ({
        permissions: [],
        loaded: false,
        loading: false,
    }),

    getters: {
        hasPermission: (state) => (permission: string) =>
            state.permissions.includes(permission),

        hasAnyPermission: (state) => (permissions: string[]) =>
            permissions.some((permission) =>
                state.permissions.includes(permission)
            ),

        hasAllPermissions: (state) => (permissions: string[]) =>
            permissions.every((permission) =>
                state.permissions.includes(permission)
            ),
    },

    actions: {
        async load(): Promise<void> {
            if (this.loaded) return;
            if (this.loading) return;

            this.loading = true;

            try {
                // Sesuaikan URL ini dengan endpoint permission RBAC CI4.
                const response = await api.get<ApiResponse<string[]>>(
                    "/rbac/permissions"
                );

                this.permissions = response.data.data ?? [];
                this.loaded = true;
            } finally {
                this.loading = false;
            }
        },

        setPermissions(permissions: string[]): void {
            this.permissions = permissions;
            this.loaded = true;
        },

        reset(): void {
            this.permissions = [];
            this.loaded = false;
            this.loading = false;
        },
    },
});
