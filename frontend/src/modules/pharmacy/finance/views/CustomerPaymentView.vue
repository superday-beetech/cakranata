<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import api from "@/api/client";
import { notify } from "@/utils/notification";
import { useModalStore } from "@/composables/useModal";

import financeService from "../services/financeService";
import type { CustomerPaymentPayload } from "../types/finance";

import CustomerPaymentFormModal, { type CustomerOption } from "./CustomerPaymentFormModal.vue";

interface CustomerPaymentRow {
  id: number;
  payment_number: string;
  customer_id: number;
  distribution_order_id?: number | null;
  payment_date: string;
  amount: number;
  payment_method: string;
  reference_number?: string | null;
  notes?: string | null;
}

const modalStore = useModalStore();

const payments = ref<CustomerPaymentRow[]>([]);
const customers = ref<CustomerOption[]>([]);
const loading = ref(false);
const search = ref("");

const customerNames = computed(() => {
  return new Map(customers.value.map((customer) => [customer.id, customer.name]));
});

const filteredPayments = computed(() => {
  const keyword = search.value.trim().toLowerCase();

  if (!keyword) return payments.value;

  return payments.value.filter((payment) => {
    const customerName = customerNames.value.get(payment.customer_id) ?? "";

    return payment.payment_number.toLowerCase().includes(keyword) || customerName.toLowerCase().includes(keyword) || payment.payment_method.toLowerCase().includes(keyword) || String(payment.distribution_order_id ?? "").includes(keyword);
  });
});

const totalPayment = computed(() => filteredPayments.value.reduce((total, payment) => total + Number(payment.amount || 0), 0));

function unwrapResponse(response: any): any {
  return response?.data?.data ?? response?.data ?? response;
}

function generatePaymentNumber(): string {
  const now = new Date();
  const date = [now.getFullYear(), String(now.getMonth() + 1).padStart(2, "0"), String(now.getDate()).padStart(2, "0")].join("");

  const time = [String(now.getHours()).padStart(2, "0"), String(now.getMinutes()).padStart(2, "0"), String(now.getSeconds()).padStart(2, "0")].join("");

  return `CP-${date}-${time}`;
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

function formatPaymentMethod(value: string): string {
  const labels: Record<string, string> = {
    cash: "Tunai",
    transfer: "Transfer",
    debit: "Debit",
    credit: "Kredit",
  };

  return labels[value] ?? value ?? "-";
}

async function loadCustomers(): Promise<void> {
  try {
    const response = await api.get("/pharmacy/customer-api");
    const body = unwrapResponse(response);
    const rows = Array.isArray(body) ? body : (body?.items ?? body?.customers ?? []);

    customers.value = rows
      .map((row: any) => ({
        id: Number(row.id),
        name: String(row.name ?? row.customer_name ?? row.company_name ?? ""),
      }))
      .filter((row: CustomerOption) => row.id > 0 && row.name !== "");
  } catch (error) {
    console.error("Gagal memuat pelanggan:", error);
    notify.error("Gagal memuat data pelanggan.");
  }
}

async function loadPayments(): Promise<void> {
  loading.value = true;

  try {
    const response = await financeService.getCustomerPayments();
    const body = unwrapResponse(response);
    const rows = Array.isArray(body) ? body : (body?.items ?? body?.payments ?? []);

    payments.value = rows;
  } catch (error) {
    console.error("Gagal memuat pembayaran pelanggan:", error);
    notify.error("Gagal memuat daftar pembayaran pelanggan.");
  } finally {
    loading.value = false;
  }
}

async function savePayment(payload: CustomerPaymentPayload): Promise<void> {
  try {
    await financeService.createCustomerPayment(payload);

    notify.success("Pembayaran pelanggan berhasil disimpan.");
    await loadPayments();
  } catch (error: any) {
    console.error("Gagal menyimpan pembayaran pelanggan:", error);

    const message = error?.response?.data?.messages?.error ?? error?.response?.data?.message ?? "Gagal menyimpan pembayaran pelanggan.";

    notify.error(message);
  }
}

function openCreateModal(): void {
  if (customers.value.length === 0) {
    notify.warning("Data pelanggan belum tersedia.");
    return;
  }

  modalStore.open({
    title: "Tambah Pembayaran Pelanggan",
    component: CustomerPaymentFormModal,
    size: "lg",
    centered: true,
    props: {
      customers: customers.value,
      paymentNumber: generatePaymentNumber(),
    },
    onSelect: async (value: unknown) => {
      if (typeof value !== "object" || value === null || !("payment_number" in value) || !("customer_id" in value)) {
        return;
      }

      const payload = value as CustomerPaymentPayload;

      modalStore.close();
      await savePayment(payload);
    },
  });
}

onMounted(async () => {
  await Promise.all([loadCustomers(), loadPayments()]);
});
</script>

<template>
  <div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
      <div>
        <h4 class="mb-1">Pembayaran Pelanggan</h4>
        <p class="text-muted mb-0">Kelola penerimaan pembayaran dari pelanggan.</p>
      </div>

      <button type="button" class="btn btn-primary" @click="openCreateModal">
        <i class="ti ti-plus me-1"></i>
        Tambah Pembayaran
      </button>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted mb-2">Total Pembayaran</div>
            <h4 class="mb-0">{{ formatCurrency(totalPayment) }}</h4>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted mb-2">Jumlah Transaksi</div>
            <h4 class="mb-0">{{ filteredPayments.length }}</h4>
          </div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="row align-items-center mb-3">
          <div class="col-md-6">
            <h5 class="mb-0">Riwayat Pembayaran</h5>
          </div>

          <div class="col-md-6 mt-3 mt-md-0">
            <input v-model="search" type="search" class="form-control" placeholder="Cari nomor, pelanggan, atau metode..." />
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Nomor Pembayaran</th>
                <th>Tanggal</th>
                <th>Pelanggan</th>
                <th>Distribution Order</th>
                <th>Metode</th>
                <th class="text-end">Jumlah</th>
              </tr>
            </thead>

            <tbody>
              <tr v-if="loading">
                <td colspan="6" class="text-center py-4">Memuat data pembayaran...</td>
              </tr>

              <tr v-else-if="filteredPayments.length === 0">
                <td colspan="6" class="text-center text-muted py-4">Belum ada data pembayaran pelanggan.</td>
              </tr>

              <tr v-for="payment in filteredPayments" :key="payment.id">
                <td class="fw-semibold">
                  {{ payment.payment_number }}
                </td>
                <td>{{ formatDate(payment.payment_date) }}</td>
                <td>
                  {{ customerNames.get(payment.customer_id) ?? `Pelanggan #${payment.customer_id}` }}
                </td>
                <td>
                  {{ payment.distribution_order_id ?? "-" }}
                </td>
                <td>{{ formatPaymentMethod(payment.payment_method) }}</td>
                <td class="text-end fw-semibold">
                  {{ formatCurrency(payment.amount) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
