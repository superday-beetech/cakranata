<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import { notify } from "@/utils/notification";
import financeService from "../services/financeService";

interface CashTransaction {
  id: number;
  transaction_number?: string;
  transaction_date: string;
  transaction_type: string;
  amount: number;
  reference_type?: string | null;
  reference_id?: number | null;
  description?: string | null;
  notes?: string | null;
}

const transactions = ref<CashTransaction[]>([]);
const loading = ref(false);
const search = ref("");

const filters = reactive({
  date_from: "",
  date_to: "",
});

function unwrapResponse(response: any): any {
  return response?.data?.data ?? response?.data ?? response;
}

function normalizeTransaction(row: any): CashTransaction {
  return {
    id: Number(row.id),
    transaction_number: row.transaction_number ?? row.cash_transaction_number ?? "",
    transaction_date: row.transaction_date ?? "",
    transaction_type: row.transaction_type ?? row.type ?? row.direction ?? "",
    amount: Number(row.amount ?? 0),
    reference_type: row.reference_type ?? null,
    reference_id: row.reference_id ? Number(row.reference_id) : null,
    description: row.description ?? null,
    notes: row.notes ?? null,
  };
}

const filteredTransactions = computed(() => {
  const keyword = search.value.trim().toLowerCase();

  if (!keyword) return transactions.value;

  return transactions.value.filter((item) =>
    [item.transaction_number, item.transaction_type, item.reference_type, item.reference_id, item.description, item.notes].some((value) =>
      String(value ?? "")
        .toLowerCase()
        .includes(keyword),
    ),
  );
});

function isCashIn(type: string): boolean {
  return ["in", "income", "cash_in", "masuk"].includes(String(type).toLowerCase());
}

const totalCashIn = computed(() => filteredTransactions.value.filter((item) => isCashIn(item.transaction_type)).reduce((total, item) => total + item.amount, 0));

const totalCashOut = computed(() => filteredTransactions.value.filter((item) => !isCashIn(item.transaction_type)).reduce((total, item) => total + item.amount, 0));

const netCash = computed(() => totalCashIn.value - totalCashOut.value);

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

  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  }).format(date);
}

function formatType(type: string): string {
  return isCashIn(type) ? "Kas Masuk" : "Kas Keluar";
}

function formatReference(value?: string | null): string {
  if (!value) return "-";

  const labels: Record<string, string> = {
    supplier_payment: "Pembayaran Supplier",
    customer_payment: "Pembayaran Pelanggan",
    expense: "Pengeluaran",
  };

  return labels[value] ?? value;
}

async function loadTransactions(): Promise<void> {
  loading.value = true;

  try {
    const params: {
      date_from?: string;
      date_to?: string;
    } = {};

    if (filters.date_from) params.date_from = filters.date_from;
    if (filters.date_to) params.date_to = filters.date_to;

    const response = await financeService.getCashTransactions(params);
    const body = unwrapResponse(response);

    const rows = Array.isArray(body) ? body : (body?.items ?? body?.transactions ?? []);

    transactions.value = rows.map(normalizeTransaction);
  } catch (error) {
    console.error("Gagal memuat transaksi kas:", error);
    notify.error("Gagal memuat data transaksi kas.");
  } finally {
    loading.value = false;
  }
}

function resetFilters(): void {
  filters.date_from = "";
  filters.date_to = "";
  search.value = "";
  loadTransactions();
}

onMounted(() => {
  loadTransactions();
});
</script>

<template>
  <div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
      <div>
        <h4 class="mb-1">Arus Kas</h4>
        <p class="text-muted mb-0">Riwayat seluruh transaksi kas masuk dan kas keluar.</p>
      </div>

      <button type="button" class="btn btn-light" :disabled="loading" @click="loadTransactions">
        <i class="ti ti-refresh me-1"></i>
        Refresh
      </button>
    </div>

    <!-- Summary -->
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted mb-2">Total Kas Masuk</div>
            <h4 class="text-success mb-0">
              {{ formatCurrency(totalCashIn) }}
            </h4>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted mb-2">Total Kas Keluar</div>
            <h4 class="text-danger mb-0">
              {{ formatCurrency(totalCashOut) }}
            </h4>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted mb-2">Saldo Bersih Periode</div>
            <h4 class="mb-0" :class="netCash >= 0 ? 'text-success' : 'text-danger'">
              {{ formatCurrency(netCash) }}
            </h4>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-3">
            <label class="form-label">Dari Tanggal</label>
            <input v-model="filters.date_from" type="date" class="form-control" />
          </div>

          <div class="col-md-3">
            <label class="form-label">Sampai Tanggal</label>
            <input v-model="filters.date_to" type="date" class="form-control" :min="filters.date_from || undefined" />
          </div>

          <div class="col-md-3">
            <button type="button" class="btn btn-primary w-100" :disabled="loading" @click="loadTransactions">Terapkan Filter</button>
          </div>

          <div class="col-md-3">
            <button type="button" class="btn btn-light w-100" :disabled="loading" @click="resetFilters">Reset Filter</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Transaction table -->
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="row align-items-center mb-3">
          <div class="col-md-6">
            <h5 class="mb-0">Riwayat Transaksi Kas</h5>
            <small class="text-muted"> {{ filteredTransactions.length }} transaksi ditampilkan </small>
          </div>

          <div class="col-md-6 mt-3 mt-md-0">
            <input v-model="search" type="search" class="form-control" placeholder="Cari nomor, referensi, atau deskripsi..." />
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Nomor Transaksi</th>
                <th>Jenis</th>
                <th>Referensi</th>
                <th>Deskripsi</th>
                <th class="text-end">Jumlah</th>
              </tr>
            </thead>

            <tbody>
              <tr v-if="loading">
                <td colspan="6" class="text-center py-4">Memuat transaksi kas...</td>
              </tr>

              <tr v-else-if="filteredTransactions.length === 0">
                <td colspan="6" class="text-center text-muted py-4">Tidak ada transaksi kas.</td>
              </tr>

              <tr v-for="item in filteredTransactions" :key="item.id">
                <td>{{ formatDate(item.transaction_date) }}</td>

                <td class="fw-semibold">
                  {{ item.transaction_number || `#${item.id}` }}
                </td>

                <td>
                  <span class="badge" :class="isCashIn(item.transaction_type) ? 'bg-light-success text-success' : 'bg-light-danger text-danger'">
                    {{ formatType(item.transaction_type) }}
                  </span>
                </td>

                <td>
                  <div>{{ formatReference(item.reference_type) }}</div>
                  <small v-if="item.reference_id" class="text-muted"> ID: {{ item.reference_id }} </small>
                </td>

                <td>
                  {{ item.description || item.notes || "-" }}
                </td>

                <td class="text-end fw-semibold" :class="isCashIn(item.transaction_type) ? 'text-success' : 'text-danger'">
                  {{ isCashIn(item.transaction_type) ? "+" : "-" }}
                  {{ formatCurrency(item.amount) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
