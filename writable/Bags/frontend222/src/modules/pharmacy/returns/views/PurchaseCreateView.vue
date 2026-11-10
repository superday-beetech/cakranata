<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import api from "@/api/client";

interface GoodsReceipt {
  id: number;
  receipt_number: string;
  purchase_order_id: number;
  order_number?: string;
  supplier_name?: string;
  received_date: string;
  status: string;
  items?: GoodsReceiptItem[];
}

interface GoodsReceiptItem {
  id: number;
  product_id: number;
  product_name: string;
  sku?: string;
  quantity_received: number;
  unit_cost: number;
}

interface ProductBatch {
  id: number;
  goods_receipt_item_id: number;
  product_id: number;
  batch_number: string;
  expired_at: string;
  received_quantity: number;
  quantity_remaining: number;
  unit_cost: number;
  status: string;
}

interface ReturnItemForm {
  goodsReceiptItemId: number;
  productId: number;
  productName: string;
  sku?: string;
  receivedQuantity: number;
  unitCost: number;
  batchId: number | "";
  quantity: number;
  notes: string;
  batches: ProductBatch[];
}

const receipts = ref<GoodsReceipt[]>([]);
const selectedReceiptId = ref<number | "">("");
const selectedReceipt = ref<GoodsReceipt | null>(null);

const items = ref<ReturnItemForm[]>([]);

const loadingReceipts = ref(false);
const loadingDetail = ref(false);
const submitting = ref(false);

const errorMessage = ref("");
const successMessage = ref("");
const createdReturnId = ref<number | null>(null);

const today = new Date().toISOString().slice(0, 10);

const form = ref({
  return_number: "",
  return_date: today,
  reason: "",
  notes: "",
});

const totalQuantity = computed(() => items.value.reduce((total, item) => total + Number(item.quantity || 0), 0));

const totalAmount = computed(() => items.value.reduce((total, item) => total + Number(item.quantity || 0) * item.unitCost, 0));

function generateReturnNumber(): string {
  const date = new Date().toISOString().slice(0, 10).replaceAll("-", "");

  const suffix = Math.floor(1000 + Math.random() * 9000);

  return `PR-${date}-${suffix}`;
}

function formatCurrency(value: number): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(value || 0);
}

function getResponseData(response: any): any {
  return response?.data?.data;
}

async function loadReceipts() {
  loadingReceipts.value = true;
  errorMessage.value = "";

  try {
    const response = await api.get("/pharmacy/goods-receipts");

    const data = getResponseData(response);

    receipts.value = Array.isArray(data) ? data : Array.isArray(data?.items) ? data.items : [];

    receipts.value = receipts.value.filter((receipt) => receipt.status === "posted");
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || "Gagal mengambil daftar penerimaan barang.";
  } finally {
    loadingReceipts.value = false;
  }
}

async function loadReceiptDetail() {
  if (!selectedReceiptId.value) {
    selectedReceipt.value = null;
    items.value = [];
    return;
  }

  loadingDetail.value = true;
  errorMessage.value = "";
  successMessage.value = "";
  createdReturnId.value = null;

  try {
    const response = await api.get(`/pharmacy/goods-receipt-detail/${selectedReceiptId.value}`);

    const receipt = getResponseData(response) as GoodsReceipt;

    if (!receipt || !Array.isArray(receipt.items)) {
      throw new Error("Data detail penerimaan tidak valid.");
    }

    if (receipt.status !== "posted") {
      throw new Error("Hanya penerimaan yang sudah diposting yang dapat diretur.");
    }

    selectedReceipt.value = receipt;

    items.value = await Promise.all(
      receipt.items.map(async (receiptItem) => {
        const batchResponse = await api.get("/pharmacy/inventory-api/batches", {
          params: {
            page: 1,
            per_page: 100,
            product_id: receiptItem.product_id,
            status: "available",
          },
        });

        const batchData = getResponseData(batchResponse);

        const allBatches: ProductBatch[] = Array.isArray(batchData) ? batchData : Array.isArray(batchData?.items) ? batchData.items : [];

        const relatedBatches = allBatches.filter((batch) => Number(batch.goods_receipt_item_id) === Number(receiptItem.id) && batch.status === "available" && Number(batch.quantity_remaining) > 0);

        return {
          goodsReceiptItemId: receiptItem.id,
          productId: receiptItem.product_id,
          productName: receiptItem.product_name,
          sku: receiptItem.sku,
          receivedQuantity: Number(receiptItem.quantity_received),
          unitCost: Number(receiptItem.unit_cost),
          batchId: "",
          quantity: 0,
          notes: "",
          batches: relatedBatches,
        };
      }),
    );
  } catch (error: any) {
    selectedReceipt.value = null;
    items.value = [];

    errorMessage.value = error.response?.data?.message || error.message || "Gagal mengambil detail penerimaan barang.";
  } finally {
    loadingDetail.value = false;
  }
}

