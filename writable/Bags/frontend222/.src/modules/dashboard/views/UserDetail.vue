<script setup lang="ts">
defineProps<{
  userId: number;
}>();
</script>

<template>
  <div>
    <h6>Detail Pengguna</h6>
    <p>User ID: {{ userId }}</p>
  </div>
</template>
