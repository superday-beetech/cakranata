<script setup lang="ts">
import { computed, onMounted, ref } from "vue";

import { useNotificationStore } from "@/composables/useNotification.js";

import MetricCards from "../components/MetricCards.vue";
import RegisteredTable from "../components/RegisteredTable.vue";
import ScanTable from "../components/ScanTableNew.vue";

import { useRegisteredQuery } from "../composables/useRegisteredQuery";
import { useScanSelection } from "../composables/useScanSelection";
import { useShieldDialog } from "../composables/useShieldDialog";
import { useVerbsDialog } from "../composables/useVerbsDialog";

import { useRouteRegistryStore } from "../stores/routeRegistryStore";
import type { ApplyAction, ScanFilter, StatusField } from "../types/route-registry";

const store = useRouteRegistryStore();
const notification = useNotificationStore();

const registeredQuery = useRegisteredQuery();
const verbsDialog = useVerbsDialog();
const shieldDialog = useShieldDialog();

// -------- Tab --------
const activeTab = ref<"registered" | "scan">("registered");

// -------- Scan state --------
const scanFilter = ref<ScanFilter>("all");
const scanSearch = ref("");
const applyAction = ref<ApplyAction>("insert");

const scanSelection = useScanSelection(() => store.scanItems, scanFilter, scanSearch, applyAction);
// -------- Metrics --------
const discoveredCount = computed(() => store.scanItems.length);
const registeredCount = computed(() => store.registeredTotal);
const routableDisplay = computed(() => {
  const value = store.routableOnPage;
  return store.pagination.totalPages > 1 ? `${value}*` : value;
});

// -------- Handlers: registered --------
function onRegisteredToggleStatus(id: number, field: StatusField, value: boolean): Promise<boolean> {
  return store.updateStatus(id, field, value);
}

async function onEditVerbs(id: number, endpoint: string): Promise<void> {
  await verbsDialog.open(id, endpoint);
}

async function onCopyEndpoint(endpoint: string): Promise<void> {
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(endpoint);
    } else {
      const ta = document.createElement("textarea");
      ta.value = endpoint;
      ta.setAttribute("readonly", "");
      ta.style.position = "fixed";
      ta.style.left = "-9999px";
      document.body.appendChild(ta);
      ta.select();
      document.execCommand("copy");
      ta.remove();
    }
    notification.success("Endpoint berhasil disalin.");
  } catch {
    notification.warning("Gagal menyalin endpoint.");
  }
}

// -------- Handlers: scan --------
async function onRunScan(): Promise<void> {
  try {
    await store.runScan();

    notification.success(`Scan selesai. ${store.scanItems.length} endpoint ditemukan.`);
    activeTab.value = "scan";
  } catch {
    // sudah dinotifikasi store
  }
}

async function onApply(): Promise<void> {
  const eligible = scanSelection.eligibleForAction.value;
  if (eligible.length === 0) {
    notification.warning("Tidak ada endpoint terpilih yang sesuai dengan tindakan ini.");
    return;
  }

  const url_route: Record<string, string> = {};
  const permissions: Record<string, string> = {};

  for (const item of eligible) {
    if (item.url_route) url_route[item.endpoint] = item.url_route;
    if (item.permission) permissions[item.endpoint] = item.permission;
  }

  const actionLabel = applyAction.value === "insert" ? "menambahkan" : applyAction.value === "update" ? "memperbarui" : "menghapus";

  if (!window.confirm(`Yakin ${actionLabel} ${eligible.length} endpoint?`)) return;

  try {
    const result = await store.applyScan({
      action: applyAction.value,
      selected: eligible.map((i) => i.endpoint),
      url_route,
      permissions,
    });

    scanSelection.clear();

    const parts: string[] = [];
    if (typeof result.inserted === "number") parts.push(`Ditambahkan: ${result.inserted}`);
    if (typeof result.updated === "number") parts.push(`Diperbarui: ${result.updated}`);
    if (Array.isArray(result.deleted)) parts.push(`Dihapus: ${result.deleted.length}`);
    if (typeof result.skipped === "number") parts.push(`Dilewati: ${result.skipped}`);

    notification.success(`Perubahan registry berhasil diproses.${parts.length ? " " + parts.join(", ") : ""}`);
  } catch {
    // sudah dinotifikasi store
  }
}

