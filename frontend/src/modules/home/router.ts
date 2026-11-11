import type { RouteRecordRaw } from "vue-router";

const homeRoutes: RouteRecordRaw[] = [
    {
        path: "/",
        name: "Home",
        component: () => import("./views/HomeView.vue"),
        meta: {
            layout: "auth",
            requiresAuth: false,
        },
    },
];

export default homeRoutes;
