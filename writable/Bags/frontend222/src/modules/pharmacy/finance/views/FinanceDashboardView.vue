<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import { notify } from "@/utils/notification";
import financeService from "../services/financeService";

interface FinanceRow {
  id: number;
  number: string;
  date: string;
  amount: number;
  description: string;
  type: string;
  referenceType?: string;
}

interface DateFilter {
  date_from?: string;
  date_to?: string;
}

const loading = ref(false);

const filters = reactive({
  date_from: "",
  date_to: "",
});

const supplierPayments = ref<any[]>([]);
const customerPayments = ref<any[]>([]);
const expenses = ref<any[]>([]);
const cashTransactions = ref<any[]>([]);

function unwrapResponse(response: any): any {
  return response?.data?.data ?? response?.data ?? response;
}

function extractRows(response: any, keys: string[]): any[] {
  const body = unwrapResponse(response);

  if (Array.isArray(body)) return body;

  for (const key of keys) {
    if (Array.isArray(body?.[key])) return body[key];
  }

  return [];
}

function toNumber(value: unknown): number {
  const result = Number(value ?? 0);
  return Number.isFinite(result) ? result : 0;
}

function isCashIn(type: string): boolean {
  return ["in", "income", "cash_in", "masuk"].includes(String(type).toLowerCase());
}

function isCashOut(type: string): boolean {
  return ["out", "expense", "cash_out", "keluar"].includes(String(type).toLowerCase());
}

const totalSupplierPayments = computed(() => supplierPayments.value.reduce((sum, row) => sum + toNumber(row.amount), 0));

const totalCustomerPayments = computed(() => customerPayments.value.reduce((sum, row) => sum + toNumber(row.amount), 0));

const totalExpenses = computed(() => expenses.value.reduce((sum, row) => sum + toNumber(row.amount), 0));

const totalCashIn = computed(() => cashTransactions.value.filter((row) => isCashIn(row.transaction_type ?? row.type ?? row.direction)).reduce((sum, row) => sum + toNumber(row.amount), 0));

const totalCashOut = computed(() => cashTransactions.value.filter((row) => isCashOut(row.transaction_type ?? row.type ?? row.direction)).reduce((sum, row) => sum + toNumber(row.amount), 0));

const netCash = computed(() => totalCashIn.value - totalCashOut.value);

const recentTransactions = computed<FinanceRow[]>(() => {
  const rows: FinanceRow[] = cashTransactions.value.map((row) => {
    const type = row.transaction_type ?? row.type ?? row.direction ?? "";

    return {
      id: toNumber(row.id),
      number: row.transaction_number ?? row.cash_transaction_number ?? `#${row.id}`,
      date: row.transaction_date ?? "",
      amount: toNumber(row.amount),
      description: row.description ?? row.notes ?? row.reference_type ?? "Transaksi kas",
      type: isCashIn(type) ? "in" : "out",
      referenceType: row.reference_type ?? "",
    };
  });

  return rows.sort((a, b) => b.date.localeCompare(a.date)).slice(0, 10);
});

function formatCurrency(value: number): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(value);
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

function formatReference(value?: string): string {
  if (!value) return "-";

  const labels: Record<string, string> = {
    supplier_payment: "Pembayaran Supplier",
    customer_payment: "Pembayaran Pelanggan",
    expense: "Pengeluaran",
  };

  return labels[value] ?? value;
}

async function loadDashboard(): Promise<void> {
  loading.value = true;

  const params: DateFilter = {};

  if (filters.date_from) params.date_from = filters.date_from;
  if (filters.date_to) params.date_to = filters.date_to;

  try {
    const results = await Promise.allSettled([financeService.getSupplierPayments(params), financeService.getCustomerPayments(params), financeService.getExpenses(params), financeService.getCashTransactions(params)]);

    const errors: string[] = [];

    if (results[0].status === "fulfilled") {
      supplierPayments.value = extractRows(results[0].value, ["items", "payments"]);
    } else {
      supplierPayments.value = [];
      errors.push("pembayaran supplier");
      console.error(results[0].reason);
    }

    if (results[1].status === "fulfilled") {
      customerPayments.value = extractRows(results[1].value, ["items", "payments"]);
    } else {
      customerPayments.value = [];
      errors.push("pembayaran pelanggan");
      console.error(results[1].reason);
    }

    if (results[2].status === "fulfilled") {
      expenses.value = extractRows(results[2].value, ["items", "expenses"]);
    } else {
      expenses.value = [];
      errors.push("pengeluaran");
      console.error(results[2].reason);
    }

    if (results[3].status === "fulfilled") {
      cashTransactions.value = extractRows(results[3].value, ["items", "transactions"]);
    } else {
      cashTransactions.value = [];
      errors.push("transaksi kas");
      console.error(results[3].reason);
    }

    if (errors.length > 0) {
      notify.warning(`Sebagian data Finance gagal dimuat: ${errors.join(", ")}.`);
    }
  } catch (error) {
    console.error("Gagal memuat Finance Dashboard:", error);
    notify.error("Gagal memuat ringkasan Finance.");
  } finally {
    loading.value = false;
  }
}

