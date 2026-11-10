import type { RouteRecordRaw } from "vue-router";

const authRoutes: RouteRecordRaw[] = [
    {
        path: "/login",
        name: "Login",
        component: () => import("./views/LoginView.vue"),
        meta: {
            layout: "auth",
            requiresAuth: false,
            guestOnly: true,
        },
    },
];

export default authRoutes;
