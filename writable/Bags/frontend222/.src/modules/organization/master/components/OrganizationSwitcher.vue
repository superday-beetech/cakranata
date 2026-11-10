<script setup lang="ts">
import { computed } from "vue";
import { useRouter } from "vue-router";
import { useOrganizationContextStore } from "@/modules/organization/stores/organizationContextStore";
import { notify } from "@/utils/notification";

const router = useRouter();
const contextStore = useOrganizationContextStore();

const activeOrganizationId = computed(() => contextStore.activeOrganization?.id ?? null);

async function switchOrganization(organizationId: number) {
  if (organizationId === activeOrganizationId.value || contextStore.switching) {
    return;
  }

  try {
    await contextStore.switchOrganization(organizationId);

    notify.success("Organisasi aktif berhasil diganti.");

    await router.replace({ name: "Dashboard" });
  } catch (error) {
    notify.error(error instanceof Error ? error.message : "Gagal mengganti organisasi.");
  }
}

function openOrganizationSelection() {
  router.push({ name: "OrganizationSelection" });
}
</script>

<template>
  <div class="dropdown acn-organization-switcher">
    <button type="button" class="nav-link d-flex align-items-center gap-2" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false" aria-label="Ganti organisasi" :disabled="contextStore.switching">
      <span class="organization-icon rounded-circle d-flex align-items-center justify-content-center">
        <i class="ti ti-building fs-5"></i>
      </span>

      <span class="organization-info text-start d-none d-xl-block">
        <span class="d-block fw-semibold text-truncate">
          {{ contextStore.activeOrganization?.name ?? "Pilih Organisasi" }}
        </span>

        <small v-if="contextStore.activeUnit" class="d-block text-muted text-truncate">
          {{ contextStore.activeUnit.name }}
        </small>
      </span>

      <i class="ti ti-chevron-down fs-3 d-none d-xl-inline-block"></i>

      <span v-if="contextStore.switching" class="spinner-border spinner-border-sm" role="status">
        <span class="visually-hidden">Mengganti Workspace...</span>
      </span>
    </button>

    <div class="dropdown-menu dropdown-menu-end content-dd dropdown-menu-animate-up">
      <div class="px-4 py-3 border-bottom">
        <h6 class="mb-0 fw-semibold fs-4">Workspace</h6>
        <small class="text-muted">Pilih ruang kerja aktif</small>
      </div>

      <div class="message-body">
        <button
          v-for="organization in contextStore.availableOrganizations"
          :key="organization.organization_id"
          type="button"
          class="dropdown-item d-flex align-items-center gap-3 px-4 py-3"
          :class="{
            active: organization.organization_id === activeOrganizationId,
          }"
          :disabled="contextStore.switching"
          @click="switchOrganization(organization.organization_id)"
        >
          <span class="organization-item-icon rounded-circle d-flex align-items-center justify-content-center">
            <i class="ti ti-building fs-5"></i>
          </span>

          <span class="flex-grow-1 overflow-hidden">
            <span class="d-block fw-semibold text-truncate">
              {{ organization.organization_name }}
            </span>

            <small class="d-block text-muted">
              {{ organization.membership_role }}
            </small>
          </span>

          <i v-if="organization.organization_id === activeOrganizationId" class="ti ti-check text-primary fs-5"></i>
        </button>
      </div>

      <div class="border-top px-4 py-2">
        <button type="button" class="dropdown-item rounded-2 py-2" @click="openOrganizationSelection">
          <i class="ti ti-layout-grid me-2 fs-5"></i>
          Lihat Semua Workspace
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.acn-organization-switcher .nav-link {
  border: 0;
  background: transparent;
  color: var(--bs-body-color);
  border-radius: 25px;
  padding: 5px 10px;
  max-width: 260px;
}

.acn-organization-switcher .nav-link:hover,
.acn-organization-switcher .nav-link:focus {
  background: var(--bs-light);
}

.organization-icon,
.organization-item-icon {
  width: 35px;
  height: 35px;
  flex: 0 0 35px;
  background: var(--bs-primary-bg-subtle);
  color: var(--bs-primary);
}

.organization-info {
  min-width: 0;
  max-width: 150px;
  line-height: 1.25;
}

.organization-info > span {
  font-size: 13px;
}

.organization-info small {
  font-size: 11px;
}

.acn-organization-switcher .dropdown-menu {
  min-width: 280px;
  max-width: min(320px, calc(100vw - 24px));
}

.acn-organization-switcher .dropdown-item.active {
  background: var(--bs-primary-bg-subtle);
  color: var(--bs-primary);
}

.acn-organization-switcher .dropdown-item:disabled {
  opacity: 0.6;
}
</style>
