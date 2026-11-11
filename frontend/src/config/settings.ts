
export const APP_SETTINGS = [
    // Application
    'App.siteName',
    'App.siteVersion',

    // Site
    'Site.label',
    'Site.description',

    // Theme
    'Theme.sidebarType',
    'Theme.layout',
    'Theme.theme',

    // Navigation
    'Config.menu',
] as const;

export type AppSettingKey = (typeof APP_SETTINGS)[number];
