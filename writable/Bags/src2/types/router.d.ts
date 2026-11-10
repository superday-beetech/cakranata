import "vue-router";

declare module "vue-router" {
    interface RouteMeta {
        layout?: "auth" | "backend";
        requiresAuth?: boolean;
        guestOnly?: boolean;
        permission?: string | string[];
    }
}
