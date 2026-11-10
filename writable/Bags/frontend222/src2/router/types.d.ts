import "vue-router";

declare module "vue-router" {
    interface RouteMeta {
        layout?: "auth" | "backend";
        requiresAuth?: boolean;
    }
}
