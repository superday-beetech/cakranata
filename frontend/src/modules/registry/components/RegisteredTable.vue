<script setup lang="ts">
import { computed } from "vue";

import type { HttpVerb, Pagination, RegisteredItem, StatusField } from "../types/route-registry";

const props = defineProps<{
  items: readonly RegisteredItem[];
  loading: boolean;
  pagination: Pagination;
  perPage: number;
  search: string;
  toggleStatus: (id: number, field: StatusField, value: boolean) => Promise<boolean>;
}>();

const emit = defineEmits<{
  (e: "reload"): void;
  (e: "update:search", value: string): void;
  (e: "update:perPage", value: number): void;
  (e: "page", value: number): void;
  (e: "edit-verbs", id: number, endpoint: string): void;
  (e: "copy", endpoint: string): void;
}>();

const pageLabel = computed(() => `Halaman ${props.pagination.page} / ${props.pagination.totalPages}`);

function statusMeta(status: string): { icon: string; color: string } {
  const s = String(status).toLowerCase();
  if (s === "scanned") return { icon: "ti ti-scan", color: "text-success" };
  if (s === "new") return { icon: "ti ti-sparkles", color: "text-primary" };
  return { icon: "ti ti-circle-x", color: "text-secondary" };
}

function verbList(verbs: readonly HttpVerb[]): string {
  return verbs.length ? verbs.join(", ") : "—";
}

async function onToggle(item: RegisteredItem, field: StatusField, event: Event): Promise<void> {
  const input = event.target as HTMLInputElement;
  const desired = input.checked;
  const previous = !desired;

  input.disabled = true;
  try {
    const ok = await props.toggleStatus(item.registry.id, field, desired);
    if (!ok) input.checked = previous;
  } finally {
    input.disabled = false;
  }
}

function onSearchInput(event: Event): void {
  emit("update:search", (event.target as HTMLInputElement).value);
}

function onPerPageChange(event: Event): void {
  emit("update:perPage", Number((event.target as HTMLSelectElement).value));
}
</script>

<template>
  <div>
    <!-- Toolbar -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h6 class="mb-0 fw-semibold">Endpoint Tersimpan</h6>
        <span class="text-muted small"> Kelola konfigurasi registry yang tersimpan di dalam database. </span>
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="loading" @click="emit('reload')"><i class="ti ti-reload me-1"></i> Muat Ulang</button>
    </div>

    <!-- Search + per page -->
    <div class="row g-2 mb-3">
      <div class="col-12 col-md-8">
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0">
            <i class="ti ti-search text-muted"></i>
          </span>
          <input :value="search" type="search" class="form-control border-start-0 ps-0" placeholder="Cari endpoint terdaftar..." @input="onSearchInput" />
        </div>
      </div>
      <div class="col-12 col-md-4">
        <select class="form-select" :value="perPage" @change="onPerPageChange">
          <option :value="10">10 per halaman</option>
          <option :value="25">25 per halaman</option>
          <option :value="50">50 per halaman</option>
          <option :value="100">100 per halaman</option>
        </select>
      </div>
    </div>

    <!-- Table -->
    <div class="table-responsive border rounded-2">
      <table class="table table-hover align-middle table-sm mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Module</th>
            <th>Endpoint</th>
            <th>Permissions</th>
            <th>HTTP Verbs</th>
            <th class="text-center">Routable</th>
            <th class="text-center">Allowed</th>
            <th class="text-center">Scan</th>
            <th class="text-end pe-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="8" class="text-center text-muted py-5">
              <span class="spinner-border spinner-border-sm text-primary me-2"></span>
              Memuat registry...
            </td>
          </tr>
          <tr v-else-if="items.length === 0">
            <td colspan="8" class="text-center text-muted py-4">Belum ada endpoint terdaftar.</td>
          </tr>
          <tr v-for="item in items" v-else :key="item.registry.id">
            <td class="small text-muted">{{ item.module }}</td>

            <td class="small text-break">
              <code>{{ item.endpoint }}</code>
              <div class="small text-muted">{{ item.registry.url_route || item.controller }}::{{ item.method }}</div>
            </td>

            <td class="small">
              <template v-if="item.registry.permissions.length">
                <span v-for="perm in item.registry.permissions" :key="perm" class="badge bg-info-subtle text-info me-1 mb-1">{{ perm }}</span>
              </template>
              <span v-else class="badge bg-warning-subtle text-warning">Belum diatur</span>
            </td>

            <td class="small">
              <div class="d-flex flex-wrap gap-1 mb-1">
                <span v-for="verb in item.registry.verbs" :key="verb" class="badge bg-primary-subtle text-primary">{{ verb }}</span>
                <span v-if="!item.registry.verbs.length" class="text-muted">
                  {{ verbList(item.registry.verbs) }}
                </span>
              </div>
              <button type="button" class="btn btn-sm btn-outline-primary" :disabled="item.registry.id < 1" @click="emit('edit-verbs', item.registry.id, item.endpoint)">
                <i class="bi bi-sliders"></i>
              </button>
            </td>

            <td class="text-center">
              <label class="form-check form-switch mb-0 d-inline-block">
                <input class="form-check-input" type="checkbox" :checked="item.registry.is_routable === 1" :disabled="item.registry.id < 1" @change="onToggle(item, 'routable', $event)" />
              </label>
            </td>

            <td class="text-center">
              <label class="form-check form-switch mb-0 d-inline-block">
                <input class="form-check-input" type="checkbox" :checked="item.registry.is_allowed === 1" :disabled="item.registry.id < 1" @change="onToggle(item, 'allowed', $event)" />
              </label>
            </td>

            <td class="text-center">
              <i class="fs-5" :class="[statusMeta(item.registry.scan_status).icon, statusMeta(item.registry.scan_status).color]" :title="item.registry.scan_status"></i>
            </td>

            <td class="text-center">
              <button type="button" class="btn btn-sm btn-link p-0" title="Salin endpoint" @click="emit('copy', item.endpoint)">
                <i class="ti ti-copy fs-5"></i>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2">
      <span class="small text-muted"> {{ items.length }} endpoint pada halaman ini; total {{ pagination.total }}. </span>
      <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="!pagination.hasPrev" @click="emit('page', pagination.page - 1)">Sebelumnya</button>
        <span class="small fw-medium">{{ pageLabel }}</span>
        <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="!pagination.hasNext" @click="emit('page', pagination.page + 1)">Berikutnya</button>
      </div>
    </div>
  </div>
</template>
