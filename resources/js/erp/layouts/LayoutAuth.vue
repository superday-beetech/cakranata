<template>
  <main class="auth-layout">
    <RouterView />
  </main>
</template>

<script setup>
import { RouterView } from "vue-router";
</script>

<style scoped>
.auth-layout {
  min-height: 100vh;
  width: 100%;
}
</style>
