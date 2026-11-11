
import { defineStore } from "pinia";
import { ref } from "vue";

import { APP_SETTINGS } from "@/config/settings";
import type { AppSettingKey } from "@/config/settings";
import type { SettingsMap } from "@/types/settings";

import { settingsService } from "@/utils/settings";

export const useSettingsStore = defineStore("settings", () => {
    const settings = ref<SettingsMap>({});

    const loading = ref(false);
    const loaded = ref(false);
    const error = ref<string | null>(null);

    let pendingRequest: Promise<void> | null = null;

    /**
     * Initialize settings.
     */
    async function initialize(force = false): Promise<void> {
        if (pendingRequest) {
            return pendingRequest;
        }

        if (loaded.value && !force) {
            return;
        }

        loading.value = true;
        error.value = null;

        pendingRequest = (async () => {
            try {
                const result = await settingsService.getMany(
                    APP_SETTINGS,
                );

                settings.value = result;
                loaded.value = true;
            } catch (err) {
                error.value = err instanceof Error
                    ? err.message
                    : "Gagal memuat konfigurasi.";

                throw err;
            } finally {
                loading.value = false;
                pendingRequest = null;
            }
        })();

        return pendingRequest;
    }

    /**
     * Get setting.
     */
    function getSetting<T = unknown>(
        key: AppSettingKey,
        fallback?: T,
    ): T | undefined {
        const value = settings.value[key];

        if (value === undefined || value === null) {
            return fallback;
        }

        return value as T;
    }

    /**
     * Update local setting.
     */
    function setSetting(
        key: AppSettingKey,
        value: unknown,
    ): void {
        settings.value[key] = value;
    }

    /**
     * Refresh settings.
     */
    async function refresh(): Promise<void> {
        await initialize(true);
    }

    /**
     * Reset settings.
     */
    function reset(): void {
        settings.value = {};
        loaded.value = false;
        error.value = null;
    }

    return {
        settings,
        loading,
        loaded,
        error,

        initialize,
        getSetting,
        setSetting,
        refresh,
        reset,
    };
});
