<script setup lang="ts">
import { computed } from "vue";

import type { OrganizationMembership } from "../types/organizationMembership";

interface Props {
  membership: OrganizationMembership;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: "close"): void;
  (e: "select", value: OrganizationMembership): void;
}>();

const statusLabel = computed(() => (props.membership.status === "active" ? "Aktif" : "Tidak Aktif"));

const isDefault = computed(() => Boolean(Number(props.membership.is_default)));

function editMembership() {
  emit("select", props.membership);
}
</script>

<template>
  <div>
    <div class="modal-body">
      <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
          <h5 class="mb-1">
            {{ membership.username || `User #${membership.user_id}` }}
          </h5>

          <!-- <div class="text-muted small">
            {{ membership.user_email || "-" }}
          </div> -->
        </div>

        <span class="badge" :class="membership.status === 'active' ? 'bg-light-success text-success' : 'bg-light-secondary text-secondary'">
          {{ statusLabel }}
        </span>
      </div>

      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <tbody>
            <tr>
              <th width="40%">Membership ID</th>
              <td>{{ membership.id }}</td>
            </tr>

            <tr>
              <th>Organization ID</th>
              <td>{{ membership.organization_id }}</td>
            </tr>

            <tr>
              <th>User ID</th>
              <td>{{ membership.user_id }}</td>
            </tr>

            <tr>
              <th>Unit Organisasi</th>
              <td>
                {{ membership.unit_name || (membership.unit_id ? `Unit #${membership.unit_id}` : "Belum ditempatkan") }}
              </td>
            </tr>

            <tr>
              <th>Role Membership</th>
              <td>{{ membership.membership_role }}</td>
            </tr>

            <tr>
              <th>Organisasi Default</th>
              <td>
                <span class="badge" :class="isDefault ? 'bg-light-success text-success' : 'bg-light-secondary text-secondary'">
                  {{ isDefault ? "Ya" : "Tidak" }}
                </span>
              </td>
            </tr>

            <tr>
              <th>Dibuat</th>
              <td>{{ membership.created_at || "-" }}</td>
            </tr>

            <tr>
              <th>Diperbarui</th>
              <td>{{ membership.updated_at || "-" }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="modal-footer">
      <button type="button" class="btn btn-light" @click="emit('close')">Tutup</button>

      <button type="button" class="btn btn-primary" @click="editMembership">
        <i class="ti ti-edit me-1"></i>
        Ubah
      </button>
    </div>
  </div>
</template>
