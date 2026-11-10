import "vue-router";

declare module "vue-router" {
  interface RouteMeta {
    layout?: "auth" | "backend" | "pos";
    requiresAuth?: boolean;
    guestOnly?: boolean;
    permission?: string | string[];
    title?: string;
  }
}
