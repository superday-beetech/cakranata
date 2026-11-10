<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";

import api from "@/api/client";
import { notify } from "@/utils/notification";
import { useModalStore } from "@/composables/modal";

import purchaseOrderService from "../services/purchaseOrderService";

import PurchaseOrderFormModal from "../components/PurchaseOrderFormModal.vue";
import PurchaseOrderDetailModal from "../components/PurchaseOrderDetailModal.vue";

import OptionSelect from "@/components/ui/OptionSelect.vue";
import type { OptionItem } from "@/composables/useOption";

import type { CreatePurchaseOrderPayload, PurchaseOrder, PurchaseOrderPagination } from "../types/purchaseOrder";

interface SupplierOption {
  id: number;
  name: string;
  code?: string;
}

interface ProductOption {
  id: number;
  name: string;
  sku?: string;
  purchase_price?: number | string;
}

const modalStore = useModalStore();

const orders = ref<PurchaseOrder[]>([]);
const suppliers = ref<SupplierOption[]>([]);
const products = ref<ProductOption[]>([]);

const loading = ref(false);
const saving = ref(false);

const filters = reactive({
  search: "",
  status: "",
  supplier_id: "",
});

const pagination = ref<PurchaseOrderPagination>({
  page: 1,
  per_page: 10,
  total: 0,
  total_pages: 0,
  has_next: false,
  has_prev: false,
});

function extractList<T>(response: any): T[] {
  const data = response?.data?.data ?? response?.data ?? response;

  if (Array.isArray(data)) return data;
  if (Array.isArray(data?.items)) return data.items;
  if (Array.isArray(data?.data)) return data.data;

  return [];
}

const supplierFilterOptions = computed<OptionItem[]>(() => [
  {
    value: "",
    label: "Semua Supplier",
  },
  ...suppliers.value.map((supplier) => ({
    value: String(supplier.id),
    label: supplier.name,
    description: supplier.code ?? undefined,
    keywords: supplier.code ?? "",
  })),
]);

const statusFilterOptions: OptionItem[] = [
  { value: "", label: "Semua Status" },
  { value: "draft", label: "Draft" },
  { value: "approved", label: "Approved" },
  { value: "ordered", label: "Ordered" },
  { value: "partial", label: "Partially Received" },
  { value: "received", label: "Received" },
  { value: "cancelled", label: "Cancelled" },
];

async function loadMasterOptions() {
  try {
    const [supplierResponse, productResponse] = await Promise.all([
      api.get("/pharmacy/supplier-api"),
      api.get("/pharmacy/product-api", {
        params: { per_page: 100 },
      }),
    ]);

    suppliers.value = extractList<SupplierOption>(supplierResponse);
    products.value = extractList<ProductOption>(productResponse);
  } catch (error) {
    console.error("Gagal memuat supplier dan produk:", error);
    notify.error("Gagal memuat pilihan supplier dan produk.");
  }
}

async function loadOrders(page = 1) {
  loading.value = true;

  try {
    const response = await purchaseOrderService.getAll({
      search: filters.search.trim() || undefined,
      status: filters.status || undefined,
      supplier_id: filters.supplier_id ? Number(filters.supplier_id) : undefined,
      page,
      per_page: pagination.value.per_page,
    });

    if (response.error) {
      throw new Error(response.message);
    }

    orders.value = response.data.items ?? [];
    pagination.value = response.data.pagination;
  } catch (error: any) {
    console.error(error);
    notify.error(error?.message || "Gagal mengambil data Purchase Order.");
  } finally {
    loading.value = false;
  }
}

function searchOrders() {
  loadOrders(1);
}

function resetFilters() {
  filters.search = "";
  filters.status = "";
  filters.supplier_id = "";

  loadOrders(1);
}

function openCreateModal() {
  modalStore.open({
    title: "Buat Purchase Order",
    component: PurchaseOrderFormModal,
    size: "xl",
    centered: true,
    props: {
      suppliers: suppliers.value,
      products: products.value,
    },
    onSelect: async (value: unknown) => {
      const payload = value as CreatePurchaseOrderPayload;
      await createOrder(payload);
    },
  });
}

