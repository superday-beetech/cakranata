export type MenuPosition = "sidebar" | "topbar" | "down"| "quick_links"| "apps";
export type MenuSection = "backend" | "frontend";

export interface NavigationMenu {
    id: string;
    title: string;
    slug: string | null;
    url: string;
    icon: string;
    parent_id: string;
    position: MenuPosition;
    section: MenuSection;
    sequence: string;
    is_divider: string;
    is_active: string;
    created_at: string | null;
    updated_at: string | null;
    selected_groups: string[];
    children?: NavigationMenu[];
}

export interface NavigationGroups {
    title: string;
    position: MenuPosition;
    section: MenuSection;
    menus: NavigationMenu[];
    roles: string[];
}

export interface NavigationResponse {
    status: number;
    error: boolean;
    message: string;
    data: {
        data: NavigationGroups;
    };
    csrf_token?: string;
}

export interface MenuHierarchy {
    id: number;
    parent_id: number;
    sequence: number;
    children: MenuHierarchy[];
}

export interface MenuFormPayload {
    id?: string;
    title: string;
    url: string;
    icon: string;
    parent_id: string;
    position: MenuPosition;
    section: MenuSection;
    is_active: boolean;
    groups: string[];
}

export interface RouteRegistryCandidate {
    id: string | number;
    title: string;
    url: string;
    exists: boolean;
    [key: string]: unknown;
}

export interface RouteRegistryPreview {
    menus: NavigationMenu[];
    candidates: RouteRegistryCandidate[];
    groups: string[];
}

export interface RouteRegistrySaveItem {
    route_menu_id: number;
    position: MenuPosition;
    groups: string[];
}

export interface NavigationState {
    menus: NavigationMenu[];
    roles: string[];
    title: string;
    position: MenuPosition;
    section: MenuSection;
    loading: boolean;
    saving: boolean;
    error: string | null;
}
