<script setup lang="ts">
import type { Organization } from "../types/organization";

defineProps<{
  organization: Organization;
}>();

const emit = defineEmits<{
  close: [];
  select: [value: Organization];
}>();

function formatDate(value?: string | null): string {
  if (!value) return "-";

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat("id-ID", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
}
</script>

<template>
  <div>
    <div class="d-flex align-items-start justify-content-between mb-4">
      <div>
        <h5 class="mb-1">{{ organization.name }}</h5>
        <div class="text-muted small">
          {{ organization.legal_name || "Nama legal belum diisi" }}
        </div>
      </div>

      <span
        class="badge"
        :class="{
          'bg-success': organization.status === 'active',
          'bg-secondary': organization.status === 'inactive',
          'bg-warning text-dark': organization.status === 'suspended',
        }"
      >
        {{ organization.status }}
      </span>
    </div>

    <div class="row g-3">
      <div class="col-md-6">
        <div class="text-muted small">Kode Organisasi</div>
        <div class="fw-semibold">{{ organization.code }}</div>
      </div>

      <div class="col-md-6">
        <div class="text-muted small">Slug</div>
        <div class="fw-semibold">{{ organization.slug }}</div>
      </div>

      <div class="col-md-6">
        <div class="text-muted small">Jenis Organisasi</div>
        <div>{{ organization.organization_type }}</div>
      </div>

      <div class="col-md-6">
        <div class="text-muted small">Timezone</div>
        <div>{{ organization.timezone }}</div>
      </div>

      <div class="col-md-6">
        <div class="text-muted small">Mata Uang</div>
        <div>{{ organization.currency }}</div>
      </div>

      <div class="col-md-6">
        <div class="text-muted small">Dibuat</div>
        <div>{{ formatDate(organization.created_at) }}</div>
      </div>

      <div class="col-md-6">
        <div class="text-muted small">Terakhir Diperbarui</div>
        <div>{{ formatDate(organization.updated_at) }}</div>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
      <button type="button" class="btn btn-light" @click="emit('close')">Tutup</button>

      <button
        type="button"
        class="btn btn-primary"
        @click="
          () => {
            emit('close');
            emit('select', organization);
          }
        "
      >
        <i class="ti ti-edit me-1"></i>
        Ubah
      </button>
    </div>
  </div>
</template>
