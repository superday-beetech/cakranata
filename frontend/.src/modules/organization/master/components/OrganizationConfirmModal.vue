<script setup lang="ts">
import { ref } from "vue";

import { notify } from "@/utils/notification";
import organizationService from "../services/organizationService";

import type { Organization } from "../types/organization";

const props = defineProps<{
  organization: Organization;
}>();

const emit = defineEmits<{
  close: [];
  select: [value: number];
}>();

const loading = ref(false);

function getErrorMessage(error: any): string {
  const message = error?.response?.data?.message;

  if (Array.isArray(message)) return message.join(", ");
  if (typeof message === "string") return message;

  return error?.message || "Gagal mengarsipkan organisasi.";
}

async function confirmArchive(): Promise<void> {
  if (loading.value) return;

  loading.value = true;

  try {
    const response = await organizationService.delete(props.organization.id);

    const result = response.data;

    if (result.error) {
      throw new Error(typeof result.message === "string" ? result.message : "Gagal mengarsipkan organisasi.");
    }

    notify.success("Organisasi berhasil diarsipkan.");

    emit("close");
    emit("select", props.organization.id);
  } catch (error: any) {
    notify.error(getErrorMessage(error));
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div>
    <p class="mb-2">Apakah kamu yakin ingin mengarsipkan organisasi berikut?</p>

    <div class="border rounded p-3 mb-3">
      <div class="fw-semibold">{{ organization.name }}</div>
      <small class="text-muted"> {{ organization.code }} · {{ organization.slug }} </small>
    </div>

    <div class="alert alert-warning mb-0">Organisasi tidak akan tampil lagi dalam daftar aktif.</div>

    <div class="d-flex justify-content-end gap-2 mt-4">
      <button type="button" class="btn btn-light" :disabled="loading" @click="emit('close')">Batal</button>

      <button type="button" class="btn btn-danger" :disabled="loading" @click="confirmArchive">
        <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>

        Arsipkan Organisasi
      </button>
    </div>
  </div>
</template>
