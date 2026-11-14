<script setup lang="ts">
import { computed, ref, watch, onMounted } from "vue";

import { offcanvas } from "@/utils/offcanvas";
import UserDetail from "@/modules/dashboard/views/UserDetail.vue";
import { modal } from "@/utils/modal";
import UserForm from "@/modules/dashboard/views/UserForm.vue";

function showUser() {
  offcanvas.open({
    title: "Detail Pengguna",
    component: UserDetail,
    props: {
      userId: 10,
    },
    placement: "bottom",
    height: "70vh",
  });
}

function openUserForm() {
  modal.open({
    title: "Tambah Pengguna",
    component: UserForm,
    size: "default",
    backdrop: "static",
  });
}

// const siteName = computed(() => setting("App.siteName"));
const siteName = "Cakra Nata";
</script>
<template>
  <h1 class="mb-4">Selamat datang di {{ siteName }}</h1>
  <button class="btn btn-primary" @click="showUser">Lihat Pengguna</button>
  <button class="btn btn-primary" @click="openUserForm">Tambah Pengguna</button>
</template>