async function createOrder(payload: CreatePurchaseOrderPayload) {
  if (saving.value) return;

  saving.value = true;

  try {
    const response = await purchaseOrderService.create(payload);

    if (response.error) {
      throw new Error(response.message);
    }

    modalStore.close();
    notify.success("Purchase Order berhasil dibuat.");

    await loadOrders(1);
  } catch (error: any) {
    console.error(error);
    notify.error(error?.response?.data?.message || error?.message || "Gagal membuat Purchase Order.");
  } finally {
    saving.value = false;
  }
}

async function openDetail(order: PurchaseOrder) {
  try {
    const response = await purchaseOrderService.getById(order.id);

    if (response.error) {
      throw new Error(response.message);
    }

    modalStore.open({
      title: `Detail Purchase Order - ${order.order_number}`,
      component: PurchaseOrderDetailModal,
      size: "xl",
      centered: true,
      props: {
        order: response.data,
      },
    });
  } catch (error: any) {
    console.error(error);
    notify.error(error?.message || "Gagal mengambil detail Purchase Order.");
  }
}

function formatCurrency(value: number | string) {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(Number(value ?? 0));
}

function formatDate(value?: string | null) {
  if (!value) return "-";

  return new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
}

function statusClass(status: string) {
  const classes: Record<string, string> = {
    draft: "bg-light-secondary text-secondary",
    approved: "bg-light-info text-info",
    ordered: "bg-light-primary text-primary",
    partial: "bg-light-warning text-warning",
    received: "bg-light-success text-success",
    cancelled: "bg-light-danger text-danger",
  };

  return classes[status] ?? "bg-light-secondary text-secondary";
}

async function openEditModal(order: PurchaseOrder) {
  if (order.status !== "draft") {
    notify.warning("Hanya PO draft yang dapat diedit.");
    return;
  }

  try {
    const response = await purchaseOrderService.getById(order.id);

    if (response.error) {
      throw new Error(response.message);
    }

    modalStore.open({
      title: `Edit Purchase Order - ${order.order_number}`,
      component: PurchaseOrderFormModal,
      size: "xl",
      centered: true,
      props: {
        suppliers: suppliers.value,
        products: products.value,
        order: response.data,
      },
      onSelect: async (value: unknown) => {
        await updateOrder(order.id, value as CreatePurchaseOrderPayload);
      },
    });
  } catch (error: any) {
    notify.error(error?.message || "Gagal mengambil data Purchase Order.");
  }
}

async function updateOrder(id: number, payload: CreatePurchaseOrderPayload) {
  try {
    const response = await purchaseOrderService.update(id, payload);

    if (response.error) {
      throw new Error(response.message);
    }

    modalStore.close();
    notify.success("Purchase Order berhasil diperbarui.");

    await loadOrders(pagination.value.page);
  } catch (error: any) {
    notify.error(error?.response?.data?.message || error?.message || "Gagal memperbarui Purchase Order.");
  }
}

async function deleteOrder(order: PurchaseOrder) {
  if (order.status !== "draft") {
    notify.warning("Hanya PO draft yang dapat dihapus.");
    return;
  }

  const confirmed = window.confirm(`Hapus Purchase Order ${order.order_number}? Tindakan ini tidak dapat dibatalkan.`);

  if (!confirmed) return;

  try {
    const response = await purchaseOrderService.delete(order.id);

    if (response.error) {
      throw new Error(response.message);
    }

    notify.success("Purchase Order berhasil dihapus.");

    const currentPage = orders.value.length === 1 && pagination.value.page > 1 ? pagination.value.page - 1 : pagination.value.page;

    await loadOrders(currentPage);
  } catch (error: any) {
    notify.error(error?.response?.data?.message || error?.message || "Gagal menghapus Purchase Order.");
  }
}

onMounted(async () => {
  await Promise.all([loadMasterOptions(), loadOrders()]);
});
</script>

