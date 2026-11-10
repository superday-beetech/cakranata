<script setup lang="ts">
import { computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useOrganizationContextStore } from "@/modules/organization/stores/organizationContextStore";
import { notify } from "@/utils/notification";

const router = useRouter();
const contextStore = useOrganizationContextStore();
const activeOrganizationId = computed(() => contextStore.activeOrganization?.id ?? null);
async function selectOrganization(organizationId: number) {
  try {
    await contextStore.switchOrganization(organizationId);

    notify.success("Organisasi berhasil dipilih.");

    await router.replace({ name: "Dashboard" });
  } catch (error) {
    notify.error(error instanceof Error ? error.message : "Gagal memilih organisasi.");
  }
}

onMounted(async () => {
  if (!contextStore.initialized) {
    try {
      await contextStore.initialize();
    } catch {
      // Error ditampilkan melalui state store.
    }
  }
});
</script>

<template>
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-12 col-lg-9 col-xl-8">
        <div class="text-center mb-4">
          <h3 class="fw-bold">Pilih Workspace</h3>
          <p class="text-muted">Pilih Workspace yang ingin Anda kelola.</p>
        </div>

        <div v-if="contextStore.loading" class="text-center py-5">
          <div class="spinner-border text-primary"></div>
          <p class="text-muted mt-3">Memuat daftar Ruang Kerja...</p>
        </div>

        <div v-else-if="contextStore.errorMessage" class="alert alert-danger">
          {{ contextStore.errorMessage }}

          <button type="button" class="btn btn-sm btn-outline-danger ms-2" @click="contextStore.initialize(true)">Coba Lagi</button>
        </div>

        <div v-else-if="!contextStore.hasOrganizations" class="card border-0 shadow-sm">
          <div class="card-body text-center py-5">
            <i class="bi bi-building fs-1 text-muted"></i>

            <h5 class="mt-3">Belum Ada Workspace</h5>

            <p class="text-muted mb-0">Akun Anda belum memiliki membership Workspace yang aktif.</p>
          </div>
        </div>

        <div v-else class="row g-3">
          <div v-for="organization in contextStore.availableOrganizations" :key="organization.organization_id" class="col-12 col-md-6">
            <div
              class="card h-100 shadow-sm workspace-card"
              :class="{
                'workspace-active': organization.organization_id === activeOrganizationId,
              }"
            >
              <div class="card-body">
                <div class="d-flex align-items-start gap-3">
                  <div class="rounded bg-info-subtle p-3">
                    <i class="ti ti-building-community fs-3"></i>
                  </div>

                  <div class="flex-grow-1 min-w-0">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                      <h5 class="fw-semibold mb-1">
                        {{ organization.organization_name }}
                      </h5>

                      <span v-if="organization.organization_id === activeOrganizationId" class="badge bg-success-subtle text-success d-inline-flex align-items-center gap-1">
                        <i class="ti ti-check"></i>
                        Aktif
                      </span>
                    </div>

                    <div class="text-muted small">
                      {{ organization.organization_code }}
                    </div>

                    <div class="mt-2">
                      <span class="badge bg-primary-subtle text-primary">
                        {{ organization.membership_role }}
                      </span>

                      <span v-if="organization.is_default" class="badge bg-light text-muted ms-1"> Default </span>
                    </div>

                    <div v-if="organization.unit_name" class="small text-muted mt-2">
                      <i class="ti ti-sitemap me-1"></i>
                      {{ organization.unit_name }}
                    </div>
                  </div>
                </div>
              </div>

              <div class="card-footer bg-transparent border-0 pt-0">
                <button
                  type="button"
                  class="btn w-100"
                  :class="organization.organization_id === activeOrganizationId ? 'btn-outline-success text-success' : 'btn-primary'"
                  :disabled="contextStore.switching || organization.organization_id === activeOrganizationId"
                  @click="selectOrganization(organization.organization_id)"
                >
                  <i v-if="organization.organization_id === activeOrganizationId" class="ti ti-check me-1"></i>

                  {{ organization.organization_id === activeOrganizationId ? "Workspace Aktif" : "Gunakan Workspace Ini" }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<style scoped>
.workspace-card {
  border: 1px solid var(--bs-border-color);
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
}

.workspace-card:hover {
  box-shadow: var(--bs-box-shadow) !important;
}

.workspace-card.workspace-active {
  border: 1px solid var(--bs-success) !important;
}
</style>
