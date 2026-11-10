<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";

import { useNavigationStore } from "@/stores/navigation";
import type { MenuPosition, RouteRegistryCandidate, RouteRegistrySaveItem } from "@/types/navigation";

import { modal } from "@/utils/modal";
import { notify } from "@/utils/notification";

const props = defineProps<{
  onSaved?: () => void | Promise<void>;
}>();

const store = useNavigationStore();

const loading = ref(false);
const saving = ref(false);
const loadError = ref("");

const existingMenus = ref<unknown[]>([]);
const candidates = ref<RouteRegistryCandidate[]>([]);
const groups = ref<string[]>([]);

const selected = reactive<Record<string, boolean>>({});
const positions = reactive<Record<string, MenuPosition>>({});
const selectedGroups = reactive<Record<string, string[]>>({});

const availableCandidates = computed(() => candidates.value.filter((item) => !item.exists && Boolean(item.url)));

const selectedCount = computed(() => availableCandidates.value.filter((item) => selected[String(item.id)]).length);

function candidateKey(item: RouteRegistryCandidate): string {
  return String(item.id);
}

function initializeCandidate(item: RouteRegistryCandidate) {
  const key = candidateKey(item);

  if (!(key in selected)) {
    selected[key] = false;
  }

  if (!(key in positions)) {
    positions[key] = "sidebar";
  }

  if (!(key in selectedGroups)) {
    selectedGroups[key] = [];
  }
}

function toggleGroup(key: string, group: string, checked: boolean) {
  const current = selectedGroups[key] ?? [];

  selectedGroups[key] = checked ? [...new Set([...current, group])] : current.filter((item) => item !== group);
}

async function loadPreview() {
  loading.value = true;
  loadError.value = "";

  try {
    const result = await store.previewRegistry();

    existingMenus.value = result.menus ?? [];
    candidates.value = result.candidates ?? [];
    groups.value = result.groups ?? [];

    for (const item of candidates.value) {
      initializeCandidate(item);
    }
  } catch (error) {
    loadError.value = error instanceof Error ? error.message : "Gagal memuat preview Route Registry.";

    notify.error(loadError.value);
  } finally {
    loading.value = false;
  }
}

async function saveSelected() {
  if (saving.value || selectedCount.value === 0) return;

  saving.value = true;

  try {
    const items: RouteRegistrySaveItem[] = availableCandidates.value
      .filter((item) => selected[candidateKey(item)])
      .map((item) => ({
        route_menu_id: Number(item.id),
        position: positions[candidateKey(item)],
        groups: [...(selectedGroups[candidateKey(item)] ?? [])],
      }));

    if (items.some((item) => !Number.isInteger(item.route_menu_id) || item.route_menu_id <= 0)) {
      throw new Error("ID Route Registry tidak valid.");
    }

    const response = await store.saveRegistry(items);
    const result = response.data as
      | {
          inserted?: unknown[];
          skipped?: unknown[];
        }
      | undefined;

    const inserted = result?.inserted?.length ?? 0;
    const skipped = result?.skipped?.length ?? 0;

    notify.success(`Sinkronisasi selesai. Berhasil: ${inserted}, dilewati: ${skipped}.`);

    await props.onSaved?.();
    modal.close();
  } catch (error) {
    notify.error(error instanceof Error ? error.message : "Sinkronisasi Route Registry gagal.");
  } finally {
    saving.value = false;
  }
}

onMounted(loadPreview);
</script>

<template>
  <div class="route-registry-import">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <div class="fw-semibold">Sinkronisasi Route Registry</div>
        <small class="text-muted"> Pilih endpoint yang ingin ditambahkan ke navigasi. </small>
      </div>

      <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="loading || saving" @click="loadPreview">
        <i class="ti ti-refresh me-1"></i>
        Muat Ulang
      </button>
    </div>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary"></div>
      <div class="small text-muted mt-2">Memuat Route Registry...</div>
    </div>

    <div v-else-if="loadError" class="alert alert-danger">
      {{ loadError }}
      <button type="button" class="btn btn-sm btn-outline-danger ms-2" @click="loadPreview">Coba lagi</button>
    </div>

    <template v-else>
      <div class="row g-2 mb-3">
        <div class="col-4">
          <div class="border rounded p-2 text-center">
            <div class="fs-4 fw-semibold">
              {{ existingMenus.length }}
            </div>
            <small class="text-muted">Terdaftar</small>
          </div>
        </div>

        <div class="col-4">
          <div class="border rounded p-2 text-center">
            <div class="fs-4 fw-semibold">
              {{ availableCandidates.length }}
            </div>
            <small class="text-muted">Kandidat</small>
          </div>
        </div>

        <div class="col-4">
          <div class="border rounded p-2 text-center">
            <div class="fs-4 fw-semibold text-primary">
              {{ selectedCount }}
            </div>
            <small class="text-muted">Dipilih</small>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th style="width: 42px">Pilih</th>
              <th>Endpoint</th>
              <th style="width: 130px">Posisi</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="item in candidates" :key="candidateKey(item)" :class="{ 'table-primary': selected[candidateKey(item)] }">
              <td class="text-center">
                <input v-if="!item.exists && item.url" v-model="selected[candidateKey(item)]" class="form-check-input" type="checkbox" />

                <span v-else class="text-muted" title="Tidak tersedia untuk diimpor"> — </span>
              </td>

              <td>
                <div class="fw-semibold">
                  {{ item.title || "Tanpa judul" }}
                </div>

                <small class="text-muted text-break">
                  {{ item.url || "URL kosong" }}
                </small>

                <div class="mt-1">
                  <span v-if="item.exists" class="badge bg-secondary"> Sudah ada </span>

                  <span v-else-if="!item.url" class="badge bg-warning text-dark"> URL kosong </span>

                  <span v-else class="badge bg-success"> Baru </span>
                </div>

                <div v-if="selected[candidateKey(item)] && groups.length" class="mt-2">
                  <small class="fw-semibold d-block mb-1"> Group yang dapat mengakses </small>

                  <div class="d-flex flex-wrap gap-3">
                    <label v-for="group in groups" :key="group" class="form-check form-check-inline mb-0">
                      <input class="form-check-input" type="checkbox" :checked="selectedGroups[candidateKey(item)]?.includes(group) ?? false" @change="toggleGroup(candidateKey(item), group, ($event.target as HTMLInputElement).checked)" />
                      <span class="form-check-label">
                        {{ group }}
                      </span>
                    </label>
                  </div>
                </div>
              </td>

              <td>
                <select v-if="!item.exists && item.url" v-model="positions[candidateKey(item)]" class="form-select form-select-sm" :disabled="!selected[candidateKey(item)]">
                  <option value="sidebar">Sidebar</option>
                  <option value="topbar">Topbar</option>
                  <option value="down">Down</option>
                </select>

                <span v-else class="text-muted">—</span>
              </td>
            </tr>

            <tr v-if="candidates.length === 0">
              <td colspan="3" class="text-center text-muted py-4">Tidak ada kandidat Route Registry.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <div class="d-flex justify-content-between align-items-center border-top pt-3 mt-3">
      <small class="text-muted">
        Section: <strong>{{ store.section }}</strong>
      </small>

      <div class="d-flex gap-2">
        <button type="button" class="btn btn-light" :disabled="saving" @click="modal.close()">Tutup</button>

        <button type="button" class="btn btn-primary" :disabled="loading || saving || selectedCount === 0" @click="saveSelected">
          <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>

          {{ saving ? "Menyimpan..." : `Sinkronkan (${selectedCount})` }}
        </button>
      </div>
    </div>
  </div>
</template>
