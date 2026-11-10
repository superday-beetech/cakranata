<script setup lang="ts">
import { RouterView, useRoute } from "vue-router";
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";

import Sidebar from "@components/backend/Sidebar.vue";
import SidebarHorizontal from "@components/backend/SidebarHorizontal.vue";
import Navbar from "@components/backend/Navbar.vue";

import NotificationHost from "@/components/ui/NotificationHost.vue";
import OffcanvasHost from "@/components/ui/OffcanvasHost.vue";
import ModalHost from "@/components/ui/ModalHost.vue";

const route = useRoute();

type SidebarType = "full" | "mini-sidebar";

interface SidebarPreference {
  type: SidebarType;
  showSidebar: boolean;
}

const SIDEBAR_KEY = "acn.sidebar";
const SIDEBAR_BREAKPOINT = 1280;

// Load saved preference
function getSavedSidebar(): SidebarPreference {
  const defaultValue: SidebarPreference = {
    type: "full",
    showSidebar: false,
  };

  try {
    const saved = localStorage.getItem(SIDEBAR_KEY);

    if (!saved) return defaultValue;

    const parsed = JSON.parse(saved);

    return {
      type: parsed.type === "mini-sidebar" ? "mini-sidebar" : "full",
      showSidebar: typeof parsed.showSidebar === "boolean" ? parsed.showSidebar : false,
    };
  } catch {
    return defaultValue;
  }
}

const savedSidebar = getSavedSidebar();

// Sidebar state
const sidebarType = ref<SidebarType>(savedSidebar.type);
const showSidebar = ref(savedSidebar.showSidebar);

// Responsive state
const isMobile = ref(typeof window !== "undefined" && window.innerWidth < SIDEBAR_BREAKPOINT);

// Effective sidebar type
const effectiveSidebarType = computed<SidebarType>(() => {
  return isMobile.value ? "mini-sidebar" : sidebarType.value;
});

// Layout
const isHorizontal = computed(() => document.documentElement.getAttribute("data-layout") === "horizontal");

function saveSidebarPreference(): void {
  // Preferensi hanya disimpan pada desktop besar.
  if (window.innerWidth < SIDEBAR_BREAKPOINT) {
    return;
  }

  try {
    localStorage.setItem(
      SIDEBAR_KEY,
      JSON.stringify({
        type: sidebarType.value,
        showSidebar: false,
      }),
    );
  } catch {
    // localStorage tidak tersedia.
  }
}

watch(sidebarType, saveSidebarPreference);

function toggleSidebar(): void {
  if (window.innerWidth >= SIDEBAR_BREAKPOINT) {
    // Desktop: Full / Mini
    sidebarType.value = sidebarType.value === "full" ? "mini-sidebar" : "full";

    return;
  }

  // Mobile/tablet: Show / Hide sementara
  showSidebar.value = !showSidebar.value;
}

// Close mobile sidebar
function closeMobileSidebar(): void {
  if (isMobile.value) {
    showSidebar.value = false;
  }
}

// Responsive handler
function handleResize(): void {
  const mobile = window.innerWidth < SIDEBAR_BREAKPOINT;

  if (isMobile.value !== mobile) {
    isMobile.value = mobile;
  }

  // Saat masuk desktop, tutup overlay mobile.
  if (!mobile) {
    showSidebar.value = false;
  }
}
function syncSidebarBody(): void {
  document.body.setAttribute("data-sidebartype", effectiveSidebarType.value);
}

watch(effectiveSidebarType, syncSidebarBody, { immediate: true });
onMounted(() => {
  window.addEventListener("resize", handleResize);
  syncSidebarBody();
});

onBeforeUnmount(() => {
  window.removeEventListener("resize", handleResize);
  document.body.removeAttribute("data-sidebartype");
});
</script>

<template>
  <div
    id="main-wrapper"
    :class="{
      'mini-sidebar': effectiveSidebarType === 'mini-sidebar',
      'show-sidebar': isMobile && showSidebar,
    }"
    :data-sidebartype="effectiveSidebarType"
  >
    <Sidebar />

    <div class="page-wrapper">
      <Navbar @toggle-sidebar="toggleSidebar" />

      <SidebarHorizontal v-if="isHorizontal" />

      <div class="body-wrapper">
        <div class="container-fluid">
          <!-- Page title and breadcrumb -->
          <div class="card bg-info-subtle shadow-none position-relative overflow-hidden mb-4">
            <div class="card-body px-4 py-3">
              <div class="row justify-content-between align-items-center">
                <div class="col-8">
                  <h4 class="fw-semibold mb-0">
                    {{ route.meta.title || "Dashboard" }}
                  </h4>
                </div>

                <div class="col-4 ms-auto d-flex justify-content-end">
                  <!-- <Breadcrumb /> -->
                </div>
              </div>
            </div>
          </div>

          <!-- Current Vue Router page -->
          <RouterView />
        </div>
      </div>
    </div>

    <NotificationHost />
    <OffcanvasHost />
    <ModalHost />

    <!-- Mobile sidebar overlay -->
    <div v-if="isMobile" class="dark-transparent" @click="closeMobileSidebar"></div>
  </div>
</template>
