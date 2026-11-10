<script setup lang="ts">
import { ref } from "vue";

import organizationMembershipService from "../services/organizationMembershipService";

import type { OrganizationMembership } from "../types/organizationMembership";

import { notify } from "@/utils/notification";

interface Props {
  organizationId: number;
  membership: OrganizationMembership;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: "close"): void;
  (e: "select", value: number): void;
}>();

const loading = ref(false);

async function deactivateMembership() {
  if (loading.value) return;

  loading.value = true;

  try {
    const response = await organizationMembershipService.deactivateMembership(props.organizationId, props.membership.id);

    const result = response.data;

    if (result.error) {
      notify.error(result.message || "Gagal menonaktifkan membership.");
      return;
    }

    notify.success(result.message || "Membership berhasil dinonaktifkan.");

    emit("select", props.membership.id);
    emit("close");
  } catch (error: any) {
    notify.error(error?.response?.data?.message || error?.message || "Terjadi kesalahan saat menonaktifkan membership.");
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div>
    <div class="modal-body">
      <div class="d-flex align-items-start gap-3">
        <div class="rounded-circle bg-light-warning text-warning p-3">
          <i class="ti ti-alert-triangle fs-5"></i>
        </div>

        <div>
          <h5 class="mb-2">Nonaktifkan Membership?</h5>

          <p class="mb-2">Anda akan menonaktifkan keanggotaan user berikut:</p>

          <div class="fw-semibold">
            {{ membership.username || `User #${membership.user_id}` }}
          </div>

          <div class="text-muted small">Role: {{ membership.membership_role }}</div>

          <p class="text-muted small mt-3 mb-0">Data membership tetap tersimpan, tetapi statusnya berubah menjadi tidak aktif.</p>
        </div>
      </div>
    </div>

    <div class="modal-footer">
      <button type="button" class="btn btn-light" :disabled="loading" @click="emit('close')">Batal</button>

      <button type="button" class="btn btn-warning" :disabled="loading" @click="deactivateMembership">
        <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>

        {{ loading ? "Memproses..." : "Ya, Nonaktifkan" }}
      </button>
    </div>
  </div>
</template>
