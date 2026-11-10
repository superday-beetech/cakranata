<script setup lang="ts">
import { ref } from "vue";

import { notify } from "@/utils/notification";
import organizationUnitService from "../services/organizationUnitService";

import type { OrganizationUnit } from "../types/organizationUnit";

interface Props {
  organizationId: number;
  unit: OrganizationUnit;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  close: [];
  select: [value: number];
}>();

const processing = ref(false);

function getErrorMessage(error: any): string {
  const message = error?.response?.data?.message;

  if (Array.isArray(message)) return message.join(", ");
  if (typeof message === "string") return message;

  return error?.message || "Terjadi kesalahan.";
}

async function confirmDeactivate(): Promise<void> {
  if (processing.value) return;

  processing.value = true;

  try {
    const response = await organizationUnitService.deactivateUnit(props.organizationId, props.unit.id);

    const result = response.data;

    if (result.error) {
      throw new Error(typeof result.message === "string" ? result.message : "Gagal menonaktifkan unit organisasi.");
    }

    notify.success("Unit organisasi berhasil dinonaktifkan.");

    emit("close");
    emit("select", props.unit.id);
  } catch (error: any) {
    notify.error(getErrorMessage(error));
  } finally {
    processing.value = false;
  }
}
</script>

<template>
  <div>
    <div class="modal-body">
      <div class="text-center py-3">
        <div class="rounded-circle bg-warning-subtle text-warning d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px">
          <i class="ti ti-alert-triangle fs-7"></i>
        </div>

        <h5 class="mb-2">Nonaktifkan Unit Organisasi?</h5>

        <p class="text-muted mb-3">Anda akan menonaktifkan unit berikut:</p>

        <div class="border rounded p-3 text-start">
          <div class="fw-semibold">{{ unit.name }}</div>
          <small class="text-muted">{{ unit.code }}</small>
        </div>

        <p class="text-muted small mt-3 mb-0">Unit tidak akan dihapus dari database. Pastikan tidak ada proses operasional yang masih bergantung pada unit ini.</p>
      </div>
    </div>

    <div class="modal-footer">
      <button type="button" class="btn btn-light" :disabled="processing" @click="emit('close')">Batal</button>

      <button type="button" class="btn btn-warning" :disabled="processing" @click="confirmDeactivate">
        <span v-if="processing" class="spinner-border spinner-border-sm me-1"></span>

        <i v-else class="ti ti-ban me-1"></i>

        {{ processing ? "Memproses..." : "Ya, Nonaktifkan" }}
      </button>
    </div>
  </div>
</template>