<template>
  <div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
      <div>
        <h4 class="mb-1">Purchase Order</h4>
        <p class="text-muted mb-0">Pengelolaan pesanan pembelian kepada supplier.</p>
      </div>

      <button type="button" class="btn btn-primary" :disabled="loading" @click="openCreateModal">
        <i class="ti ti-plus me-1"></i>
        Buat Purchase Order
      </button>
    </div>

    <!-- Filters -->
    <div class="card">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-lg-4">
            <label class="form-label">Pencarian</label>
            <input v-model="filters.search" type="text" class="form-control" placeholder="Nomor PO atau nama supplier" @keyup.enter="searchOrders" />
          </div>

          <div class="col-lg-3">
            <label class="form-label">Supplier</label>

            <OptionSelect v-model="filters.supplier_id" :options="supplierFilterOptions" placeholder="Semua Supplier" search-placeholder="Cari supplier..." empty-text="Supplier tidak ditemukan" />
          </div>

          <div class="col-lg-3">
            <label class="form-label">Status</label>

            <OptionSelect v-model="filters.status" :options="statusFilterOptions" placeholder="Semua Status" search-placeholder="Cari status..." empty-text="Status tidak ditemukan" />
          </div>

          <div class="col-lg-2 d-flex gap-2">
            <button type="button" class="btn btn-primary flex-fill" @click="searchOrders">
              <i class="ti ti-search me-1"></i>
              Cari
            </button>

            <button type="button" class="btn btn-light" @click="resetFilters">Reset</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="card-title mb-0">Daftar Purchase Order</h5>

          <span class="text-muted small"> Total {{ pagination.total }} data </span>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>Nomor PO</th>
                <th>Supplier</th>
                <th>Tanggal Order</th>
                <th>Estimasi</th>
                <th>Status</th>
                <th class="text-end">Total</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>

            <tbody>
              <tr v-if="loading">
                <td colspan="7" class="text-center py-5">
                  <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                  Memuat data...
                </td>
              </tr>

              <tr v-else-if="orders.length === 0">
                <td colspan="7" class="text-center text-muted py-5">Belum ada Purchase Order.</td>
              </tr>

              <tr v-for="order in orders" :key="order.id">
                <td>
                  <span class="fw-semibold">
                    {{ order.order_number }}
                  </span>
                </td>

                <td>{{ order.supplier_name || "-" }}</td>

                <td>{{ formatDate(order.order_date) }}</td>

                <td>{{ formatDate(order.expected_date) }}</td>

                <td>
                  <span class="badge" :class="statusClass(order.status)">
                    {{ order.status }}
                  </span>
                </td>

                <td class="text-end fw-semibold">
                  {{ formatCurrency(order.total_amount) }}
                </td>

                <td class="text-center">
                  <div class="d-flex justify-content-center gap-1">
                    <button type="button" class="btn btn-sm btn-light-primary" title="Detail" @click="openDetail(order)">
                      <i class="ti ti-eye"></i>
                    </button>

                    <button v-if="order.status === 'draft'" type="button" class="btn btn-sm btn-light-warning" title="Edit" @click="openEditModal(order)">
                      <i class="ti ti-edit"></i>
                    </button>

                    <button v-if="order.status === 'draft'" type="button" class="btn btn-sm btn-light-danger" title="Hapus" @click="deleteOrder(order)">
                      <i class="ti ti-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.total > 0" class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
          <div class="d-flex align-items-center gap-2">
            <span class="text-muted small">Tampilkan</span>

            <select v-model.number="pagination.per_page" class="form-select form-select-sm" style="width: 80px" @change="loadOrders(1)">
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>

            <span class="text-muted small">data</span>
          </div>

          <div class="d-flex align-items-center gap-2">
            <span class="text-muted small">
              Halaman {{ pagination.page }} dari
              {{ pagination.total_pages }}
            </span>

            <button type="button" class="btn btn-sm btn-light" :disabled="!pagination.has_prev || loading" @click="loadOrders(pagination.page - 1)">
              <i class="ti ti-chevron-left"></i>
            </button>

            <button type="button" class="btn btn-sm btn-light" :disabled="!pagination.has_next || loading" @click="loadOrders(pagination.page + 1)">
              <i class="ti ti-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
