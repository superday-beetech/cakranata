<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import api from "@/api/client";

interface PurchaseReturn {
  id: number;
  return_number: string;
  supplier_id: number;
  supplier_name: string;
  goods_receipt_id: number;
  receipt_number: string;
  return_date: string;
  status: string;
  reason: string | null;
  total_amount: number;
  notes: string | null;
  created_at?: string;
  items?: PurchaseReturnItem[];
}

interface PurchaseReturnItem {
  id: number;
  product_name: string;
  sku?: string;
  batch_number: string;
  quantity: number;
  unit_cost: number;
  subtotal: number;
  notes?: string;
}

const returns = ref<PurchaseReturn[]>([]);
const selectedReturn = ref<PurchaseReturn | null>(null);

const loading = ref(false);
const loadingDetail = ref(false);
const posting = ref(false);

const errorMessage = ref("");
const successMessage = ref("");

const search = ref("");
const statusFilter = ref("");

const filteredReturns = computed(() => {
  const keyword = search.value.toLowerCase().trim();

  return returns.value.filter((item) => {
    const matchesSearch = !keyword || item.return_number?.toLowerCase().includes(keyword) || item.supplier_name?.toLowerCase().includes(keyword) || item.receipt_number?.toLowerCase().includes(keyword);

    const matchesStatus = !statusFilter.value || item.status === statusFilter.value;

    return matchesSearch && matchesStatus;
  });
});

function getResponseData(response: any): any {
  return response?.data?.data;
}

function formatCurrency(value: number): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(Number(value || 0));
}

