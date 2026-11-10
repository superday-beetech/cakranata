<template>
  <section class="not-found">
    <strong>404</strong>
    <h1>Halaman tidak ditemukan</h1>
    <p>Alamat halaman ERP yang Anda buka tidak tersedia.</p>
    <RouterLink to="/">Kembali ke Dashboard</RouterLink>
  </section>
</template>

<script setup>
import { RouterLink } from "vue-router";
</script>

<style scoped>
.not-found {
  display: grid;
  min-height: 60vh;
  place-content: center;
  justify-items: center;
  text-align: center;
}

strong {
  color: #4169e1;
  font-size: 64px;
}

h1 {
  margin-bottom: 0;
  color: #172033;
}

p {
  color: #8994a7;
}

a {
  margin-top: 12px;
  color: #315bd5;
  text-decoration: none;
}
</style>