async function onSyncShield(): Promise<void> {
  await shieldDialog.open();
}

// -------- Lifecycle --------
onMounted(async () => {
  try {
    await store.loadRegistered();
  } catch {
    // sudah dinotifikasi store
  }
});
</script>

<template>
  <div class="py-4">
    <!-- Header + metrics -->
    <div class="row align-items-center mb-4 g-3">
      <div class="col-12 col-lg-4">
        <h4 class="mb-1 fw-bold text-dark">Route Registry</h4>
        <p class="text-muted mb-0 small">Inventaris endpoint, konfigurasi HTTP verbs, dan status routing sistem.</p>
      </div>
      <div class="col-12 col-lg-8">
        <MetricCards :discovered="discoveredCount" :registered="registeredCount" :routable="routableDisplay" />
      </div>
    </div>

    <!-- Card -->
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-body-tertiary d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-3">
        <ul class="nav nav-pills gap-1 mb-0" role="tablist">
          <li class="nav-item" role="presentation">
            <button type="button" class="nav-link px-3 py-2" :class="{ active: activeTab === 'registered' }" @click="activeTab = 'registered'"><i class="ti ti-database me-1"></i> Registered Endpoints</button>
          </li>
          <li class="nav-item" role="presentation">
            <button type="button" class="nav-link px-3 py-2" :class="{ active: activeTab === 'scan' }" @click="activeTab = 'scan'"><i class="ti ti-eye me-1"></i> Scan Preview</button>
          </li>
        </ul>

        <div class="d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-primary btn-sm px-3 py-2" :disabled="store.loading.scan" @click="onRunScan">
            <i class="ti ti-refresh me-1"></i>
            {{ store.loading.scan ? "Memindai..." : "Scan Modul" }}
          </button>
          <button type="button" class="btn btn-warning btn-sm px-3 py-2" :disabled="store.loading.shield" @click="onSyncShield">
            <i class="ti ti-refresh me-1"></i>
            Sinkronkan Permission ke Shield
          </button>
        </div>
      </div>

      <div class="card-body p-4">
        <!-- Registered -->
        <div v-show="activeTab === 'registered'">
          <RegisteredTable
            :items="store.registeredItems"
            :loading="store.loading.registered"
            :pagination="store.pagination"
            :per-page="store.query.perPage"
            :search="store.query.search"
            :toggle-status="onRegisteredToggleStatus"
            @reload="registeredQuery.reload"
            @update:search="(v) => (registeredQuery.search.value = v)"
            @update:perPage="registeredQuery.setPerPage"
            @page="registeredQuery.goToPage"
            @edit-verbs="onEditVerbs"
            @copy="onCopyEndpoint"
          />
        </div>

        <!-- Scan -->
        <div v-show="activeTab === 'scan'">
          <ScanTable
            :items="scanSelection.visibleItems.value"
            :selected="scanSelection.selected.value"
            :all-visible-selected="scanSelection.allVisibleSelected.value"
            :filter="scanFilter"
            :search="scanSearch"
            :action="applyAction"
            :loading="store.loading.scan || store.loading.apply"
            :selected-count="scanSelection.selectedCount.value"
            :eligible-count="scanSelection.eligibleCount.value"
            :total-count="store.scanItems.length"
            :toggle-status="onRegisteredToggleStatus"
            @toggle="scanSelection.toggle"
            @select-all="scanSelection.setAllVisible"
            @select-all-click="scanSelection.selectAllVisible"
            @clear="scanSelection.clear"
            @apply="onApply"
            @update:filter="(v) => (scanFilter = v)"
            @update:search="(v) => (scanSearch = v)"
            @update:action="(v) => (applyAction = v)"
            @edit-verbs="onEditVerbs"
          />
        </div>
      </div>
    </div>
  </div>
</template>
