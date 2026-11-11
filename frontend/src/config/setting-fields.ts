
export type SettingInputType =
    | "text"
    | "textarea"
    | "select"
    | "color"
    | "number"
    | "switch"
    | "checkbox"
    | "checkbox-group"
    | "radio"
    | "file"
    | "json";

export interface SettingFieldOption {
    label: string;
    value: string | number;
    icon?: string;
    color?: string;
}

export interface SettingField {
    input: SettingInputType;
    label?: string;
    placeholder?: string;
    options?: SettingFieldOption[];
    rows?: number;
    accept?: string;
    multiple?: boolean;
}

type ClassSettingFields = Record<string, SettingField>;

export const SETTING_FIELDS: Record<string, ClassSettingFields> = {
    Adm: {
        siteName: {
            input: "text",
            label: "Nama Aplikasi",
            placeholder: "Masukkan nama aplikasi",
        },

        siteDescription: {
            input: "textarea",
            label: "Deskripsi Aplikasi",
            rows: 3,
        },

        siteVersion: {
            input: "text",
            label: "Versi Aplikasi",
        },
    },
    App: {
        httpVerb: {
        input: "checkbox-group",
        label: "HTTP Methods",
        options: [
            { label: "GET", value: "GET" },
            { label: "POST", value: "POST" },
            { label: "PUT", value: "PUT" },
            { label: "PATCH", value: "PATCH" },
            { label: "DELETE", value: "DELETE" },
            { label: "OPTIONS", value: "OPTIONS" },
        ],
        },
        logo: {
            input: "file",
            label: "Logo Aplikasi",
            accept: "image/png,image/jpeg,image/svg+xml",
        },

        favicon: {
            input: "file",
            label: "Favicon",
            accept: "image/png,image/x-icon",
        },
    },
    Theme: {
        sidebar: {
            input: "select",
            label: "Tipe Sidebar",
            options: [
                { label: "Full", value: "full" },
                { label: "Mini", value: "mini-sidebar" },
            ],
        },

        layout: {
            input: "radio",
            label: "Layout",
            options: [
                { label: "Vertical", value: "vertical", icon: "ti ti-layout-sidebar-right" },
                { label: "Horizontal", value: "horizontal", icon: "ti ti-layout-navbar" },
            ],
        },
        color: {
            input: "radio",
            label: "Color",
            options: [
                { label: "Blue Theme", value: "Blue_Theme", color: "#5D87FF" },
                { label: "Aqua Theme", value: "Aqua_Theme", color: "#49BEFF" },
                { label: "Purple Theme", value: "Purple_Theme", color: "#763EBD" },
                { label: "Green Theme", value: "Green_Theme", color: "#13DEB9" },
                { label: "Cyan Theme", value: "Cyan_Theme", color: "#539BFF" },
                { label: "Orange Theme", value: "Orange_Theme", color: "#FA896B" },
            ],
        },
        logo: {
            input: "select",
            label: "Mode Tema",
            options: [
                { label: "Light", value: "light" },
                { label: "Dark", value: "dark" },
            ],
        },

        primaryColor: {
            input: "color",
            label: "Warna Utama",
        },

        darkMode: {
            input: "switch",
            label: "Dark Mode",
        },

        sidebarMini: {
            input: "checkbox",
            label: "Sidebar Mini",
        },
    },
};
