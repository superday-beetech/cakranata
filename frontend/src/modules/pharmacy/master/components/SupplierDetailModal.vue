<script setup lang="ts">
import type { Supplier } from "../services/supplierService";

defineProps<{
  supplier: Supplier;
}>();

const emit = defineEmits<{
  select: [value: Supplier];
  close: [];
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
  <div class="modal-body">
    <div class="d-flex justify-content-between align-items-start mb-4">
      <div>
        <h5 class="mb-1">{{ supplier.name }}</h5>
        <span class="text-muted">{{ supplier.code }}</span>
      </div>

      <span class="badge" :class="Number(supplier.is_active) === 1 ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'">
        {{ Number(supplier.is_active) === 1 ? "Aktif" : "Nonaktif" }}
      </span>
    </div>

    <div class="table-responsive">
      <table class="table table-borderless align-middle mb-0">
        <tbody>
          <tr>
            <th class="text-muted ps-0" style="width: 35%">Kode</th>
            <td>{{ supplier.code }}</td>
          </tr>
          <tr>
            <th class="text-muted ps-0">Nama</th>
            <td>{{ supplier.name }}</td>
          </tr>
          <tr>
            <th class="text-muted ps-0">Telepon</th>
            <td>{{ supplier.phone || "-" }}</td>
          </tr>
          <tr>
            <th class="text-muted ps-0">Email</th>
            <td>{{ supplier.email || "-" }}</td>
          </tr>
          <tr>
            <th class="text-muted ps-0">Alamat</th>
            <td class="text-wrap">{{ supplier.address || "-" }}</td>
          </tr>
          <tr>
            <th class="text-muted ps-0">Dibuat</th>
            <td>{{ formatDate(supplier.created_at) }}</td>
          </tr>
          <tr>
            <th class="text-muted ps-0">Diperbarui</th>
            <td>{{ formatDate(supplier.updated_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="modal-footer">
    <button class="btn btn-light" type="button" @click="emit('close')">Tutup</button>

    <button class="btn btn-primary" type="button" @click="emit('select', supplier)">
      <i class="ti ti-edit me-1" />
      Edit Supplier
    </button>
  </div>
</template>