function formatDate(value: string): string {
  if (!value) return "-";

  const date = new Date(`${value.slice(0, 10)}T00:00:00`);

  return new Intl.DateTimeFormat("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  }).format(date);
}

function statusLabel(status: string): string {
  const labels: Record<string, string> = {
    draft: "Draft",
    posted: "Sudah Diposting",
    cancelled: "Dibatalkan",
  };

  return labels[status] || status;
}

function statusClass(status: string): string {
  const classes: Record<string, string> = {
    draft: "bg-warning text-dark",
    posted: "bg-success",
    cancelled: "bg-danger",
  };

  return classes[status] || "bg-secondary";
}

async function loadReturns() {
  loading.value = true;
  errorMessage.value = "";

  try {
    const response = await api.get("/pharmacy/returns-api/list-purchase");

    const data = getResponseData(response);

    returns.value = Array.isArray(data) ? data : [];
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || "Gagal mengambil daftar Purchase Return.";
  } finally {
    loading.value = false;
  }
}

async function showDetail(id: number) {
  selectedReturn.value = null;
  loadingDetail.value = true;
  errorMessage.value = "";

  try {
    const response = await api.get(`/pharmacy/returns-api/show-purchase/${id}`);

    selectedReturn.value = getResponseData(response);
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || "Gagal mengambil detail Purchase Return.";
  } finally {
    loadingDetail.value = false;
  }
}

async function postReturn(item: PurchaseReturn) {
  if (item.status !== "draft") {
    return;
  }

  const confirmed = window.confirm(`Posting Purchase Return ${item.return_number}?\n\n` + "Stok batch akan dikurangi dan transaksi tidak dapat diposting ulang.");

  if (!confirmed) return;

  posting.value = true;
  errorMessage.value = "";
  successMessage.value = "";

  try {
    const response = await api.post(`/pharmacy/returns-api/post-purchase/${item.id}`);

    successMessage.value = response.data?.message || "Purchase Return berhasil diposting.";

    await loadReturns();

    if (selectedReturn.value?.id === item.id) {
      await showDetail(item.id);
    }
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || "Gagal memposting Purchase Return.";
  } finally {
    posting.value = false;
  }
}

onMounted(loadReturns);
</script>

<template>
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">Purchase Return</h4>
        <p class="text-muted mb-0">Pengelolaan retur pembelian kepada supplier.</p>
      </div>

      <button type="button" class="btn btn-primary" @click="$router.push('/pharmacy/returns/purchase/purchase-create')">
        <i class="ti ti-plus me-1"></i>
        Buat Purchase Return
      </button>
    </div>

    <div v-if="errorMessage" class="alert alert-danger alert-dismissible" role="alert">
      {{ errorMessage }}
      <button type="button" class="btn-close" @click="errorMessage = ''"></button>
    </div>

    <div v-if="successMessage" class="alert alert-success alert-dismissible" role="alert">
      {{ successMessage }}
      <button type="button" class="btn-close" @click="successMessage = ''"></button>
    </div>

    <div class="card mb-4">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Pencarian</label>
            <input v-model="search" type="search" class="form-control" placeholder="Nomor retur, supplier, atau receipt..." />
          </div>

          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select v-model="statusFilter" class="form-select">
              <option value="">Semua Status</option>
              <option value="draft">Draft</option>
              <option value="posted">Sudah Diposting</option>
              <option value="cancelled">Dibatalkan</option>
            </select>
          </div>

          <div class="col-md-3 d-flex align-items-end">
            <button type="button" class="btn btn-outline-secondary w-100" :disabled="loading" @click="loadReturns">
              <i class="ti ti-refresh me-1"></i>
              Muat Ulang
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Purchase Return</h5>

        <span class="badge bg-primary"> {{ filteredReturns.length }} Transaksi </span>
      </div>

      <div class="card-body">
        <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-primary"></div>
          <p class="text-muted mt-2 mb-0">Memuat data Purchase Return...</p>
        </div>

        <div v-else-if="filteredReturns.length === 0" class="text-center text-muted py-5">
          <i class="ti ti-file-off fs-7"></i>
          <p class="mt-2 mb-0">Belum ada Purchase Return.</p>
        </div>

        <div v-else class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Nomor Retur</th>
                <th>Tanggal</th>
                <th>Supplier</th>
                <th>Goods Receipt</th>
                <th class="text-end">Total</th>
                <th>Status</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="item in filteredReturns" :key="item.id">
                <td>
                  <strong>{{ item.return_number }}</strong>
                </td>

                <td>{{ formatDate(item.return_date) }}</td>

                <td>{{ item.supplier_name || "-" }}</td>

                <td>{{ item.receipt_number || "-" }}</td>

                <td class="text-end">
                  {{ formatCurrency(item.total_amount) }}
                </td>

                <td>
                  <span class="badge" :class="statusClass(item.status)">
                    {{ statusLabel(item.status) }}
                  </span>
                </td>

                <td class="text-center">
                  <div class="d-flex justify-content-center gap-1">
                    <button type="button" class="btn btn-sm btn-outline-primary" title="Detail" @click="showDetail(item.id)">
                      <i class="ti ti-eye"></i>
                    </button>

                    <button v-if="item.status === 'draft'" type="button" class="btn btn-sm btn-success" title="Posting" :disabled="posting" @click="postReturn(item)">
                      <i class="ti ti-check"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-if="loadingDetail" class="card mt-4">
      <div class="card-body text-center py-4">
        <div class="spinner-border text-primary"></div>
        <p class="text-muted mt-2 mb-0">Memuat detail retur...</p>
      </div>
    </div>

    <div v-if="selectedReturn" class="card mt-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <h5 class="mb-1">Detail {{ selectedReturn.return_number }}</h5>
          <small class="text-muted"> Purchase Return #{{ selectedReturn.id }} </small>
        </div>

        <button type="button" class="btn btn-sm btn-outline-secondary" @click="selectedReturn = null">
          <i class="ti ti-x me-1"></i>
          Tutup
        </button>
      </div>

      <div class="card-body">
        <div class="row g-3 mb-4">
          <div class="col-md-3">
            <small class="text-muted d-block">Supplier</small>
            <strong>{{ selectedReturn.supplier_name || "-" }}</strong>
          </div>

          <div class="col-md-3">
            <small class="text-muted d-block">Goods Receipt</small>
            <strong>{{ selectedReturn.receipt_number || "-" }}</strong>
          </div>

          <div class="col-md-3">
            <small class="text-muted d-block">Tanggal Retur</small>
            <strong>{{ formatDate(selectedReturn.return_date) }}</strong>
          </div>

          <div class="col-md-3">
            <small class="text-muted d-block">Status</small>
            <span class="badge" :class="statusClass(selectedReturn.status)">
              {{ statusLabel(selectedReturn.status) }}
            </span>
          </div>

          <div class="col-md-6">
            <small class="text-muted d-block">Alasan</small>
            {{ selectedReturn.reason || "-" }}
          </div>

          <div class="col-md-6">
            <small class="text-muted d-block">Catatan</small>
            {{ selectedReturn.notes || "-" }}
          </div>
        </div>

        <h6 class="mb-3">Item Retur</h6>

        <div class="table-responsive">
          <table class="table table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th>Produk</th>
                <th>Batch</th>
                <th class="text-end">Kuantitas</th>
                <th class="text-end">Harga Satuan</th>
                <th class="text-end">Subtotal</th>
                <th>Catatan</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="item in selectedReturn.items || []" :key="item.id">
                <td>
                  <strong>{{ item.product_name || "-" }}</strong>
                  <small v-if="item.sku" class="d-block text-muted"> SKU: {{ item.sku }} </small>
                </td>

                <td>{{ item.batch_number || "-" }}</td>

                <td class="text-end">{{ item.quantity }}</td>

                <td class="text-end">
                  {{ formatCurrency(item.unit_cost) }}
                </td>

                <td class="text-end">
                  {{ formatCurrency(item.subtotal) }}
                </td>

                <td>{{ item.notes || "-" }}</td>
              </tr>

              <tr v-if="!selectedReturn.items?.length">
                <td colspan="6" class="text-center text-muted">Tidak ada item retur.</td>
              </tr>
            </tbody>

            <tfoot>
              <tr>
                <th colspan="4" class="text-end">Total Retur</th>
                <th class="text-end">
                  {{ formatCurrency(selectedReturn.total_amount) }}
                </th>
                <th></th>
              </tr>
            </tfoot>
          </table>
        </div>

        <div v-if="selectedReturn.status === 'draft'" class="text-end mt-3">
          <button type="button" class="btn btn-success" :disabled="posting" @click="postReturn(selectedReturn)">
            <i class="ti ti-check me-1"></i>
            Posting Purchase Return
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
