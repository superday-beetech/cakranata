<script setup lang="ts">
import type { ApplyAction, ScanFilter, ScanItem, StatusField } from "../types/route-registry";

const props = defineProps<{
  items: readonly ScanItem[];
  selected: ReadonlySet<string>;
  allVisibleSelected: boolean;
  filter: ScanFilter;
  search: string;
  action: ApplyAction;
  loading: boolean;
  selectedCount: number;
  eligibleCount: number;
  totalCount: number;
  toggleStatus: (id: number, field: StatusField, value: boolean) => Promise<boolean>;
}>();

const emit = defineEmits<{
  (e: "toggle", endpoint: string): void;
  (e: "select-all", checked: boolean): void;
  (e: "select-all-click"): void;
  (e: "clear"): void;
  (e: "apply"): void;
  (e: "update:filter", value: ScanFilter): void;
  (e: "update:search", value: string): void;
  (e: "update:action", value: ApplyAction): void;
  (e: "edit-verbs", id: number, endpoint: string): void;
}>();

function isRegistered(item: ScanItem): boolean {
  return item.state === "registered" || Number(item.registry?.id ?? 0) > 0;
}

function verbList(item: ScanItem): string {
  const verbs = item.registry?.verbs ?? [];
  return verbs.length ? verbs.join(", ") : "—";
}

async function onToggleStatus(item: ScanItem, field: StatusField, event: Event): Promise<void> {
  const id = Number(item.registry?.id ?? 0);
  if (id < 1) return;

  const input = event.target as HTMLInputElement;
  const desired = input.checked;
  const previous = !desired;

  input.disabled = true;
  try {
    const ok = await props.toggleStatus(id, field, desired);
    if (!ok) input.checked = previous;
  } finally {
    input.disabled = false;
  }
}

function onFilterChange(event: Event): void {
  emit("update:filter", (event.target as HTMLSelectElement).value as ScanFilter);
}

function onActionChange(event: Event): void {
  emit("update:action", (event.target as HTMLSelectElement).value as ApplyAction);
}

function onSearchInput(event: Event): void {
  emit("update:search", (event.target as HTMLInputElement).value);
}

function onHeaderCheck(event: Event): void {
  emit("select-all", (event.target as HTMLInputElement).checked);
}
</script>

<template>
  <div>
    <!-- Toolbar atas -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h6 class="mb-0 fw-semibold">Hasil Pemindaian Modul</h6>
        <span class="text-muted small"> Scan tidak mengubah basis data sebelum Anda menerapkannya. </span>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" @click="emit('select-all-click')"><i class="ti ti-checkbox me-1"></i> Pilih Semua</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" @click="emit('clear')"><i class="ti ti-square me-1"></i> Batal Pilih</button>
      </div>
    </div>

    <!-- Filter bar -->
    <div class="row g-2 mb-3">
      <div class="col-12 col-md-5">
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0">
            <i class="ti ti-search text-muted"></i>
          </span>
          <input :value="search" type="search" class="form-control border-start-0 ps-0" placeholder="Cari module, controller, method, endpoint..." @input="onSearchInput" />
        </div>
      </div>
      <div class="col-12 col-md-3">
        <select class="form-select" :value="filter" @change="onFilterChange">
          <option value="all">Semua hasil</option>
          <option value="new">Belum terdaftar</option>
          <option value="registered">Sudah terdaftar</option>
        </select>
      </div>
      <div class="col-12 col-md-4">
        <select class="form-select" :value="action" @change="onActionChange">
          <option value="insert">Insert endpoint baru</option>
          <option value="update">Update endpoint terdaftar</option>
          <option value="delete">Delete endpoint terdaftar</option>
        </select>
      </div>
    </div>

    <!-- Table -->
    <div class="table-responsive border rounded-2">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 40px" class="ps-3">
              <input type="checkbox" class="form-check-input" :checked="allVisibleSelected" aria-label="Pilih semua" @change="onHeaderCheck" />
            </th>
            <th>Module</th>
            <th>Controller&Method</th>
            <th>Verbs</th>
            <th class="text-center">Routable</th>
            <th class="text-center">Allowed</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="6" class="text-center text-muted py-5">
              <span class="spinner-border spinner-border-sm text-primary me-2"></span>
              Memproses...
            </td>
          </tr>
          <tr v-else-if="items.length === 0">
            <td colspan="6" class="text-center text-muted py-4">Tidak ada endpoint yang cocok.</td>
          </tr>
          <tr v-for="item in items" v-else :key="item.endpoint">
            <td class="ps-3">
              <input type="checkbox" class="form-check-input" :checked="selected.has(item.endpoint)" aria-label="Pilih endpoint" @change="emit('toggle', item.endpoint)" />
            </td>

            <td>{{ item.module || "—" }}</td>

            <td class="text-break">
              <div>{{ item.controller || "—" }}</div>
              <small class="text-muted">
                <code>{{ item.endpoint }}</code>
              </small>
              <div class="mt-1">
                <span class="badge" :class="isRegistered(item) ? 'bg-success-subtle text-success' : 'bg-info-subtle text-info'">{{ isRegistered(item) ? "Terdaftar" : "Baru" }}</span>

                <span v-if="action === 'insert' && isRegistered(item)" class="badge bg-warning-subtle text-warning ms-1">Akan dilewati</span>

                <span v-if="action === 'update' && !isRegistered(item)" class="badge bg-warning-subtle text-warning ms-1">Belum terdaftar</span>
              </div>
            </td>

            <td>{{ verbList(item) }}</td>

            <td class="text-center">
              <label v-if="isRegistered(item)" class="form-check form-switch mb-0 d-inline-block">
                <input class="form-check-input" type="checkbox" :checked="item.registry?.is_routable === 1" @change="onToggleStatus(item, 'routable', $event)" />
              </label>
              <i v-else class="bi bi-square text-muted"></i>
            </td>

            <td class="text-center">
              <label v-if="isRegistered(item)" class="form-check form-switch mb-0 d-inline-block">
                <input class="form-check-input" type="checkbox" :checked="item.registry?.is_allowed === 1" @change="onToggleStatus(item, 'allowed', $event)" />
              </label>
              <i v-else class="bi bi-square text-muted"></i>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Footer -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
      <span class="small text-muted"> {{ items.length }} ditampilkan dari {{ totalCount }} hasil; {{ selectedCount }} dipilih, {{ eligibleCount }} sesuai tindakan. </span>
      <button type="button" class="btn btn-primary btn-sm px-4" :disabled="eligibleCount === 0 || loading" @click="emit('apply')">Terapkan Pilihan</button>
    </div>
  </div>
</template>
