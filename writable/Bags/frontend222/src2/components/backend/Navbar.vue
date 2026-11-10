<script setup lang="ts">
import { computed, ref, onMounted } from "vue";
import { useRoute, useRouter, RouterLink } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { useMenuStore } from "@/stores/menu";
import { useTheme } from "@/composables/useTheme";

import type { MenuItem } from "@/types/menu";
import { modal } from "@/utils/modal";
import SearchModal from "@/components/backend/ModalSearch.vue";
import OrganizationSwitcher from "@/modules/organization/master/components/OrganizationSwitcher.vue";
const route = useRoute();
const router = useRouter();
const { theme, setTheme } = useTheme();

const menuStore = useMenuStore();

const appsMenus = computed(() => menuStore.menus.apps);
const quickLinks = computed(() => menuStore.menus.quick_links);
const navbarApps = computed(() => menuStore.menus.topbar);

const loadingMenus = computed(() => menuStore.loading);

const profileName = ref("Pengguna");
const profileRole = ref("User");

const authStore = useAuthStore();

function menuKey(menu: MenuItem): number | string {
  return menu.id ?? menu.title ?? menu.url ?? "";
}

function resolveUrl(url?: string | null): string {
  if (!url || url === "#") return "/";

  const value = String(url).trim();

  if (/^(https?:)?\/\//i.test(value)) {
    return value;
  }

  return value.startsWith("/") ? value : `/${value}`;
}

function isExternal(url?: string | null): boolean {
  return /^(https?:)?\/\//i.test(String(url || "").trim());
}

function isActiveUrl(url?: string | null): boolean {
  if (!url || url === "#" || isExternal(url)) return false;

  const path = resolveUrl(url).replace(/\/+$/, "");

  return route.path.replace(/\/+$/, "") === path;
}

onMounted(async () => {
  if (!menuStore.loaded) {
    try {
      await menuStore.load();
    } catch (error) {
      console.error("Gagal memuat navigasi:", error);
    }
  }
});

function openSearchModal() {
  modal.open({
    title: "Pencarian",
    component: SearchModal,
    size: "lg",
    centered: true,
    scrollable: true,
    backdrop: true,
    keyboard: true,
  });
}

async function handleLogout() {
  const success = await authStore.logout();

  if (success) {
    menuStore.clear();
    router.replace("/login");
  }
}

const emit = defineEmits<{
  "toggle-sidebar": [];
}>();

