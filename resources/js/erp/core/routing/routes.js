export const routes = [
  {
    path: "/",
    name: "app-layout",
    component: () => import("../../layouts/Layout.vue"),
    meta: { requiresAuth: true },
    children: [
      {
        path: "",
        name: "dashboard",
        component: () => import("../../pages/Dashboard.vue"),
        meta: { title: "Dashboard" },
      },
    ],
  },
  {
    path: "/login",
    component: () => import("../../layouts/LayoutAuth.vue"),
    children: [
      {
        path: "",
        name: "login",
        component: () => import("../../pages/Login.vue"),
        meta: { title: "Login", guestOnly: true },
      },
      {
        path: "/forgot-password",
        name: "forgot-password",
        component: () => import("../../pages/ForgotPassword.vue"),
        meta: { title: "Forgot Password", guestOnly: true },
      },
      {
        path: "/register",
        name: "register",
        component: () => import("../../pages/Register.vue"),
        meta: { title: "Register", guestOnly: true },
      },
    ],
  },
  {
    path: "/:pathMatch(.*)*",
    name: "not-found",
    component: () => import("../../pages/NotFound.vue"),
    meta: { title: "Halaman Tidak Ditemukan" },
  },
];
