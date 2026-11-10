<script setup>
import { RouterView } from "vue-router";
import { defineAsyncComponent } from "vue";
import { modalState } from "./layouts/components/global/modalServices.js";

const GlobalModal = defineAsyncComponent(() => import("./layouts/components/global/ModalGlobal.vue"));
</script>

<template>
  <RouterView />
  <GlobalModal v-if="modalState.visible" />
</template>
