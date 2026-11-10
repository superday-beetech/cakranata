export interface MenuItem {
    id: number;
    title: string;
    slug?: string | null;
    url: string | null;
    icon: string | null;
    parent_id: number;
    position: string;
    section: string;
    sequence: number;
    is_divider: number | boolean;
    is_active: number | boolean;
    children?: MenuItem[];
}

export interface MenuCollection {
    sidebar: MenuItem[];
    apps: MenuItem[];
    quick_links: MenuItem[];
    topbar: MenuItem[];
}

export interface MenuState {
    menus: MenuCollection;
    loading: boolean;
    loaded: boolean;
    error: string | null;
}