function toggleSidebar(): void {
  emit("toggle-sidebar");
}
</script>
<template>
  <header class="topbar">
    <!-- VERTICAL HEADER -->
    <div class="with-vertical">
      <nav class="navbar navbar-expand-lg p-0">
        <!-- Left navigation -->
        <ul class="navbar-nav">
          <li class="nav-item nav-icon-hover-bg rounded-circle ms-n2">
            <a class="nav-link" id="headerCollapse" href="javascript:void(0)" @click.prevent.stop="toggleSidebar"><i class="ti ti-menu-2"></i> </a>
          </li>

          <li class="nav-item nav-icon-hover-bg rounded-circle d-none d-lg-flex">
            <a class="nav-link" href="#" @click.prevent="openSearchModal" aria-label="Buka pencarian">
              <i class="ti ti-search"></i>
            </a>
          </li>
        </ul>

        <!-- Desktop Apps + Quick Links -->
        <ul class="navbar-nav quick-links d-none d-lg-flex align-items-center">
          <li v-if="appsMenus.length > 0" class="nav-item nav-icon-hover-bg rounded w-auto dropdown d-none d-lg-block mx-0">
            <div class="hover-dd">
              <a class="nav-link" href="javascript:void(0)">
                Apps
                <span class="mt-1">
                  <i class="ti ti-chevron-down fs-3"></i>
                </span>
              </a>

              <div class="dropdown-menu dropdown-menu-nav dropdown-menu-animate-up py-0">
                <div class="row g-0">
                  <!-- Apps -->
                  <div class="col-8">
                    <div class="row g-0 p-4">
                      <div v-for="app in appsMenus" :key="menuKey(app)" class="col-6">
                        <RouterLink :to="resolveUrl(app.url)" class="d-flex align-items-center p-3 rounded-2 text-decoration-none">
                          <div class="text-bg-light rounded-1 me-3 p-3 d-flex align-items-center justify-content-center">
                            <i :class="app.icon || 'ti ti-apps'"></i>
                          </div>

                          <div>
                            <h6 class="mb-1 fw-semibold bg-hover-primary">
                              {{ app.title }}
                            </h6>
                            <span class="fs-2 d-block text-muted">
                              {{ app.slug || "" }}
                            </span>
                          </div>
                        </RouterLink>
                      </div>

                      <div v-if="!loadingMenus && appsMenus.length === 0" class="col-12 p-3 text-muted">Belum ada menu aplikasi.</div>
                    </div>
                  </div>

                  <!-- Quick Links -->
                  <div class="col-4">
                    <div class="position-relative p-7 border-start h-100">
                      <h5 class="fs-5 mb-9 fw-semibold">Quick Links</h5>

                      <ul class="list-unstyled mb-0">
                        <li v-for="link in quickLinks" :key="menuKey(link)" class="mb-3">
                          <RouterLink :to="resolveUrl(link.url)" class="fw-semibold bg-hover-primary text-decoration-none">
                            {{ link.title }}
                          </RouterLink>
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </li>

          <!-- Navbar shortcuts -->
          <li v-for="item in navbarApps" :key="menuKey(item)" class="nav-item dropdown-hover d-none d-lg-block">
            <RouterLink :to="resolveUrl(item.url)" class="nav-link">
              {{ item.title }}
            </RouterLink>
          </li>
        </ul>

        <!-- Mobile logo -->
        <div class="d-block d-lg-none py-4">
          <RouterLink to="/" class="text-nowrap logo-img"></RouterLink>
        </div>

        <!-- Mobile navbar toggler -->
        <a
          class="navbar-toggler nav-icon-hover-bg rounded-circle p-0 mx-0 border-0"
          href="javascript:void(0)"
          data-bs-toggle="collapse"
          data-bs-target="#navbarNav"
          aria-controls="navbarNav"
          aria-expanded="false"
          aria-label="Toggle navigation"
        >
          <i class="ti ti-dots fs-7"></i>
        </a>

        <!-- Right navigation -->
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
          <div class="d-flex align-items-center justify-content-between">
            <!-- Mobile navigation drawer -->
            <a
              href="javascript:void(0)"
              class="nav-link nav-icon-hover-bg rounded-circle mx-0 ms-n1 d-flex d-lg-none align-items-center justify-content-center"
              data-bs-toggle="offcanvas"
              data-bs-target="#mobilenavbar"
              aria-controls="offcanvasWithBothOptions"
            >
              <i class="ti ti-align-justified fs-7"></i>
            </a>

            <ul class="navbar-nav flex-row ms-auto align-items-center justify-content-center">
              <!-- Theme -->
              <li class="nav-item nav-icon-hover-bg rounded-circle">
                <a class="nav-link moon dark-layout" href="javascript:void(0)" @click.prevent="setTheme('dark')">
                  <i class="ti ti-moon"></i>
                </a>

                <a class="nav-link sun light-layout" href="javascript:void(0)" style="display: none" @click.prevent="setTheme('light')">
                  <i class="ti ti-sun"></i>
                </a>
              </li>
              <!-- Shopping cart -->
              <li class="nav-item nav-icon-hover-bg rounded-circle">
                <a class="nav-link position-relative" href="javascript:void(0)" data-bs-toggle="offcanvas" data-bs-target="#offcanvasRight" aria-controls="offcanvasRight">
                  <i class="ti ti-basket"></i>
                </a>
              </li>

              <!-- Notifications -->
              <li class="nav-item nav-icon-hover-bg rounded-circle dropdown">
                <a class="nav-link position-relative" href="javascript:void(0)" id="drop2" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="ti ti-bell-ringing"></i>
                  <div class="notification bg-primary rounded-circle"></div>
                </a>

                <div class="dropdown-menu content-dd dropdown-menu-end dropdown-menu-animate-up" aria-labelledby="drop2">
                  <div class="d-flex align-items-center justify-content-between py-3 px-7">
                    <h5 class="mb-0 fs-5 fw-semibold">Notifications</h5>
                    <span class="badge text-bg-primary rounded-4 px-3 py-1 lh-sm"> 0 new </span>
                  </div>

                  <div class="message-body">
                    <div class="py-6 px-7 text-muted">Belum ada notifikasi.</div>
                  </div>

                  <div class="py-6 px-7 mb-1">
                    <button type="button" class="btn btn-outline-primary w-100">See All Notifications</button>
                  </div>
                </div>
              </li>

              <li class="nav-item d-flex align-items-center">
                <OrganizationSwitcher />
              </li>

              <!-- User profile -->
              <li class="nav-item dropdown">
                <a class="nav-link pe-0" href="javascript:void(0)" id="drop1" data-bs-toggle="dropdown" aria-expanded="false">
                  <div class="d-flex align-items-center">
                    <div class="user-profile-img rounded-circle bg-primary-subtle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px">
                      <i class="ti ti-user fs-5"></i>
                    </div>
                  </div>
                </a>

                <div class="dropdown-menu content-dd dropdown-menu-end dropdown-menu-animate-up" aria-labelledby="drop1">
                  <div class="profile-dropdown position-relative">
                    <div class="py-3 px-7 pb-0">
                      <h5 class="mb-0 fs-5 fw-semibold">User Profile</h5>
                    </div>

                    <div class="d-flex align-items-center py-9 mx-7 border-bottom">
                      <div class="rounded-circle bg-primary-subtle d-flex align-items-center justify-content-center" style="width: 64px; height: 64px">
                        <i class="ti ti-user fs-7"></i>
                      </div>

                      <div class="ms-3">
                        <h5 class="mb-1 fs-3">{{ profileName }}</h5>
                        <span class="mb-1 d-block">{{ profileRole }}</span>
                      </div>
                    </div>

                    <div class="message-body">
                      <RouterLink to="/profile" class="py-8 px-7 mt-8 d-flex align-items-center text-decoration-none">
                        <span class="d-flex align-items-center justify-content-center text-bg-light rounded-1 p-6">
                          <i class="ti ti-user fs-5"></i>
                        </span>

                        <div class="w-100 ps-3">
                          <h6 class="mb-1 fs-3 fw-semibold lh-base">My Profile</h6>
                          <span class="fs-2 d-block text-body-secondary"> Account Settings </span>
                        </div>
                      </RouterLink>

                      <RouterLink to="/settings" class="py-8 px-7 d-flex align-items-center text-decoration-none">
                        <span class="d-flex align-items-center justify-content-center text-bg-light rounded-1 p-6">
                          <i class="ti ti-settings fs-5"></i>
                        </span>

                        <div class="w-100 ps-3">
                          <h6 class="mb-1 fs-3 fw-semibold lh-base">Account Settings</h6>
                          <span class="fs-2 d-block text-body-secondary"> Manage your account </span>
                        </div>
                      </RouterLink>
                    </div>

                    <div class="d-grid py-4 px-7 pt-8">
                      <button type="button" class="btn btn-outline-primary" @click="handleLogout" :disabled="authStore.loading">
                        <i class="ti ti-logout me-2"></i>
                        {{ authStore.loading ? "Logging out..." : "Log Out" }}
                      </button>
                    </div>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </nav>

      <!-- MOBILE APPS OFFCANVAS -->
      <div class="offcanvas offcanvas-end" data-bs-scroll="true" tabindex="-1" id="mobilenavbar" aria-labelledby="mobilenavbarLabel">
        <nav class="sidebar-nav scroll-sidebar">
          <div class="offcanvas-header justify-content-between">
            <h5 class="mb-0" id="mobilenavbarLabel">Navigation</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
          </div>

          <div class="offcanvas-body">
            <ul id="mobile-sidebarnav" class="list-unstyled">
              <!-- Apps group -->
              <li class="sidebar-item">
                <a class="sidebar-link has-arrow" href="javascript:void(0)" data-bs-toggle="collapse" data-bs-target="#mobileAppsCollapse" aria-expanded="false">
                  <span><i class="ti ti-apps"></i></span>
                  <span class="hide-menu">Apps</span>
                </a>

                <ul id="mobileAppsCollapse" class="collapse first-level my-3 list-unstyled">
                  <li v-for="app in appsMenus" :key="menuKey(app)" class="sidebar-item py-2">
                    <RouterLink :to="resolveUrl(app.url)" class="d-flex align-items-center text-decoration-none">
                      <div class="text-bg-light rounded-1 me-3 p-6 d-flex align-items-center justify-content-center">
                        <i :class="app.icon || 'ti ti-apps'"></i>
                      </div>

                      <div>
                        <h6 class="mb-1 bg-hover-primary">{{ app.title }}</h6>
                        <span class="fs-2 d-block text-muted">
                          {{ app.slug || "" }}
                        </span>
                      </div>
                    </RouterLink>
                  </li>

                  <!-- Quick Links -->
                  <li class="sidebar-item px-8 mt-7 mb-4">
                    <h5 class="fs-5 fw-semibold">Quick Links</h5>
                  </li>

                  <li v-for="link in quickLinks" :key="menuKey(link)" class="sidebar-item py-2 px-8">
                    <RouterLink :to="resolveUrl(link.url)" class="fw-semibold text-dark text-decoration-none">
                      {{ link.title }}
                    </RouterLink>
                  </li>
                </ul>
              </li>

              <!-- Mobile navbar shortcuts -->
              <li v-for="item in navbarApps" :key="menuKey(item)" class="sidebar-item">
                <RouterLink :to="resolveUrl(item.url)" class="sidebar-link">
                  <span>
                    <i :class="item.icon || 'ti ti-link'"></i>
                  </span>
                  <span class="hide-menu">{{ item.title }}</span>
                </RouterLink>
              </li>
            </ul>
          </div>
        </nav>
      </div>
    </div>

    <!-- HORIZONTAL HEADER -->
    <div class="app-header with-horizontal">
      <nav class="navbar navbar-expand-xl container-fluid p-0">
        <ul class="navbar-nav align-items-center">
          <li class="nav-item nav-icon-hover-bg rounded-circle d-flex d-xl-none ms-n2">
            <a class="nav-link" id="sidebarCollapse" href="javascript:void(0)" @click.prevent.stop="toggleSidebar"> <i class="ti ti-menu-2"></i> </a>
          </li>

          <li class="nav-item d-none d-xl-block">
            <RouterLink to="/" class="text-nowrap nav-link"></RouterLink>
          </li>

          <li class="nav-item nav-icon-hover-bg rounded-circle d-none d-xl-flex">
            <a class="nav-link" href="javascript:void(0)" @click.prevent="openSearchModal" aria-label="Buka pencarian">
              <i class="ti ti-search"></i>
            </a>
          </li>
        </ul>

        <!-- Apps + Quick Links -->
        <ul class="navbar-nav quick-links d-none d-xl-flex align-items-center">
          <li class="nav-item nav-icon-hover-bg rounded w-auto dropdown d-none d-lg-flex">
            <div class="hover-dd">
              <a class="nav-link" href="javascript:void(0)">
                Apps
                <span class="mt-1">
                  <i class="ti ti-chevron-down fs-3"></i>
                </span>
              </a>

              <div class="dropdown-menu dropdown-menu-nav dropdown-menu-animate-up py-0">
                <div class="row g-0">
                  <div class="col-8">
                    <div class="row g-0 p-4">
                      <div v-for="app in appsMenus" :key="menuKey(app)" class="col-6">
                        <RouterLink :to="resolveUrl(app.url)" class="d-flex align-items-center p-3 rounded-2 text-decoration-none">
                          <div class="text-bg-light rounded-1 me-3 p-3 d-flex align-items-center justify-content-center">
                            <i :class="app.icon || 'ti ti-apps'"></i>
                          </div>

                          <div>
                            <h6 class="mb-1 fw-semibold bg-hover-primary">
                              {{ app.title }}
                            </h6>
                            <span class="fs-2 d-block text-muted">
                              {{ app.slug || "" }}
                            </span>
                          </div>
                        </RouterLink>
                      </div>
                    </div>
                  </div>

                  <div class="col-4">
                    <div class="position-relative p-7 border-start h-100">
                      <h5 class="fs-5 mb-9 fw-semibold">Quick Links</h5>

                      <ul class="list-unstyled mb-0">
                        <li v-for="link in quickLinks" :key="menuKey(link)" class="mb-3">
                          <RouterLink :to="resolveUrl(link.url)" class="fw-semibold bg-hover-primary text-decoration-none">
                            {{ link.title }}
                          </RouterLink>
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </li>

          <li v-for="item in navbarApps" :key="menuKey(item)" class="nav-item dropdown-hover d-none d-lg-block">
            <RouterLink :to="resolveUrl(item.url)" class="nav-link">
              {{ item.title }}
            </RouterLink>
          </li>
        </ul>

        <div class="d-block d-xl-none">
          <RouterLink to="/" class="text-nowrap nav-link"></RouterLink>
        </div>

        <!-- Mobile toggler -->
        <a
          class="navbar-toggler nav-icon-hover-bg rounded-circle p-0 mx-0 border-0"
          href="javascript:void(0)"
          data-bs-toggle="collapse"
          data-bs-target="#navbarNavHorizontal"
          aria-controls="navbarNavHorizontal"
          aria-expanded="false"
          aria-label="Toggle navigation"
        >
          <span class="p-2"><i class="ti ti-dots fs-7"></i></span>
        </a>

        <!-- Right navigation -->
        <div class="collapse navbar-collapse justify-content-end" id="navbarNavHorizontal">
          <ul class="navbar-nav flex-row ms-auto align-items-center justify-content-center">
            <!-- Theme -->
            <li class="nav-item nav-icon-hover-bg rounded-circle">
              <a class="nav-link" href="javascript:void(0)" aria-label="Toggle theme">
                <i class="ti ti-moon"></i>
              </a>
            </li>
            <!-- Organization Switcher -->
            <li class="nav-item d-flex align-items-center px-2">
              <OrganizationSwitcher />
            </li>
            <!-- Notifications -->
            <li class="nav-item nav-icon-hover-bg rounded-circle dropdown">
              <a class="nav-link position-relative" href="javascript:void(0)" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="ti ti-bell-ringing"></i>
              </a>

              <div class="dropdown-menu content-dd dropdown-menu-end dropdown-menu-animate-up">
                <div class="py-4 px-7">
                  <h5 class="mb-0 fs-5 fw-semibold">Notifications</h5>
                </div>
                <div class="message-body px-7 pb-4 text-muted">Belum ada notifikasi.</div>
              </div>
            </li>

            <!-- User profile -->
            <li class="nav-item dropdown">
              <a class="nav-link pe-0" href="javascript:void(0)" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="user-profile-img rounded-circle bg-primary-subtle d-inline-flex align-items-center justify-content-center" style="width: 35px; height: 35px">
                  <i class="ti ti-user"></i>
                </span>
              </a>

              <div class="dropdown-menu content-dd dropdown-menu-end dropdown-menu-animate-up">
                <div class="py-3 px-7">
                  <h5 class="mb-0 fs-5 fw-semibold">{{ profileName }}</h5>
                  <span class="fs-2 text-muted">{{ profileRole }}</span>
                </div>

                <div class="message-body">
                  <RouterLink to="/profile" class="dropdown-item py-3 px-7"> My Profile </RouterLink>

                  <RouterLink to="/settings" class="dropdown-item py-3 px-7"> Account Settings </RouterLink>

                  <a href="/auth/logout" class="dropdown-item py-3 px-7"> Log Out </a>
                </div>
              </div>
            </li>
          </ul>
        </div>
      </nav>
    </div>
  </header>
</template>
