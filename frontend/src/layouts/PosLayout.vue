<script setup lang="ts">
import { RouterView } from "vue-router";

import NotificationHost from "@/components/ui/NotificationHost.vue";
import OffcanvasHost from "@/components/ui/OffcanvasHost.vue";
import ModalHost from "@/components/ui/ModalHost.vue";
</script>

<template>
  <div class="pos-layout">
    <RouterView />
  </div>

  <NotificationHost />
  <OffcanvasHost />
  <ModalHost />
</template>
