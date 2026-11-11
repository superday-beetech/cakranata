<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import api from "@/api/client";

interface Delivery {
  id: number;
  delivery_number: string;
  order_number?: string;
  customer_name?: string;
  delivery_date: string;
  status: string;
}

interface DeliveryItem {
  id: number;
  product_id: number;
  product_name: string;
  sku?: string;
  batch_id: number;
  batch_number?: string;
  quantity_dispatched: number;
  unit_price: number;
}

interface ReturnItemForm {
  deliveryItemId: number;
  productName: string;
  sku?: string;
  batchNumber?: string;
  batchId: number;
  dispatchedQuantity: number;
  unitPrice: number;
  quantity: number;
  restockable: boolean;
  conditionNotes: string;
}

const deliveries = ref<Delivery[]>([]);
const selectedDeliveryId = ref<number | "">("");
const selectedDelivery = ref<any>(null);
const items = ref<ReturnItemForm[]>([]);

const loadingDeliveries = ref(false);
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

const totalAmount = computed(() => items.value.reduce((total, item) => total + Number(item.quantity || 0) * item.unitPrice, 0));

const totalRestockable = computed(() => items.value.reduce((total, item) => total + (item.restockable ? Number(item.quantity || 0) : 0), 0));

function getResponseData(response: any): any {
  return response?.data?.data;
}

function generateReturnNumber(): string {
  const date = new Date().toISOString().slice(0, 10).replaceAll("-", "");

  const suffix = Math.floor(1000 + Math.random() * 9000);

  return `SR-${date}-${suffix}`;
}

function formatCurrency(value: number): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(Number(value || 0));
}

async function loadDeliveries() {
  loadingDeliveries.value = true;
  errorMessage.value = "";

  try {
    const response = await api.get("/pharmacy/delivery-api", {
      params: {
        page: 1,
        per_page: 100,
      },
    });

    const data = getResponseData(response);

    const rows = Array.isArray(data) ? data : Array.isArray(data?.items) ? data.items : [];

    deliveries.value = rows.filter((delivery: Delivery) => delivery.status === "posted");
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || "Gagal mengambil daftar pengiriman.";
  } finally {
    loadingDeliveries.value = false;
  }
}

async function loadDeliveryDetail() {
  selectedDelivery.value = null;
  items.value = [];
  createdReturnId.value = null;
  successMessage.value = "";
  errorMessage.value = "";

  if (!selectedDeliveryId.value) return;

  loadingDetail.value = true;

  try {
    const response = await api.get(`/pharmacy/delivery-api/show/${selectedDeliveryId.value}`);

    const data = getResponseData(response);

    const delivery = data?.delivery;

    if (!delivery || delivery.status !== "posted") {
      throw new Error("Hanya pengiriman yang sudah diposting yang dapat diretur.");
    }

    selectedDelivery.value = delivery;

    const deliveryItems: DeliveryItem[] = Array.isArray(data?.items) ? data.items : [];

    items.value = deliveryItems.map((item) => ({
      deliveryItemId: Number(item.id),
      productName: item.product_name,
      sku: item.sku,
      batchNumber: item.batch_number,
      batchId: Number(item.batch_id),
      dispatchedQuantity: Number(item.quantity_dispatched),
      unitPrice: Number(item.unit_price),
      quantity: 0,
      restockable: true,
      conditionNotes: "",
    }));
  } catch (error: any) {
    selectedDelivery.value = null;
    items.value = [];

    errorMessage.value = error.response?.data?.message || error.message || "Gagal mengambil detail pengiriman.";
  } finally {
    loadingDetail.value = false;
  }
}

function getRemainingQuantity(item: ReturnItemForm): number {
  return Math.max(0, item.dispatchedQuantity - Number(item.quantity || 0));
}

async function submitReturn() {
  errorMessage.value = "";
  successMessage.value = "";
  createdReturnId.value = null;

  if (!selectedDeliveryId.value || !selectedDelivery.value) {
    errorMessage.value = "Pilih pengiriman terlebih dahulu.";
    return;
  }

  const selectedItems = items.value.filter((item) => Number(item.quantity) > 0);

  if (selectedItems.length === 0) {
    errorMessage.value = "Masukkan minimal satu barang yang diretur.";
    return;
  }

  for (const item of selectedItems) {
    if (item.quantity <= 0 || item.quantity > item.dispatchedQuantity) {
      errorMessage.value = `Kuantitas retur ${item.productName} tidak valid.`;
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
      delivery_id: Number(selectedDeliveryId.value),
      return_date: form.value.return_date,
      reason: form.value.reason,
      notes: form.value.notes,
      items: selectedItems.map((item) => ({
        delivery_item_id: item.deliveryItemId,
        quantity: Number(item.quantity),
        restockable: item.restockable,
        condition_notes: item.conditionNotes,
      })),
    };

    const response = await api.post("/pharmacy/returns-api/create-sales", payload);

    const data = getResponseData(response);

    createdReturnId.value = Number(data?.return_id);

    successMessage.value = response.data?.message || "Draft Sales Return berhasil dibuat.";
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || "Gagal membuat Sales Return.";
  } finally {
    submitting.value = false;
  }
}

onMounted(() => {
  form.value.return_number = generateReturnNumber();
  loadDeliveries();
});
</script>

