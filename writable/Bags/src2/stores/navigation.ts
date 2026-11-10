import { defineStore } from "pinia";
import { ref, computed } from "vue";
import axios from "axios";

import api from "@/api/client";
import type { ApiResponse, LegacyApiResponse } from "@/types/types";

import type {
    NavigationMenu,
    NavigationGroups,
    MenuPosition,
    MenuSection,
    MenuFormPayload,
    MenuHierarchy,
    RouteRegistryPreview,
    RouteRegistrySaveItem,
} from "@/types/navigation";

function getErrorMessage(error: unknown): string {
    if (axios.isAxiosError(error)) {
        const response = error.response?.data as LegacyApiResponse | undefined;
        return response?.message || error.message || "Terjadi kesalahan pada server.";
    }

    return error instanceof Error
        ? error.message
        : "Terjadi kesalahan yang tidak diketahui.";
}

export const useNavigationStore = defineStore("navigation", () => {
    const menus = ref<NavigationMenu[]>([]);
    const roles = ref<string[]>([]);

    const title = ref("Manajemen Navigasi");
    const position = ref<MenuPosition>("sidebar");
    const section = ref<MenuSection>("backend");

    const loading = ref(false);
    const saving = ref(false);
    const error = ref<string | null>(null);

    const menuTree = computed(() => buildMenuTree(menus.value));

    function buildMenuTree(
        items: NavigationMenu[],
        parentId: string = "0",
    ): NavigationMenu[] {
        return items
            .filter((item) => String(item.parent_id || "0") === parentId)
            .sort((a, b) => Number(a.sequence) - Number(b.sequence))
            .map((item) => ({
                ...item,
                children: buildMenuTree(items, item.id),
            }));
    }

    function flattenMenuTree(items: NavigationMenu[]): NavigationMenu[] {
        const result: NavigationMenu[] = [];

        function walk(nodes: NavigationMenu[]) {
            for (const node of nodes) {
                result.push(node);

                if (node.children?.length) {
                    walk(node.children);
                }
            }
        }

        walk(items);
        return result;
    }

    async function loadNavigation(
        selectedPosition: MenuPosition = position.value,
        selectedSection: MenuSection = section.value,
    ) {
        loading.value = true;
        error.value = null;

        try {
            const response = await api.get<ApiResponse<{ data: NavigationGroups }>>(
                "/backend/navigation-api",
                {
                    params: {
                        position: selectedPosition,
                        section: selectedSection,
                    },
                },
            );

            // ApiController successResponse membungkus payload pada data.
            const payload = response.data.data?.data;

            if (!payload) {
                throw new Error("Format respons navigasi tidak valid.");
            }

            position.value = payload.position;
            section.value = payload.section;
            title.value = payload.title;
            menus.value = payload.menus ?? [];
            roles.value = payload.roles ?? [];

            return payload;
        } catch (err) {
            error.value = getErrorMessage(err);
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function saveMenu(payload: MenuFormPayload) {
        saving.value = true;
        error.value = null;

        try {
            const formData = new FormData();

            formData.append("id", payload.id ?? "");
            formData.append("title", payload.title);
            formData.append("url", payload.url);
            formData.append("icon", payload.icon);
            formData.append("parent_id", payload.parent_id);
            formData.append("position", payload.position);
            formData.append("section", payload.section);
            formData.append("is_active", payload.is_active ? "1" : "");

            payload.groups.forEach((group) => {
                formData.append("groups[]", group);
            });

            const response = await api.post<LegacyApiResponse>(
                "/backend/navigation-api/create",
                formData,
                {
                    headers: {
                        "Content-Type": "multipart/form-data",
                    },
                },
            );

            if (response.data.status !== "success") {
                throw new Error(response.data.message || "Gagal menyimpan menu.");
            }

            await loadNavigation(payload.position, payload.section);

            return response.data;
        } catch (err) {
            error.value = getErrorMessage(err);
            throw err;
        } finally {
            saving.value = false;
        }
    }

    async function deleteMenu(id: string) {
        saving.value = true;
        error.value = null;

        try {
            const response = await api.delete<LegacyApiResponse>(
                `/backend/navigation-api/destroy/${encodeURIComponent(id)}`,
            );

            if (response.data.status !== "success") {
                throw new Error(response.data.message || "Gagal menghapus menu.");
            }

            await loadNavigation();

            return response.data;
        } catch (err) {
            error.value = getErrorMessage(err);
            throw err;
        } finally {
            saving.value = false;
        }
    }

    async function updateOrder(tree: MenuHierarchy[]) {
        error.value = null;

        const response = await api.post<LegacyApiResponse>(
            "/backend/navigation-api/update-order",
            tree,
            {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
            },
        );

        if (response.data.status !== "success") {
            throw new Error(response.data.message || "Gagal memperbarui urutan menu.");
        }

        return response.data;
    }

    async function previewRegistry(): Promise<RouteRegistryPreview> {
        const response = await api.post<LegacyApiResponse<RouteRegistryPreview>>(
            "/backend/navigation-api/sync-menu-preview",
            {
                section: section.value,
            },
        );

        if (response.data.status !== "success" || !response.data.data) {
            throw new Error(response.data.message || "Gagal memuat Route Registry.");
        }

        return response.data.data;
    }

    async function saveRegistry(items: RouteRegistrySaveItem[]) {
        const response = await api.post<LegacyApiResponse>(
            "/backend/navigation-api/sync-menu-save",
            {
                section: section.value,
                items,
            },
        );

        if (response.data.status !== "success") {
            throw new Error(response.data.message || "Gagal menyinkronkan menu.");
        }

        await loadNavigation();

        return response.data;
    }

    return {
        menus,
        roles,
        title,
        position,
        section,
        loading,
        saving,
        error,

        menuTree,

        buildMenuTree,
        flattenMenuTree,

        loadNavigation,
        saveMenu,
        deleteMenu,
        updateOrder,
        previewRegistry,
        saveRegistry,
    };
});
