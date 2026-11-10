
import { useSettingsStore } from "@stores/settings";

import type { AppSettingKey } from "@/config/settings";

/**
 * Global application settings accessor.
 *
 * Usage:
 * setting("App.siteName")
 */
export function setting<T = unknown>(
    key: AppSettingKey,
    fallback?: T,
): T | undefined {
    const store = useSettingsStore();

    return store.getSetting<T>(key, fallback);
}
