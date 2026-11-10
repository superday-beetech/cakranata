<script setup lang="ts">
import { RouterView, useRoute } from "vue-router";

import Sidebar from "@components/backend/Sidebar.vue";
import Navbar from "@components/backend/Navbar.vue";
import NotificationHost from "@/components/ui/NotificationHost.vue";
import OffcanvasHost from "@/components/ui/OffcanvasHost.vue";
import ModalHost from "@/components/ui/ModalHost.vue";
const route = useRoute();
</script>

<template>
  <div id="main-wrapper">
    <Sidebar />

    <div class="page-wrapper">
      <Navbar />

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
    <div class="dark-transparent sidebartoggler"></div>
  </div>
</template>
