<!--PrintTemplateList--->
<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { listPrintTemplates, deletePrintTemplate, duplicatePrintTemplate, extractErrorMessage } from "../services/printTemplates";
import type { PrintTemplateSummary } from "../types/printTemplate";

const router = useRouter();

const loading = ref(false);
const errorMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);

const items = ref<PrintTemplateSummary[]>([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(15);
const search = ref("");
const typeFilter = ref<string>("");

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)));

async function fetchList() {
  loading.value = true;
  errorMessage.value = null;
  try {
    const res = await listPrintTemplates({
      page: page.value,
      per_page: perPage.value,
      search: search.value || undefined,
      type: typeFilter.value || undefined,
    });
    items.value = res.data;
    total.value = res.total;
  } catch (err) {
    errorMessage.value = extractErrorMessage(err);
  } finally {
    loading.value = false;
  }
}

function goCreate() {
  router.push({ path: "/settings/print-template-page", query: { mode: "create" } });
}
function goEdit(id: number) {
  router.push({
    path: "/settings/print-template-page",
    query: { mode: "edit", id: String(id) },
  });
}
function goShow(id: number) {
  router.push({ path: "/settings/print-template-page", query: { mode: "show", id: String(id) } });
}

async function onDuplicate(id: number) {
  if (!confirm("Duplikat template ini?")) return;
  try {
    const res = await duplicatePrintTemplate(id);
    successMessage.value = `Template "${res.name}" berhasil diduplikat.`;
    setTimeout(() => (successMessage.value = null), 2500);
    await fetchList();
  } catch (err) {
    errorMessage.value = extractErrorMessage(err);
  }
}

async function onDelete(id: number, name: string) {
  if (!confirm(`Hapus template "${name}"? Tindakan ini tidak bisa dibatalkan.`)) return;
  try {
    await deletePrintTemplate(id);
    successMessage.value = "Template berhasil dihapus.";
    setTimeout(() => (successMessage.value = null), 2500);
    await fetchList();
  } catch (err) {
    errorMessage.value = extractErrorMessage(err);
  }
}

function onPage(p: number) {
  if (p < 1 || p > totalPages.value) return;
  page.value = p;
  fetchList();
}

function onSearch() {
  page.value = 1;
  fetchList();
}

function formatDate(s: string) {
  if (!s) return "-";
  return new Date(s).toLocaleString("id-ID", {
    dateStyle: "medium",
    timeStyle: "short",
  });
}

onMounted(fetchList);
</script>

<template>
  <div class="container-fluid">
    <!-- Header -->
    <div class="row">
      <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
          <h4 class="mb-0">Template Cetak</h4>
          <ol class="breadcrumb m-0">
            <li class="breadcrumb-item"><a href="#">Farmasi</a></li>
            <li class="breadcrumb-item active">Template Cetak</li>
          </ol>
        </div>
      </div>
    </div>

    <!-- Alerts -->
    <div v-if="errorMessage" class="alert alert-danger alert-dismissible fade show">
      <i class="ti ti-alert-circle me-1"></i> {{ errorMessage }}
      <button type="button" class="btn-close" @click="errorMessage = null"></button>
    </div>
    <div v-if="successMessage" class="alert alert-success alert-dismissible fade show">
      <i class="ti ti-circle-check me-1"></i> {{ successMessage }}
      <button type="button" class="btn-close" @click="successMessage = null"></button>
    </div>

    <!-- Toolbar -->
    <div class="card">
      <div class="card-body py-3">
        <div class="row g-2 align-items-end">
          <div class="col-md-5">
            <label class="form-label small mb-1">Cari</label>
            <input v-model="search" type="text" class="form-control" placeholder="Nama template..." @keyup.enter="onSearch" />
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">Tipe</label>
            <select v-model="typeFilter" class="form-select" @change="onSearch">
              <option value="">Semua</option>
              <option value="kwitansi">Kwitansi</option>
              <option value="struk">Struk</option>
              <option value="penerimaan_barang">Penerimaan Barang</option>
            </select>
          </div>
          <div class="col-md-2">
            <button class="btn btn-outline-secondary w-100" @click="onSearch"><i class="ti ti-search me-1"></i> Cari</button>
          </div>
          <div class="col-md-2">
            <button class="btn btn-primary w-100" @click="goCreate"><i class="ti ti-plus me-1"></i> Buat Baru</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="card">
      <div class="card-body p-0">
        <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-primary"></div>
          <p class="text-muted mt-2 mb-0">Memuat...</p>
        </div>

        <div v-else-if="items.length === 0" class="text-center text-muted py-5">
          <i class="ti ti-template-off fs-1 d-block mb-2"></i>
          Belum ada template.
          <div class="mt-2">
            <button class="btn btn-sm btn-primary" @click="goCreate"><i class="ti ti-plus me-1"></i> Buat Template Pertama</button>
          </div>
        </div>

        <div v-else class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th style="width: 60px">#</th>
                <th>Nama</th>
                <th style="width: 140px">Tipe</th>
                <th style="width: 140px">Ukuran</th>
                <th style="width: 180px">Diperbarui</th>
                <th style="width: 200px" class="text-end">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(it, idx) in items" :key="it.id">
                <td>{{ (page - 1) * perPage + idx + 1 }}</td>
                <td>
                  <div class="fw-semibold">{{ it.name }}</div>
                  <small class="text-muted">ID: {{ it.id }}</small>
                </td>
                <td>
                  <span class="badge bg-light text-dark">{{ it.type }}</span>
                </td>
                <td>
                  <span class="badge bg-light text-dark">{{ it.paper_type }}</span>
                </td>
                <td>
                  <small>{{ formatDate(it.updated_at) }}</small>
                </td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" title="Edit" @click="goEdit(it.id)">
                      <i class="ti ti-pencil"></i>
                    </button>
                    <button class="btn btn-outline-secondary" title="Duplikat" @click="onDuplicate(it.id)">
                      <i class="ti ti-copy"></i>
                    </button>
                    <button class="btn btn-outline-danger" title="Hapus" @click="onDelete(it.id, it.name)">
                      <i class="ti ti-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="total > 0" class="card-footer d-flex align-items-center justify-content-between">
        <small class="text-muted"> Menampilkan {{ (page - 1) * perPage + 1 }}–{{ Math.min(page * perPage, total) }} dari {{ total }} </small>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item" :class="{ disabled: page <= 1 }">
              <button class="page-link" @click="onPage(page - 1)">
                <i class="ti ti-chevron-left"></i>
              </button>
            </li>
            <li v-for="p in totalPages" :key="p" class="page-item" :class="{ active: p === page }">
              <button class="page-link" @click="onPage(p)">{{ p }}</button>
            </li>
            <li class="page-item" :class="{ disabled: page >= totalPages }">
              <button class="page-link" @click="onPage(page + 1)">
                <i class="ti ti-chevron-right"></i>
              </button>
            </li>
          </ul>
        </nav>
      </div>
    </div>
  </div>
</template>
