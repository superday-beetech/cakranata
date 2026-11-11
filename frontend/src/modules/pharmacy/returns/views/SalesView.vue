<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import api from "@/api/client";
import { notify } from "@/utils/notification";

interface SalesReturn {
  id: number;
  return_number: string;
  customer_id: number;
  delivery_id: number;
  return_date: string;
  status: string;
  reason: string | null;
  total_amount: number;
  total_items: number;
  total_quantity: number;
  restockable_quantity: number;
}

interface SalesReturnItem {
  id: number;
  delivery_item_id: number;
  batch_id: number;
  quantity: number;
  unit_price: number;
  subtotal: number;
  restockable: number | boolean;
  condition_notes: string | null;
}

interface SalesReturnDetail {
  return: SalesReturn;
  items: SalesReturnItem[];
}

const router = useRouter();

const returns = ref<SalesReturn[]>([]);
const selected = ref<SalesReturnDetail | null>(null);

const loading = ref(false);
const detailLoading = ref(false);
const postingId = ref<number | null>(null);

const formatCurrency = (value: number | string | null | undefined) => {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(Number(value ?? 0));
};

const formatDate = (value: string) => {
  if (!value) return "-";

  return new Date(value).toLocaleDateString("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
};

const statusLabel = (status: string) => {
  const labels: Record<string, string> = {
    draft: "Draft",
    posted: "Sudah Diposting",
    cancelled: "Dibatalkan",
  };

  return labels[status] ?? status;
};

const loadReturns = async () => {
  loading.value = true;

  try {
    const response = await api.get("/pharmacy/returns-api/list-sales");

    returns.value = response.data.data?.items ?? [];
  } catch (error: any) {
    notify.error(error.response?.data?.message ?? "Gagal memuat daftar sales return.", "error");
  } finally {
    loading.value = false;
  }
};

const showDetail = async (id: number) => {
  detailLoading.value = true;
  selected.value = null;

  try {
    const response = await api.get(`/pharmacy/returns-api/show-sales/${id}`);

    selected.value = response.data.data;
  } catch (error: any) {
    notify.error(error.response?.data?.message ?? "Gagal memuat detail sales return.", "error");
  } finally {
    detailLoading.value = false;
  }
};

const postReturn = async (id: number) => {
  if (!window.confirm("Posting sales return ini? Stok batch yang layak akan bertambah.")) {
    return;
  }

  postingId.value = id;

  try {
    const response = await api.post(`/pharmacy/returns-api/post-sales/${id}`);

    notify.success(response.data.message ?? "Sales return berhasil diposting.", "success");

    await loadReturns();
    await showDetail(id);
  } catch (error: any) {
    notify.error(error.response?.data?.message ?? "Gagal posting sales return.", "error");
  } finally {
    postingId.value = null;
  }
};

const createReturn = () => {
  router.push("/pharmacy/returns/sales/sales-create");
};

onMounted(loadReturns);
</script>

<template>
  <div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
      <div>
        <h4 class="mb-1">Sales Return</h4>
        <p class="text-muted mb-0">Pengelolaan retur barang dari pelanggan.</p>
      </div>

      <button type="button" class="btn btn-primary" @click="createReturn">
        <i class="ti ti-plus me-1"></i>
        Buat Sales Return
      </button>
    </div>

    <div class="row g-4">
      <div :class="selected ? 'col-xl-7' : 'col-12'">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar Retur Penjualan</h5>

            <button class="btn btn-sm btn-light" type="button" @click="loadReturns" :disabled="loading">
              <i class="ti ti-refresh me-1"></i>
              Refresh
            </button>
          </div>

          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>No. Retur</th>
                    <th>Tanggal</th>
                    <th>Delivery ID</th>
                    <th class="text-end">Item</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                    <th></th>
                  </tr>
                </thead>

                <tbody>
                  <tr v-if="loading">
                    <td colspan="7" class="text-center py-4">Memuat data...</td>
                  </tr>

                  <tr v-else-if="returns.length === 0">
                    <td colspan="7" class="text-center py-4 text-muted">Belum ada data sales return.</td>
                  </tr>

                  <tr v-for="item in returns" :key="item.id">
                    <td>
                      <strong>{{ item.return_number }}</strong>
                    </td>

                    <td>{{ formatDate(item.return_date) }}</td>

                    <td>{{ item.delivery_id }}</td>

                    <td class="text-end">
                      {{ item.total_items }}
                    </td>

                    <td class="text-end">
                      {{ formatCurrency(item.total_amount) }}
                    </td>

                    <td>
                      <span class="badge" :class="item.status === 'posted' ? 'bg-success' : item.status === 'draft' ? 'bg-warning text-dark' : 'bg-secondary'">
                        {{ statusLabel(item.status) }}
                      </span>
                    </td>

                    <td class="text-end">
                      <button class="btn btn-sm btn-outline-primary" type="button" @click="showDetail(item.id)">Detail</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div v-if="selected || detailLoading" class="col-xl-5">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Detail Sales Return</h5>

            <button class="btn-close" type="button" aria-label="Tutup detail" @click="selected = null"></button>
          </div>

          <div class="card-body">
            <div v-if="detailLoading" class="text-center py-4">Memuat detail...</div>

            <template v-else-if="selected">
              <div class="mb-3">
                <div class="text-muted small">Nomor Retur</div>
                <strong>{{ selected.return.return_number }}</strong>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-6">
                  <div class="text-muted small">Tanggal</div>
                  <strong>
                    {{ formatDate(selected.return.return_date) }}
                  </strong>
                </div>

                <div class="col-6">
                  <div class="text-muted small">Delivery ID</div>
                  <strong>{{ selected.return.delivery_id }}</strong>
                </div>

                <div class="col-6">
                  <div class="text-muted small">Customer ID</div>
                  <strong>{{ selected.return.customer_id }}</strong>
                </div>

                <div class="col-6">
                  <div class="text-muted small">Status</div>
                  <strong>
                    {{ statusLabel(selected.return.status) }}
                  </strong>
                </div>
              </div>

              <div class="mb-3">
                <div class="text-muted small">Alasan Retur</div>
                <div>{{ selected.return.reason || "-" }}</div>
              </div>

              <hr />

              <h6 class="mb-3">Item Retur</h6>

              <div v-for="item in selected.items" :key="item.id" class="border rounded p-3 mb-2">
                <div class="d-flex justify-content-between">
                  <strong> Delivery Item #{{ item.delivery_item_id }} </strong>

                  <span class="badge" :class="item.restockable ? 'bg-success' : 'bg-secondary'">
                    {{ item.restockable ? "Dapat Restock" : "Tidak Restock" }}
                  </span>
                </div>

                <div class="small text-muted mt-2">Batch ID: {{ item.batch_id }}</div>

                <div class="d-flex justify-content-between mt-2">
                  <span>Jumlah</span>
                  <strong>{{ item.quantity }}</strong>
                </div>

                <div class="d-flex justify-content-between">
                  <span>Harga Satuan</span>
                  <span>{{ formatCurrency(item.unit_price) }}</span>
                </div>

                <div class="d-flex justify-content-between">
                  <span>Subtotal</span>
                  <strong>{{ formatCurrency(item.subtotal) }}</strong>
                </div>

                <div v-if="item.condition_notes" class="small text-muted mt-2">Kondisi: {{ item.condition_notes }}</div>
              </div>

              <hr />

              <div class="d-flex justify-content-between mb-2">
                <span>Total Item</span>
                <strong>{{ selected.items.length }}</strong>
              </div>

              <div class="d-flex justify-content-between mb-3">
                <span>Total Retur</span>
                <strong>
                  {{ formatCurrency(selected.return.total_amount) }}
                </strong>
              </div>

              <button v-if="selected.return.status === 'draft'" type="button" class="btn btn-success w-100" :disabled="postingId === selected.return.id" @click="postReturn(selected.return.id)">
                <i class="ti ti-check me-1"></i>
                {{ postingId === selected.return.id ? "Memproses..." : "Posting Sales Return" }}
              </button>

              <div v-else class="alert alert-success mb-0">Sales return sudah diposting.</div>
            </template>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
