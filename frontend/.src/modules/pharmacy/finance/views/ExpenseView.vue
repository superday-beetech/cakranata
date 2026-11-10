<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { notify } from "@/utils/notification";
import { useModalStore } from "@/composables/modal";

import financeService, { type ExpensePayload } from "../services/financeService";

import ExpenseFormModal from "./ExpenseFormModal.vue";

interface ExpenseRow {
  id: number;
  expense_number: string;
  expense_date: string;
  category: string;
  description: string;
  amount: number;
  payment_method: string;
  reference_number?: string | null;
  notes?: string | null;
}

const modalStore = useModalStore();

const expenses = ref<ExpenseRow[]>([]);
const loading = ref(false);
const search = ref("");

const categoryLabels: Record<string, string> = {
  transportation: "Transportasi",
  utilities: "Listrik dan Air",
  rent: "Sewa",
  salary: "Gaji dan Upah",
  supplies: "Perlengkapan",
  administration: "Administrasi",
  operational: "Operasional",
};

const filteredExpenses = computed(() => {
  const keyword = search.value.trim().toLowerCase();

  if (!keyword) return expenses.value;

  return expenses.value.filter((expense) => [expense.expense_number, expense.category, expense.description, expense.payment_method, expense.reference_number ?? ""].some((value) => String(value).toLowerCase().includes(keyword)));
});

const totalExpense = computed(() => filteredExpenses.value.reduce((total, expense) => total + Number(expense.amount || 0), 0));

function unwrapResponse(response: any): any {
  return response?.data?.data ?? response?.data ?? response;
}

function generateExpenseNumber(): string {
  const now = new Date();

  const date = [now.getFullYear(), String(now.getMonth() + 1).padStart(2, "0"), String(now.getDate()).padStart(2, "0")].join("");

  const time = [String(now.getHours()).padStart(2, "0"), String(now.getMinutes()).padStart(2, "0"), String(now.getSeconds()).padStart(2, "0")].join("");

  return `EXP-${date}-${time}`;
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

function formatCategory(value: string): string {
  return categoryLabels[value] ?? value ?? "-";
}

function formatPaymentMethod(value: string): string {
  const labels: Record<string, string> = {
    cash: "Tunai",
    transfer: "Transfer",
    debit: "Debit",
    credit: "Kredit",
  };

  return labels[value] ?? value ?? "-";
}

async function loadExpenses(): Promise<void> {
  loading.value = true;

  try {
    const response = await financeService.getExpenses();
    const body = unwrapResponse(response);

    const rows = Array.isArray(body) ? body : (body?.items ?? body?.expenses ?? []);

    expenses.value = rows;
  } catch (error) {
    console.error("Gagal memuat pengeluaran:", error);
    notify.error("Gagal memuat daftar pengeluaran.");
  } finally {
    loading.value = false;
  }
}

async function saveExpense(payload: ExpensePayload): Promise<void> {
  try {
    await financeService.createExpense(payload);

    notify.success("Pengeluaran berhasil disimpan.");
    await loadExpenses();
  } catch (error: any) {
    console.error("Gagal menyimpan pengeluaran:", error);

    const message = error?.response?.data?.messages?.error ?? error?.response?.data?.message ?? "Gagal menyimpan pengeluaran.";

    notify.error(message);
  }
}

function openCreateModal(): void {
  modalStore.open({
    title: "Tambah Pengeluaran",
    component: ExpenseFormModal,
    size: "lg",
    centered: true,
    props: {
      expenseNumber: generateExpenseNumber(),
    },
    onSelect: async (value: unknown) => {
      if (typeof value !== "object" || value === null || !("expense_number" in value) || !("expense_date" in value)) {
        return;
      }

      const payload = value as ExpensePayload;

      modalStore.close();
      await saveExpense(payload);
    },
  });
}

onMounted(() => {
  loadExpenses();
});
</script>

<template>
  <div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
      <div>
        <h4 class="mb-1">Pengeluaran Operasional</h4>
        <p class="text-muted mb-0">Kelola pencatatan biaya dan pengeluaran operasional apotek.</p>
      </div>

      <button type="button" class="btn btn-primary" @click="openCreateModal">
        <i class="ti ti-plus me-1"></i>
        Tambah Pengeluaran
      </button>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted mb-2">Total Pengeluaran</div>
            <h4 class="mb-0">{{ formatCurrency(totalExpense) }}</h4>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted mb-2">Jumlah Transaksi</div>
            <h4 class="mb-0">{{ filteredExpenses.length }}</h4>
          </div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="row align-items-center mb-3">
          <div class="col-md-6">
            <h5 class="mb-0">Riwayat Pengeluaran</h5>
          </div>

          <div class="col-md-6 mt-3 mt-md-0">
            <input v-model="search" type="search" class="form-control" placeholder="Cari nomor, kategori, atau deskripsi..." />
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Nomor Pengeluaran</th>
                <th>Tanggal</th>
                <th>Kategori</th>
                <th>Deskripsi</th>
                <th>Metode</th>
                <th class="text-end">Jumlah</th>
              </tr>
            </thead>

            <tbody>
              <tr v-if="loading">
                <td colspan="6" class="text-center py-4">Memuat data pengeluaran...</td>
              </tr>

              <tr v-else-if="filteredExpenses.length === 0">
                <td colspan="6" class="text-center text-muted py-4">Belum ada data pengeluaran.</td>
              </tr>

              <tr v-for="expense in filteredExpenses" :key="expense.id">
                <td class="fw-semibold">
                  {{ expense.expense_number }}
                </td>
                <td>{{ formatDate(expense.expense_date) }}</td>
                <td>{{ formatCategory(expense.category) }}</td>
                <td>{{ expense.description }}</td>
                <td>{{ formatPaymentMethod(expense.payment_method) }}</td>
                <td class="text-end fw-semibold">
                  {{ formatCurrency(expense.amount) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