<template>
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">Buat Sales Return</h4>
        <p class="text-muted mb-0">Mencatat barang yang dikembalikan pelanggan.</p>
      </div>
    </div>

    <div v-if="errorMessage" class="alert alert-danger alert-dismissible" role="alert">
      {{ errorMessage }}
      <button type="button" class="btn-close" @click="errorMessage = ''"></button>
    </div>

    <div v-if="successMessage" class="alert alert-success" role="alert">
      {{ successMessage }}

      <div v-if="createdReturnId" class="mt-2">
        Nomor retur:
        <strong>{{ form.return_number }}</strong>
        <br />
        Return ID:
        <strong>{{ createdReturnId }}</strong>
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
            <label class="form-label"> Nomor Sales Return </label>
            <input v-model="form.return_number" type="text" class="form-control" :disabled="submitting" />
          </div>

          <div class="col-md-4">
            <label class="form-label"> Tanggal Retur </label>
            <input v-model="form.return_date" type="date" class="form-control" :disabled="submitting" />
          </div>

          <div class="col-md-4">
            <label class="form-label"> Delivery </label>
            <select v-model="selectedDeliveryId" class="form-select" :disabled="loadingDeliveries || loadingDetail || submitting" @change="loadDeliveryDetail">
              <option value="">Pilih pengiriman</option>
              <option v-for="delivery in deliveries" :key="delivery.id" :value="delivery.id">
                {{ delivery.delivery_number }}
                — {{ delivery.customer_name || "Customer" }}
              </option>
            </select>

            <small v-if="loadingDeliveries" class="text-muted"> Memuat daftar pengiriman... </small>
          </div>

          <div class="col-md-6">
            <label class="form-label"> Alasan Retur </label>
            <input v-model="form.reason" type="text" class="form-control" placeholder="Contoh: Barang tidak sesuai pesanan" :disabled="submitting" />
          </div>

          <div class="col-md-6">
            <label class="form-label"> Catatan </label>
            <input v-model="form.notes" type="text" class="form-control" placeholder="Catatan tambahan" :disabled="submitting" />
          </div>
        </div>

        <div v-if="selectedDelivery" class="alert border mt-3 mb-0">
          <div class="row g-3">
            <div class="col-md-4">
              <small class="text-muted d-block"> Nomor Delivery </small>
              <strong>
                {{ selectedDelivery.delivery_number }}
              </strong>
            </div>

            <div class="col-md-4">
              <small class="text-muted d-block"> Nomor Order </small>
              <strong>
                {{ selectedDelivery.order_number || "-" }}
              </strong>
            </div>

            <div class="col-md-4">
              <small class="text-muted d-block"> Customer </small>
              <strong>
                {{ selectedDelivery.customer_name || "-" }}
              </strong>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between">
        <h5 class="mb-0">Barang yang Dikembalikan</h5>

        <span class="badge bg-primary"> {{ items.length }} Item </span>
      </div>

      <div class="card-body">
        <div v-if="loadingDetail" class="text-center py-4">
          <div class="spinner-border text-primary"></div>
          <p class="text-muted mt-2 mb-0">Memuat detail pengiriman...</p>
        </div>

        <div v-else-if="!selectedDelivery" class="text-center text-muted py-5">
          <i class="ti ti-package fs-7"></i>
          <p class="mt-2 mb-0">Pilih Delivery untuk menampilkan barang.</p>
        </div>

        <div v-else-if="items.length === 0" class="text-center text-muted py-4">Tidak ada item pada pengiriman ini.</div>

        <div v-else class="table-responsive">
          <table class="table table-bordered align-middle">
            <thead class="">
              <tr>
                <th style="min-width: 170px">Produk</th>
                <th style="min-width: 130px">Batch</th>
                <th class="text-end">Dikirim</th>
                <th style="width: 120px">Qty Retur</th>
                <th style="min-width: 130px">Layak Masuk Stok</th>
                <th style="min-width: 180px">Kondisi / Catatan</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="item in items" :key="item.deliveryItemId">
                <td>
                  <strong>{{ item.productName }}</strong>
                  <small v-if="item.sku" class="d-block text-muted"> SKU: {{ item.sku }} </small>
                </td>

                <td>
                  {{ item.batchNumber || `Batch #${item.batchId}` }}
                </td>

                <td class="text-end">
                  {{ item.dispatchedQuantity }}
                </td>

                <td>
                  <input v-model.number="item.quantity" type="number" min="0" :max="item.dispatchedQuantity" class="form-control form-control-sm" :disabled="submitting" />

                  <small class="text-muted"> Maks. {{ item.dispatchedQuantity }} </small>
                </td>

                <td>
                  <select v-model="item.restockable" class="form-select form-select-sm" :disabled="submitting || item.quantity <= 0">
                    <option :value="true">Ya</option>
                    <option :value="false">Tidak</option>
                  </select>
                </td>

                <td>
                  <input v-model="item.conditionNotes" type="text" class="form-control form-control-sm" placeholder="Kondisi barang" :disabled="submitting || item.quantity <= 0" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <small class="text-muted d-block"> Total Kuantitas Retur </small>
            <h5 class="mb-0">{{ totalQuantity }}</h5>
          </div>

          <div class="col-md-4">
            <small class="text-muted d-block"> Kuantitas Masuk Kembali ke Stok </small>
            <h5 class="mb-0">{{ totalRestockable }}</h5>
          </div>

          <div class="col-md-4">
            <small class="text-muted d-block"> Estimasi Nilai Retur </small>
            <h5 class="mb-0">
              {{ formatCurrency(totalAmount) }}
            </h5>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-body d-flex justify-content-end">
        <button type="button" class="btn btn-primary" :disabled="submitting || loadingDetail || !selectedDelivery || totalQuantity <= 0 || !!createdReturnId" @click="submitReturn">
          <span v-if="submitting" class="spinner-border spinner-border-sm me-2"></span>

          {{ submitting ? "Menyimpan..." : "Simpan Draft Sales Return" }}
        </button>
      </div>
    </div>
  </div>
</template>
