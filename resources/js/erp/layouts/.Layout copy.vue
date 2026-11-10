<script setup>
import { RouterView } from "vue-router";

import Navbar from "./components/Navbar.vue";
import Sidebar from "./components/Sidebar.vue";
import Breadcrumb from "./components/Breadcrumb.vue";
</script>

<template>
  <div id="main-wrapper">
    <!-- Modernize header -->

    <Sidebar />

    <!-- Modernize horizontal navigation -->

    <!-- Main page area -->
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
                    {{ $route.meta.title || "Dashboard" }}
                  </h4>
                </div>

                <div class="col-4 ms-auto d-flex justify-content-end">
                  <Breadcrumb />
                </div>
              </div>
            </div>
          </div>

          <!-- Current Vue Router page -->
          <RouterView />
        </div>
      </div>
      <!-- <button class="btn btn-primary p-3 rounded-circle d-flex align-items-center justify-content-center customizer-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasExample" aria-controls="offcanvasExample">
        <i class="icon ti ti-settings fs-7"></i>
      </button> -->
    </div>

    <div class="dark-transparent sidebartoggler"></div>
    <!-- Modernize overlay -->
  </div>
</template>