function resetFilters(): void {
  filters.date_from = "";
  filters.date_to = "";
  loadDashboard();
}

onMounted(() => {
  loadDashboard();
});
</script>

<template>
  <div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
      <div>
        <h4 class="mb-1">Finance Dashboard</h4>
        <p class="text-muted mb-0">Ringkasan pembayaran, pengeluaran, dan arus kas apotek.</p>
      </div>

      <button type="button" class="btn btn-light" :disabled="loading" @click="loadDashboard">
        <i class="ti ti-refresh me-1"></i>
        Refresh
      </button>
    </div>

    <!-- Date filter -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Dari Tanggal</label>
            <input v-model="filters.date_from" type="date" class="form-control" />
          </div>

          <div class="col-md-4">
            <label class="form-label">Sampai Tanggal</label>
            <input v-model="filters.date_to" type="date" class="form-control" :min="filters.date_from || undefined" />
          </div>

          <div class="col-md-2">
            <button type="button" class="btn btn-primary w-100" :disabled="loading" @click="loadDashboard">Terapkan</button>
          </div>

          <div class="col-md-2">
            <button type="button" class="btn btn-light w-100" :disabled="loading" @click="resetFilters">Reset</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment summary -->
    <h5 class="mb-3">Ringkasan Pembayaran</h5>

    <div class="row g-3 mb-4">
      <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <span class="text-muted">Pembayaran Supplier</span>
              <i class="ti ti-truck-delivery fs-5 text-danger"></i>
            </div>
            <h4 class="mb-2">
              {{ formatCurrency(totalSupplierPayments) }}
            </h4>
            <small class="text-muted"> {{ supplierPayments.length }} transaksi </small>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <span class="text-muted">Pembayaran Pelanggan</span>
              <i class="ti ti-users fs-5 text-success"></i>
            </div>
            <h4 class="mb-2">
              {{ formatCurrency(totalCustomerPayments) }}
            </h4>
            <small class="text-muted"> {{ customerPayments.length }} transaksi </small>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <span class="text-muted">Pengeluaran Operasional</span>
              <i class="ti ti-receipt fs-5 text-warning"></i>
            </div>
            <h4 class="mb-2">
              {{ formatCurrency(totalExpenses) }}
            </h4>
            <small class="text-muted"> {{ expenses.length }} transaksi </small>
          </div>
        </div>
      </div>
    </div>

    <!-- Cash summary -->
    <h5 class="mb-3">Ringkasan Arus Kas</h5>

    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted mb-2">Kas Masuk</div>
            <h4 class="text-success mb-0">
              {{ formatCurrency(totalCashIn) }}
            </h4>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted mb-2">Kas Keluar</div>
            <h4 class="text-danger mb-0">
              {{ formatCurrency(totalCashOut) }}
            </h4>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted mb-2">Selisih Kas Periode</div>
            <h4 class="mb-0" :class="netCash >= 0 ? 'text-success' : 'text-danger'">
              {{ formatCurrency(netCash) }}
            </h4>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent transactions -->
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
          <div>
            <h5 class="mb-1">Transaksi Kas Terbaru</h5>
            <small class="text-muted"> Maksimal 10 transaksi terbaru dari data yang dimuat. </small>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Nomor Transaksi</th>
                <th>Referensi</th>
                <th>Deskripsi</th>
                <th>Jenis</th>
                <th class="text-end">Jumlah</th>
              </tr>
            </thead>

            <tbody>
              <tr v-if="loading">
                <td colspan="6" class="text-center py-4">Memuat ringkasan Finance...</td>
              </tr>

              <tr v-else-if="recentTransactions.length === 0">
                <td colspan="6" class="text-center text-muted py-4">Belum ada transaksi kas.</td>
              </tr>

              <tr v-for="item in recentTransactions" :key="item.id">
                <td>{{ formatDate(item.date) }}</td>
                <td class="fw-semibold">{{ item.number }}</td>
                <td>{{ formatReference(item.referenceType) }}</td>
                <td>{{ item.description }}</td>
                <td>
                  <span class="badge" :class="item.type === 'in' ? 'bg-light-success text-success' : 'bg-light-danger text-danger'">
                    {{ item.type === "in" ? "Kas Masuk" : "Kas Keluar" }}
                  </span>
                </td>
                <td class="text-end fw-semibold" :class="item.type === 'in' ? 'text-success' : 'text-danger'">
                  {{ item.type === "in" ? "+" : "-" }}
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
