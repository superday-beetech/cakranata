<script setup>
import { useRoute } from "vue-router";

const route = useRoute();
</script>

<template>
  <section class="placeholder-page">
    <div class="placeholder-icon">▦</div>
    <h1>{{ route.meta.title || "Halaman ERP" }}</h1>
    <p>Fondasi halaman ini sudah terdaftar. Fitur akan diimplementasikan pada tahap modul terkait.</p>
  </section>
</template>

<style scoped>
.placeholder-page {
  display: grid;
  min-height: 360px;
  place-content: center;
  justify-items: center;
  padding: 32px;
  text-align: center;
}

.placeholder-icon {
  display: grid;
  width: 64px;
  height: 64px;
  place-items: center;
  border-radius: 18px;
  background: #e9efff;
  color: #4169e1;
  font-size: 30px;
}

h1 {
  margin: 22px 0 8px;
  color: #172033;
  font-size: 24px;
}

p {
  max-width: 440px;
  color: #8994a7;
  font-size: 13px;
  line-height: 1.7;
}
</style>
