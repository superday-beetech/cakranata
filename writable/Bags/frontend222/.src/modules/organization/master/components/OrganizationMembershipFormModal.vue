<script setup lang="ts">
import { computed, ref, watch } from "vue";

import organizationMembershipService from "../services/organizationMembershipService";

import type { OrganizationMembership, CreateOrganizationMembershipPayload, UpdateOrganizationMembershipPayload } from "../types/organizationMembership";

import { notify } from "@/utils/notification";
import { ORGANIZATION_MEMBERSHIP_ROLES } from "../types/organizationMembership";
interface UserOption {
  id: number;
  username: string;
}

interface UnitOption {
  id: number;
  name: string;
  code?: string;
}

interface Props {
  organizationId: number;
  membership?: OrganizationMembership | null;
  users: UserOption[];
  units?: UnitOption[];
}

const props = withDefaults(defineProps<Props>(), {
  membership: null,
  units: () => [],
});

const emit = defineEmits<{
  (e: "close"): void;
  (e: "select", value: OrganizationMembership): void;
}>();

const isEdit = computed(() => !!props.membership);

const loading = ref(false);
const errors = ref<Record<string, string>>({});

const form = ref({
  user_id: "",
  unit_id: "",
  membership_role: "member",
  status: "active" as "active" | "inactive",
  is_default: false,
});

watch(
  () => props.membership,
  (membership) => {
    form.value = {
      user_id: membership ? String(membership.user_id) : "",
      unit_id: membership?.unit_id ? String(membership.unit_id) : "",
      membership_role: membership?.membership_role ?? "member",
      status: membership?.status ?? "active",
      is_default: Boolean(Number(membership?.is_default ?? 0)),
    };
  },
  { immediate: true },
);

function validateForm(): boolean {
  errors.value = {};

  if (!form.value.user_id) {
    errors.value.user_id = "User wajib dipilih.";
  }

  if (!form.value.membership_role.trim()) {
    errors.value.membership_role = "Role wajib diisi.";
  }

  return Object.keys(errors.value).length === 0;
}

function getErrorMessage(error: any): string {
  return error?.response?.data?.messages?.error || error?.response?.data?.message || error?.message || "Terjadi kesalahan saat menyimpan membership.";
}

async function submitForm() {
  if (!validateForm()) return;

  loading.value = true;

  try {
    const payload = {
      user_id: Number(form.value.user_id),
      unit_id: form.value.unit_id ? Number(form.value.unit_id) : null,
      membership_role: form.value.membership_role.trim(),
      status: form.value.status,
      is_default: form.value.is_default,
    };

    let response;

    if (isEdit.value && props.membership) {
      const updatePayload: UpdateOrganizationMembershipPayload = payload;

      response = await organizationMembershipService.updateMembership(props.organizationId, props.membership.id, updatePayload);
    } else {
      const createPayload: CreateOrganizationMembershipPayload = {
        user_id: payload.user_id,
        unit_id: payload.unit_id,
        membership_role: payload.membership_role,
        is_default: payload.is_default,
      };

      response = await organizationMembershipService.createMembership(props.organizationId, createPayload);
    }

    const result = response.data;

    if (result.error) {
      notify.error(result.message || "Gagal menyimpan membership.");
      return;
    }

    notify.success(result.message || (isEdit.value ? "Membership berhasil diperbarui." : "Membership berhasil ditambahkan."));

    if (result.data) {
      emit("select", result.data as OrganizationMembership);
    }

    emit("close");
  } catch (error: any) {
    notify.error(getErrorMessage(error));
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <form @submit.prevent="submitForm">
    <div class="modal-body">
      <!-- User -->
      <div class="mb-3">
        <label class="form-label"> User <span class="text-danger">*</span> </label>

        <select v-model="form.user_id" class="form-select" :class="{ 'is-invalid': errors.user_id }" :disabled="loading || isEdit">
          <option value="" disabled>Pilih user</option>

          <option v-for="user in users" :key="user.id" :value="String(user.id)">
            {{ user.username }}
          </option>
        </select>

        <div v-if="errors.user_id" class="invalid-feedback">
          {{ errors.user_id }}
        </div>
      </div>

      <!-- Unit -->
      <div class="mb-3">
        <label class="form-label">Unit Organisasi</label>

        <select v-model="form.unit_id" class="form-select" :disabled="loading">
          <option value="">Belum ditempatkan</option>

          <option v-for="unit in units" :key="unit.id" :value="String(unit.id)">
            {{ unit.name }}
            {{ unit.code ? `(${unit.code})` : "" }}
          </option>
        </select>
      </div>

      <!-- Role -->
      <div class="mb-3">
        <label class="form-label"> Role Membership <span class="text-danger">*</span> </label>

        <select id="membership_role" v-model="form.membership_role" class="form-select" required>
          <option value="" disabled>Pilih role</option>

          <option v-for="role in ORGANIZATION_MEMBERSHIP_ROLES" :key="role.value" :value="role.value">
            {{ role.label }}
          </option>
        </select>

        <!-- <input v-model="form.membership_role" type="text" class="form-control" :class="{ 'is-invalid': errors.membership_role }" placeholder="Contoh: owner, admin, manager, member" :disabled="loading" /> -->

        <div v-if="errors.membership_role" class="invalid-feedback">
          {{ errors.membership_role }}
        </div>
      </div>

      <!-- Status -->
      <div v-if="isEdit" class="mb-3">
        <label class="form-label">Status</label>

        <select v-model="form.status" class="form-select" :disabled="loading">
          <option value="active">Aktif</option>
          <option value="inactive">Tidak Aktif</option>
        </select>
      </div>

      <!-- Default -->
      <div class="form-check mb-3">
        <input id="membershipDefault" v-model="form.is_default" class="form-check-input" type="checkbox" :disabled="loading" />

        <label class="form-check-label" for="membershipDefault"> Jadikan organisasi default user </label>
      </div>
    </div>

    <div class="modal-footer">
      <button type="button" class="btn btn-light" :disabled="loading" @click="emit('close')">Batal</button>

      <button type="submit" class="btn btn-primary" :disabled="loading">
        <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>

        {{ loading ? "Menyimpan..." : "Simpan" }}
      </button>
    </div>
  </form>
</template>
