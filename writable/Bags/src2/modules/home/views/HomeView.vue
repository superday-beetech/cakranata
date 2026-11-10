<script setup lang="ts">
import { computed } from "vue";
import { useAuthStore } from "@/stores/auth";

const auth = useAuthStore();

const isLoggedIn = computed(() => auth.isAuthenticated);
</script>

<template>
  <main class="home-page">
    <header class="home-header">
      <h2>ACN ERP</h2>

      <nav>
        <RouterLink v-if="!isLoggedIn" to="/login"> Login </RouterLink>

        <RouterLink v-else to="/dashboard"> Dashboard </RouterLink>
      </nav>
    </header>

    <section class="home-content">
      <h1>Integrated Commodity Ecosystem</h1>

      <p>Sistem pengelolaan usaha terintegrasi untuk mendukung operasional ACN.</p>

      <RouterLink v-if="!isLoggedIn" to="/login" class="btn-login"> Login ke Sistem </RouterLink>

      <RouterLink v-else to="/dashboard" class="btn-login"> Masuk Dashboard </RouterLink>
    </section>
  </main>
</template>
