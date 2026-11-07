<template>
  <main class="p-5 text-center">
    <h1>403</h1>
    <h2>Akses Ditolak</h2>
    <p>Anda tidak memiliki permission untuk mengakses halaman ini.</p>

    <RouterLink to="/dashboard"> Kembali ke Dashboard </RouterLink>
  </main>
</template>

<script setup lang="ts">
import { RouterLink } from "vue-router";
</script>