function getMaximumQuantity(item: ReturnItemForm): number {
  const batch = item.batches.find((row) => Number(row.id) === Number(item.batchId));

  if (!batch) {
    return 0;
  }

  return Math.min(Number(batch.quantity_remaining), item.receivedQuantity);
}

function onBatchChange(item: ReturnItemForm) {
  const maximum = getMaximumQuantity(item);

  if (item.quantity > maximum) {
    item.quantity = maximum;
  }
}

async function submitReturn() {
  errorMessage.value = "";
  successMessage.value = "";
  createdReturnId.value = null;

  if (!selectedReceiptId.value || !selectedReceipt.value) {
    errorMessage.value = "Pilih penerimaan barang terlebih dahulu.";
    return;
  }

  const selectedItems = items.value.filter((item) => Number(item.quantity) > 0);

  if (selectedItems.length === 0) {
    errorMessage.value = "Masukkan minimal satu barang yang akan diretur.";
    return;
  }

  for (const item of selectedItems) {
    if (!item.batchId) {
      errorMessage.value = `Pilih batch untuk produk ${item.productName}.`;
      return;
    }

    if (item.quantity <= 0 || item.quantity > getMaximumQuantity(item)) {
      errorMessage.value = `Kuantitas retur produk ${item.productName} tidak valid.`;
      return;
    }
  }

  submitting.value = true;

  try {
    if (!form.value.return_number.trim()) {
      form.value.return_number = generateReturnNumber();
    }

    const payload = {
      return_number: form.value.return_number,
      goods_receipt_id: Number(selectedReceiptId.value),
      return_date: form.value.return_date,
      reason: form.value.reason,
      notes: form.value.notes,
      items: selectedItems.map((item) => ({
        goods_receipt_item_id: item.goodsReceiptItemId,
        batch_id: Number(item.batchId),
        quantity: Number(item.quantity),
        notes: item.notes,
      })),
    };

    const response = await api.post("/pharmacy/returns-api/create-purchase", payload);

    const data = getResponseData(response);

    createdReturnId.value = Number(data?.return_id);

    successMessage.value = response.data?.message || "Draft Purchase Return berhasil dibuat.";
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || "Gagal membuat Purchase Return.";
  } finally {
    submitting.value = false;
  }
}

onMounted(() => {
  form.value.return_number = generateReturnNumber();
  loadReceipts();
});
</script>

