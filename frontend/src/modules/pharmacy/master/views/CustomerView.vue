<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";

import customerService, { type Customer, type CustomerPagination } from "../services/customerService";

import CustomerFormModal from "../components/CustomerFormModal.vue";
import CustomerDetailModal from "../components/CustomerDetailModal.vue";

import { useModalStore } from "@/composables/useModal";
import { notify } from "@/utils/notification";

const modalStore = useModalStore();

const customers = ref<Customer[]>([]);
const loading = ref(false);

const search = ref("");
const status = ref("");
const customerType = ref("");

const currentPage = ref(1);
const perPage = ref(10);

const pagination = ref<CustomerPagination>({
  current_page: 1,
  per_page: 10,
  total: 0,
  total_pages: 0,
});

let searchTimeout: ReturnType<typeof setTimeout> | undefined;

const visiblePages = computed<(number | string)[]>(() => {
  const total = pagination.value.total_pages;
  const current = currentPage.value;

  if (total <= 7) {
    return Array.from({ length: total }, (_, index) => index + 1);
  }

  if (current <= 4) {
    return [1, 2, 3, 4, 5, "...", total];
  }

  if (current >= total - 3) {
    return [1, "...", total - 4, total - 3, total - 2, total - 1, total];
  }

  return [1, "...", current - 1, current, current + 1, "...", total];
});

async function loadCustomers(page = currentPage.value) {
  if (loading.value) return;

  loading.value = true;

  try {
    const response = await customerService.getAll({
      page,
      per_page: perPage.value,
      search: search.value.trim(),
      customer_type: customerType.value || undefined,
      is_active: status.value === "" ? undefined : Number(status.value),
    });

    const result = response.data;

    if (result.error || !result.data) {
      throw new Error(Array.isArray(result.message) ? result.message.join(", ") : result.message || "Gagal mengambil data customer.");
    }

    customers.value = result.data.items;
    pagination.value = result.data.pagination;
    currentPage.value = result.data.pagination.current_page;
  } catch (error: any) {
    notify.error(error?.response?.data?.message || error?.message || "Gagal mengambil data customer.");
  } finally {
    loading.value = false;
  }
}

function changePage(page: number) {
  if (loading.value || page < 1 || page > pagination.value.total_pages || page === currentPage.value) {
    return;
  }

  loadCustomers(page);
}

function openCreateForm() {
  modalStore.open({
    title: "Tambah Customer",
    component: CustomerFormModal,
    size: "lg",
    centered: true,
    props: { customer: null },
    onSelect: () => loadCustomers(1),
  });
}

function openEditForm(customer: Customer) {
  modalStore.open({
    title: "Edit Customer",
    component: CustomerFormModal,
    size: "lg",
    centered: true,
    props: { customer },
    onSelect: () => loadCustomers(),
  });
}

function openDetail(customer: Customer) {
  modalStore.open({
    title: "Detail Customer",
    component: CustomerDetailModal,
    size: "lg",
    centered: true,
    props: { customer },
    onSelect: (value) => {
      modalStore.close();
      openEditForm(value as Customer);
    },
  });
}

async function deleteCustomer(customer: Customer) {
  const confirmed = window.confirm(`Hapus customer "${customer.name}"?`);

  if (!confirmed) return;

  try {
    const response = await customerService.delete(customer.id);
    const result = response.data;

    if (result.error) {
      throw new Error(Array.isArray(result.message) ? result.message.join(", ") : result.message || "Gagal menghapus customer.");
    }

    notify.success("Customer berhasil dihapus.");

    const nextPage = customers.value.length === 1 && currentPage.value > 1 ? currentPage.value - 1 : currentPage.value;

    await loadCustomers(nextPage);
  } catch (error: any) {
    notify.error(error?.response?.data?.message || error?.message || "Gagal menghapus customer.");
  }
}

function resetFilters() {
  search.value = "";
  status.value = "";
  customerType.value = "";
}

watch([search, status, customerType], () => {
  if (searchTimeout) clearTimeout(searchTimeout);

  searchTimeout = setTimeout(() => loadCustomers(1), 350);
});

watch(perPage, () => loadCustomers(1));

onMounted(() => loadCustomers(1));
</script>

