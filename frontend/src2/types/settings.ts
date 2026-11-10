
import type { AppSettingKey } from '@/config/settings';

export interface SettingsResponse {
    status: boolean;
    data: Partial<Record<AppSettingKey, unknown>>;
    meta?: {
        count: number;
    };
}

export type SettingsMap = Partial<Record<AppSettingKey, unknown>>;