<template>
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">Buat Purchase Return</h4>
        <p class="text-muted mb-0">Membuat retur barang kepada pemasok.</p>
      </div>
    </div>

    <div v-if="errorMessage" class="alert alert-danger alert-dismissible" role="alert">
      {{ errorMessage }}
      <button type="button" class="btn-close" @click="errorMessage = ''"></button>
    </div>

    <div v-if="successMessage" class="alert alert-success" role="alert">
      {{ successMessage }}
      <div v-if="createdReturnId" class="mt-2">
        Nomor retur: <strong>{{ form.return_number }}</strong>
        <br />
        Return ID: <strong>{{ createdReturnId }}</strong>
        <br />
        Status: <strong>Draft</strong>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0">Informasi Retur</h5>
      </div>

      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label"> Nomor Purchase Return </label>
            <input v-model="form.return_number" type="text" class="form-control" placeholder="Nomor retur" />
          </div>

          <div class="col-md-4">
            <label class="form-label"> Tanggal Retur </label>
            <input v-model="form.return_date" type="date" class="form-control" />
          </div>

          <div class="col-md-4">
            <label class="form-label"> Goods Receipt </label>
            <select v-model="selectedReceiptId" class="form-select" :disabled="loadingReceipts || loadingDetail || submitting" @change="loadReceiptDetail">
              <option value="">Pilih penerimaan barang</option>
              <option v-for="receipt in receipts" :key="receipt.id" :value="receipt.id">
                {{ receipt.receipt_number }}
                — {{ receipt.supplier_name || "Supplier" }}
              </option>
            </select>

            <small v-if="loadingReceipts" class="text-muted"> Memuat penerimaan... </small>
          </div>

          <div class="col-md-6">
            <label class="form-label">Alasan Retur</label>
            <input v-model="form.reason" type="text" class="form-control" placeholder="Contoh: Barang rusak atau tidak sesuai" />
          </div>

          <div class="col-md-6">
            <label class="form-label">Catatan</label>
            <input v-model="form.notes" type="text" class="form-control" placeholder="Catatan tambahan" />
          </div>
        </div>

        <div v-if="selectedReceipt" class="alert alert-light border mt-3 mb-0">
          <div class="row g-2">
            <div class="col-md-4">
              <small class="text-muted d-block"> Nomor Penerimaan </small>
              <strong>
                {{ selectedReceipt.receipt_number }}
              </strong>
            </div>

            <div class="col-md-4">
              <small class="text-muted d-block"> Nomor Purchase Order </small>
              <strong>
                {{ selectedReceipt.order_number || "-" }}
              </strong>
            </div>

            <div class="col-md-4">
              <small class="text-muted d-block"> Supplier </small>
              <strong>
                {{ selectedReceipt.supplier_name || "-" }}
              </strong>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between">
        <h5 class="mb-0">Barang yang Diretur</h5>

        <span class="badge bg-primary"> {{ items.length }} Item </span>
      </div>

      <div class="card-body">
        <div v-if="loadingDetail" class="text-center py-4">
          <div class="spinner-border text-primary"></div>
          <p class="text-muted mt-2 mb-0">Memuat detail penerimaan dan batch...</p>
        </div>

        <div v-else-if="!selectedReceipt" class="text-center text-muted py-5">
          <i class="ti ti-package fs-7"></i>
          <p class="mb-0 mt-2">Pilih Goods Receipt untuk menampilkan barang.</p>
        </div>

        <div v-else-if="items.length === 0" class="text-center text-muted py-4">Tidak ada item pada penerimaan ini.</div>

        <div v-else class="table-responsive">
          <table class="table table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th style="min-width: 180px">Produk</th>
                <th style="min-width: 180px">Batch</th>
                <th class="text-end">Diterima</th>
                <th style="width: 130px">Qty Retur</th>
                <th class="text-end">Harga Satuan</th>
                <th style="min-width: 160px">Catatan</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="item in items" :key="item.goodsReceiptItemId">
                <td>
                  <strong>{{ item.productName }}</strong>
                  <small v-if="item.sku" class="d-block text-muted"> SKU: {{ item.sku }} </small>
                </td>

                <td>
                  <select v-model="item.batchId" class="form-select form-select-sm" :disabled="submitting" @change="onBatchChange(item)">
                    <option value="">Pilih batch</option>
                    <option v-for="batch in item.batches" :key="batch.id" :value="batch.id">
                      {{ batch.batch_number }}
                      — Sisa {{ batch.quantity_remaining }} — ED {{ batch.expired_at }}
                    </option>
                  </select>

                  <small v-if="item.batches.length === 0" class="text-danger"> Tidak ada batch tersedia. </small>
                </td>

                <td class="text-end">
                  {{ item.receivedQuantity }}
                </td>

                <td>
                  <input v-model.number="item.quantity" type="number" min="0" :max="getMaximumQuantity(item)" class="form-control form-control-sm" :disabled="!item.batchId || submitting" />

                  <small class="text-muted"> Maks. {{ getMaximumQuantity(item) }} </small>
                </td>

                <td class="text-end">
                  {{ formatCurrency(item.unitCost) }}
                </td>

                <td>
                  <input v-model="item.notes" type="text" class="form-control form-control-sm" placeholder="Catatan item" :disabled="submitting" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="row align-items-center">
          <div class="col-md-8">
            <div class="d-flex gap-4 flex-wrap">
              <div>
                <small class="text-muted d-block"> Total Kuantitas Retur </small>
                <h5 class="mb-0">
                  {{ totalQuantity }}
                </h5>
              </div>

              <div>
                <small class="text-muted d-block"> Estimasi Nilai Retur </small>
                <h5 class="mb-0">
                  {{ formatCurrency(totalAmount) }}
                </h5>
              </div>
            </div>
          </div>

          <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <button type="button" class="btn btn-primary" :disabled="submitting || loadingDetail || !selectedReceipt || totalQuantity <= 0 || !!createdReturnId" @click="submitReturn">
              <span v-if="submitting" class="spinner-border spinner-border-sm me-2"></span>

              {{ submitting ? "Menyimpan..." : "Simpan Draft Retur" }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