<template>
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">Customer</h4>
        <p class="text-muted mb-0">Pengelolaan data pelanggan dan penerima distribusi.</p>
      </div>

      <button class="btn btn-primary" @click="openCreateForm">
        <i class="ti ti-plus me-1" />
        Tambah Customer
      </button>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-5">
            <label class="form-label">Pencarian</label>
            <input v-model="search" type="search" class="form-control" placeholder="Cari kode, nama, telepon, atau email..." />
          </div>

          <div class="col-md-3">
            <label class="form-label">Jenis Customer</label>
            <input v-model="customerType" type="text" class="form-control" placeholder="Contoh: retail" />
          </div>

          <div class="col-md-2">
            <label class="form-label">Status</label>
            <select v-model="status" class="form-select">
              <option value="">Semua</option>
              <option value="1">Aktif</option>
              <option value="0">Nonaktif</option>
            </select>
          </div>

          <div class="col-md-2">
            <button class="btn btn-light w-100" type="button" @click="resetFilters">
              <i class="ti ti-refresh me-1" />
              Reset
            </button>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-4" style="width: 60px">No.</th>
              <th style="width: 140px">Kode</th>
              <th>Nama Customer</th>
              <th>Jenis</th>
              <th>Telepon</th>
              <th>Email</th>
              <th>Status</th>
              <th class="text-end pe-4" style="width: 150px">Aksi</th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="loading">
              <td colspan="8" class="text-center py-5">
                <span class="spinner-border spinner-border-sm me-2" />
                Memuat data customer...
              </td>
            </tr>

            <tr v-else-if="customers.length === 0">
              <td colspan="8" class="text-center text-muted py-5">Belum ada data customer.</td>
            </tr>

            <tr v-for="(customer, index) in customers" v-else :key="customer.id">
              <td class="ps-4">
                {{ (currentPage - 1) * perPage + index + 1 }}
              </td>

              <td class="fw-semibold">{{ customer.code }}</td>

              <td>
                <div class="fw-semibold">{{ customer.name }}</div>
                <small class="text-muted">
                  {{ customer.address || "-" }}
                </small>
              </td>

              <td>{{ customer.customer_type || "-" }}</td>
              <td>{{ customer.phone || "-" }}</td>
              <td>{{ customer.email || "-" }}</td>

              <td>
                <span class="badge" :class="Number(customer.is_active) === 1 ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'">
                  {{ Number(customer.is_active) === 1 ? "Aktif" : "Nonaktif" }}
                </span>
              </td>

              <td class="text-end pe-4">
                <div class="d-flex justify-content-end gap-1">
                  <button class="btn btn-sm btn-light" title="Detail" @click="openDetail(customer)">
                    <i class="ti ti-eye" />
                  </button>

                  <button class="btn btn-sm btn-light" title="Edit" @click="openEditForm(customer)">
                    <i class="ti ti-edit" />
                  </button>

                  <button class="btn btn-sm btn-light text-danger" title="Hapus" @click="deleteCustomer(customer)">
                    <i class="ti ti-trash" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="pagination.total > 0" class="card-body border-top d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-2">
          <small class="text-muted">Tampilkan</small>

          <select v-model.number="perPage" class="form-select form-select-sm" style="width: 75px">
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>

          <small class="text-muted"> Total {{ pagination.total }} customer </small>
        </div>

        <nav aria-label="Customer pagination">
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item" :class="{ disabled: currentPage === 1 || loading }">
              <button class="page-link" @click="changePage(1)">Pertama</button>
            </li>

            <li class="page-item" :class="{ disabled: currentPage === 1 || loading }">
              <button class="page-link" @click="changePage(currentPage - 1)">Sebelumnya</button>
            </li>

            <li
              v-for="(page, index) in visiblePages"
              :key="`${page}-${index}`"
              class="page-item"
              :class="{
                active: page === currentPage,
                disabled: page === '...' || loading,
              }"
            >
              <button v-if="typeof page === 'number'" class="page-link" @click="changePage(page)">
                {{ page }}
              </button>
              <span v-else class="page-link">...</span>
            </li>

            <li
              class="page-item"
              :class="{
                disabled: currentPage === pagination.total_pages || loading,
              }"
            >
              <button class="page-link" @click="changePage(currentPage + 1)">Selanjutnya</button>
            </li>

            <li
              class="page-item"
              :class="{
                disabled: currentPage === pagination.total_pages || loading,
              }"
            >
              <button class="page-link" @click="changePage(pagination.total_pages)">Akhir</button>
            </li>
          </ul>
        </nav>
      </div>
    </div>
  </div>
</template>
