import type { RouteRecordRaw } from "vue-router";

const dashboardRoutes: RouteRecordRaw[] = [
    {
        path: "/dashboard",
        name: "Dashboard",
        component: () => import("./views/DashboardView.vue"),
        meta: {
            layout: "backend",
            requiresAuth: true,
        },
    },
];

export default dashboardRoutes;
