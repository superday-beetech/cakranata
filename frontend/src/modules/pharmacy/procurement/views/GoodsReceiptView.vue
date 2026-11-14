<script setup lang="ts">
import { onMounted, ref } from "vue";

import { notify } from "@/utils/notification";
import { useModalStore } from "@/composables/useModal";

import goodsReceiptService from "../services/goodsReceiptService";
import GoodsReceiptFormModal from "../components/GoodsReceiptFormModal.vue";
import GoodsReceiptDetailModal from "../components/GoodsReceiptDetailModal.vue";

import type { GoodsReceipt, GoodsReceiptListResult, GoodsReceiptPayload, GoodsReceiptPagination, GoodsReceiptStatus } from "../types/goodsReceipt";

const modalStore = useModalStore();

const loading = ref(false);
const receipts = ref<GoodsReceipt[]>([]);

const search = ref("");
const status = ref<GoodsReceiptStatus | "">("");
const pagination = ref<GoodsReceiptPagination>({
  page: 1,
  per_page: 10,
  total: 0,
  total_pages: 0,
  has_next: false,
  has_prev: false,
});

const formatNumber = (value: number) => new Intl.NumberFormat("id-ID").format(Number(value) || 0);

function getErrorMessage(error: unknown): string {
  const err = error as {
    response?: {
      data?: {
        message?: string;
      };
    };
    message?: string;
  };

  return err.response?.data?.message || err.message || "Terjadi kesalahan.";
}

async function loadReceipts(page = 1) {
  loading.value = true;

  try {
    const result = await goodsReceiptService.getAll({
      page,
      per_page: pagination.value.per_page,
      search: search.value.trim() || undefined,
      status: status.value || undefined,
    });

    if (!result) {
      throw new Error("Gagal memuat daftar penerimaan barang.");
    }

    receipts.value = result.items;
    pagination.value = result.pagination;
  } catch (error) {
    notify.error(getErrorMessage(error));
  } finally {
    loading.value = false;
  }
}

function searchReceipts() {
  loadReceipts(1);
}

function changeStatus() {
  loadReceipts(1);
}

function changePage(page: number) {
  if (page < 1 || page > pagination.value.total_pages) {
    return;
  }

  loadReceipts(page);
}

function openCreateModal() {
  modalStore.open({
    title: "Buat Goods Receipt",
    component: GoodsReceiptFormModal,
    size: "xl",
    centered: true,

    onSelect: async (value: unknown) => {
      const payload = value as GoodsReceiptPayload;
      await createReceipt(payload);
    },
  });
}

async function createReceipt(payload: GoodsReceiptPayload) {
  try {
    await goodsReceiptService.create(payload);

    notify.success("Goods Receipt berhasil dibuat sebagai draft.");

    modalStore.close();

    await loadReceipts(1);
  } catch (error) {
    notify.error(getErrorMessage(error));
  }
}

function openDetailModal(receipt: GoodsReceipt) {
  modalStore.open({
    title: `Detail Goods Receipt - ${receipt.receipt_number}`,
    component: GoodsReceiptDetailModal,
    props: {
      receiptId: Number(receipt.id),
    },
    size: "xl",
    centered: true,

    // Event select dari DetailModal hanya untuk refresh daftar.
    // Proses posting sudah dilakukan oleh DetailModal.
    onSelect: async () => {
      await loadReceipts(pagination.value.page);
    },
  });
}

function statusClass(value: string) {
  switch (value) {
    case "posted":
      return "bg-success";

    case "draft":
      return "bg-warning text-dark";

    case "cancelled":
      return "bg-secondary";

    default:
      return "bg-light text-dark";
  }
}

onMounted(() => loadReceipts());
</script>

<template>
  <div class="goods-receipt-page">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
      <div>
        <h4 class="mb-1">Goods Receipt</h4>
        <p class="text-muted mb-0">Pengelolaan penerimaan barang dari supplier.</p>
      </div>

      <button type="button" class="btn btn-primary" @click="openCreateModal">
        <i class="ti ti-plus me-1" />
        Buat Penerimaan
      </button>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="row g-2 mb-3">
          <div class="col-md-5">
            <div class="input-group">
              <input v-model="search" type="search" class="form-control" placeholder="Cari nomor GR, PO, supplier..." @keyup.enter="searchReceipts" />

              <button type="button" class="btn btn-outline-primary" @click="searchReceipts">
                <i class="ti ti-search" />
                Cari
              </button>
            </div>
          </div>

          <div class="col-md-3">
            <select v-model="status" class="form-select" @change="changeStatus">
              <option value="">Semua Status</option>
              <option value="draft">Draft</option>
              <option value="posted">Posted</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>

          <div class="col-md-4 text-md-end">
            <button type="button" class="btn btn-light" :disabled="loading" @click="loadReceipts(pagination.page)">
              <i class="ti ti-refresh me-1" />
              Muat Ulang
            </button>
          </div>
        </div>

        <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-primary" />
          <div class="mt-2 text-muted">Memuat data penerimaan...</div>
        </div>

        <div v-else class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Nomor Penerimaan</th>
                <th>Purchase Order</th>
                <th>Supplier</th>
                <th>Tanggal Terima</th>
                <th>Status</th>
                <th class="text-end">Aksi</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="receipt in receipts" :key="receipt.id">
                <td class="fw-semibold">
                  {{ receipt.receipt_number }}
                </td>

                <td>
                  {{ receipt.order_number || "-" }}
                </td>

                <td>
                  {{ receipt.supplier_name || "-" }}
                </td>

                <td>
                  {{ receipt.received_date }}
                </td>

                <td>
                  <span class="badge" :class="statusClass(receipt.status)">
                    {{ receipt.status }}
                  </span>
                </td>

                <td class="text-end">
                  <button type="button" class="btn btn-sm btn-outline-primary" title="Detail" @click="openDetailModal(receipt)">
                    <i class="ti ti-eye" />
                    Detail
                  </button>
                </td>
              </tr>

              <tr v-if="receipts.length === 0">
                <td colspan="6" class="text-center text-muted py-5">Belum ada data Goods Receipt.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="!loading && pagination.total > 0" class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-3">
          <div class="text-muted small">
            Menampilkan
            {{ (pagination.page - 1) * pagination.per_page + 1 }}
            -
            {{ Math.min(pagination.page * pagination.per_page, pagination.total) }}
            dari {{ formatNumber(pagination.total) }} data
          </div>

          <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="!pagination.has_prev" @click="changePage(pagination.page - 1)">Sebelumnya</button>

            <span class="small"> Halaman {{ pagination.page }} / {{ Math.max(1, pagination.total_pages) }} </span>

            <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="!pagination.has_next" @click="changePage(pagination.page + 1)">Berikutnya</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
