
import api from "@/api/client";

import type { AppSettingKey } from "@/config/settings";
import type {
    SettingsMap,
    SettingsResponse,
} from "@/types/settings";

export const settingsService = {
    async getMany(
        keys: readonly AppSettingKey[],
    ): Promise<SettingsMap> {
        if (keys.length === 0) {
            return {};
        }

        const response = await api.post<SettingsResponse>(
            "settings",
            { keys },
        );

        if (!response.data.status) {
            throw new Error("Gagal mengambil konfigurasi.");
        }

        return response.data.data ?? {};
    },
};
