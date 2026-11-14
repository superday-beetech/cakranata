<script setup lang="ts">
import type { ValidationIssue } from "../utils/validateDesign";

const props = defineProps<{
  errors: ValidationIssue[];
  warnings: ValidationIssue[];
}>();

const emit = defineEmits<{
  "goto-element": [string | undefined];
}>();

function onClickIssue(issue: ValidationIssue) {
  if (issue.elementId) emit("goto-element", issue.elementId);
}
</script>

<template>
  <div v-if="errors.length || warnings.length" class="validation-banner mb-2">
    <div v-if="errors.length" class="alert alert-danger mb-2 py-2">
      <div class="fw-semibold mb-1">
        <i class="ti ti-alert-octagon me-1"></i>
        {{ errors.length }} kesalahan — perbaiki dulu sebelum menyimpan
      </div>
      <ul class="mb-0 small ps-3">
        <li v-for="(e, i) in errors" :key="`e-${i}`">
          <a v-if="e.elementId" href="#" class="text-decoration-underline" @click.prevent="onClickIssue(e)">{{ e.message }}</a>
          <span v-else>{{ e.message }}</span>
        </li>
      </ul>
    </div>
    <div v-if="warnings.length" class="alert alert-warning mb-0 py-2">
      <div class="fw-semibold mb-1">
        <i class="ti ti-alert-triangle me-1"></i>
        {{ warnings.length }} peringatan — boleh lanjut simpan
      </div>
      <ul class="mb-0 small ps-3">
        <li v-for="(w, i) in warnings" :key="`w-${i}`">
          <a v-if="w.elementId" href="#" class="text-decoration-underline" @click.prevent="onClickIssue(w)">{{ w.message }}</a>
          <span v-else>{{ w.message }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.validation-banner a {
  color: inherit;
}
</style>
