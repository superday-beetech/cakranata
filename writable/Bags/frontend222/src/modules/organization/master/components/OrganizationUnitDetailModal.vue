<script setup lang="ts">
import { computed } from "vue";

import type { OrganizationUnit } from "../types/organizationUnit";

interface Props {
  organizationUnit: OrganizationUnit;
  units?: OrganizationUnit[];
}

const props = withDefaults(defineProps<Props>(), {
  units: () => [],
});

const emit = defineEmits<{
  close: [];
  select: [value: OrganizationUnit];
}>();

const unitTypeLabels: Record<string, string> = {
  head_office: "Kantor Pusat",
  branch: "Cabang",
  division: "Divisi",
  department: "Departemen",
  warehouse: "Gudang",
  operational_location: "Lokasi Operasional",
  other: "Lainnya",
};

const parentUnit = computed(() => props.units.find((unit) => unit.id === props.organizationUnit.parent_id));

const unitTypeLabel = computed(() => unitTypeLabels[props.organizationUnit.unit_type] ?? props.organizationUnit.unit_type);

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
    <div class="modal-body">
      <div class="d-flex align-items-center gap-3 mb-4">
        <div class="rounded-circle bg-light text-primary d-flex align-items-center justify-content-center" style="width: 52px; height: 52px">
          <i class="ti ti-building-community fs-7"></i>
        </div>

        <div>
          <h5 class="mb-1">{{ organizationUnit.name }}</h5>
          <div class="text-muted small">
            {{ organizationUnit.code }}
          </div>
        </div>

        <span class="badge ms-auto" :class="organizationUnit.status === 'active' ? 'bg-success' : 'bg-secondary'">
          {{ organizationUnit.status === "active" ? "Aktif" : "Tidak Aktif" }}
        </span>
      </div>

      <div class="border rounded p-3">
        <h6 class="fw-semibold mb-3">Informasi Unit</h6>

        <div class="row g-3">
          <div class="col-md-6">
            <div class="text-muted small mb-1">Kode Unit</div>
            <div class="fw-semibold">{{ organizationUnit.code }}</div>
          </div>

          <div class="col-md-6">
            <div class="text-muted small mb-1">Nama Unit</div>
            <div class="fw-semibold">{{ organizationUnit.name }}</div>
          </div>

          <div class="col-md-6">
            <div class="text-muted small mb-1">Jenis Unit</div>
            <div>{{ unitTypeLabel }}</div>
          </div>

          <div class="col-md-6">
            <div class="text-muted small mb-1">Unit Induk</div>
            <div>
              <template v-if="parentUnit">
                {{ parentUnit.name }}
                <small class="text-muted">({{ parentUnit.code }})</small>
              </template>
              <span v-else class="text-muted">Tidak memiliki induk</span>
            </div>
          </div>

          <div class="col-md-6">
            <div class="text-muted small mb-1">Tanggal Dibuat</div>
            <div>{{ formatDate(organizationUnit.created_at) }}</div>
          </div>

          <div class="col-md-6">
            <div class="text-muted small mb-1">Terakhir Diperbarui</div>
            <div>{{ formatDate(organizationUnit.updated_at) }}</div>
          </div>
        </div>
      </div>
    </div>

    <div class="modal-footer">
      <button type="button" class="btn btn-light" @click="emit('close')">Tutup</button>

      <button type="button" class="btn btn-primary" @click="emit('select', organizationUnit)">
        <i class="ti ti-edit me-1"></i>
        Ubah Unit
      </button>
    </div>
  </div>
</template>
