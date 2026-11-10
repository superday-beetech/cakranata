<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";

import distributionService from "../services/distributionService";
import deliveryService from "../services/deliveryService";

import type { DistributionOrderDetail } from "../types/distribution";

import { notify } from "@/utils/notification";

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const saving = ref(false);

const orderDetail = ref<DistributionOrderDetail | null>(null);

function localDate(): string {
  const date = new Date();
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");

  return `${year}-${month}-${day}`;
}

function getTodayDate(): string {
  const now = new Date();

  return [now.getFullYear(), String(now.getMonth() + 1).padStart(2, "0"), String(now.getDate()).padStart(2, "0")].join("-");
}

function generateDeliveryNumber(date: string): string {
  const now = new Date();
  const [year, month, day] = date.split("-");

  const datePart = `${year}${month}${day}`;

  const timePart = [String(now.getHours()).padStart(2, "0"), String(now.getMinutes()).padStart(2, "0"), String(now.getSeconds()).padStart(2, "0")].join("");

  const randomPart = String(Math.floor(1000 + Math.random() * 9000));

  return `DLV-${datePart}-${timePart}-${randomPart}`;
}

const form = reactive({
  delivery_number: "",
  delivery_date: getTodayDate(),
  recipient_name: "",
  recipient_phone: "",
  shipping_address: "",
  notes: "",
});

const orderId = computed(() => Number(route.query.order_id));

const order = computed(() => orderDetail.value?.order ?? null);
const items = computed(() => orderDetail.value?.items ?? []);

const canCreateDelivery = computed(() => {
  return !!order.value && !["completed", "cancelled"].includes(order.value.status);
});

function formatCurrency(value: number | string): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(Number(value || 0));
}

function formatNumber(value: number | string): string {
  return new Intl.NumberFormat("id-ID", {
    maximumFractionDigits: 3,
  }).format(Number(value || 0));
}

async function loadOrder() {
  if (!Number.isInteger(orderId.value) || orderId.value <= 0) {
    notify.error("ID pesanan tidak valid.");
    router.replace("/pharmacy/distribution");
    return;
  }

  loading.value = true;

  try {
    orderDetail.value = await distributionService.getOrderDetail(orderId.value);

    if (!orderDetail.value?.order) {
      throw new Error("Data pesanan tidak ditemukan.");
    }

    if (!canCreateDelivery.value) {
      notify.warning("Pesanan ini tidak dapat dibuatkan pengiriman.");
    }
  } catch (error: any) {
    notify.error(error?.response?.data?.message || error?.message || "Gagal memuat data pesanan.");
  } finally {
    loading.value = false;
  }
}

function goBack() {
  if (order.value) {
    router.push({
      path: "/pharmacy/distribution/order-detail",
      query: {
        id: String(order.value.id),
      },
    });

    return;
  }

  router.push("/pharmacy/distribution");
}

async function submit() {
  if (!order.value || !canCreateDelivery.value) {
    notify.warning("Pesanan tidak dapat dibuatkan pengiriman.");
    return;
  }

  if (!form.delivery_number.trim()) {
    notify.warning("Nomor pengiriman wajib diisi.");
    return;
  }

  if (!form.delivery_date) {
    notify.warning("Tanggal pengiriman wajib diisi.");
    return;
  }

  saving.value = true;

  try {
    const newDeliveryId = await deliveryService.createDelivery({
      delivery_number: form.delivery_number.trim(),
      distribution_order_id: Number(order.value.id),
      delivery_date: form.delivery_date,
      recipient_name: form.recipient_name.trim() || null,
      recipient_phone: form.recipient_phone.trim() || null,
      shipping_address: form.shipping_address.trim() || null,
      notes: form.notes.trim() || null,
    });

    notify.success("Dokumen pengiriman berhasil dibuat.");

    router.push({
      path: "/pharmacy/distribution/delivery-detail",
      query: {
        id: String(newDeliveryId),
        order_id: String(order.value.id),
      },
    });
  } catch (error: any) {
    notify.error(error?.response?.data?.message || error?.message || "Gagal membuat dokumen pengiriman.");
  } finally {
    saving.value = false;
  }
}
watch(
  () => form.delivery_date,
  (date) => {
    form.delivery_number = date ? generateDeliveryNumber(date) : "";
  },
  { immediate: true },
);
onMounted(loadOrder);
</script>

<template>
  <div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <h4 class="mb-1">Buat Dokumen Pengiriman</h4>
        <p class="text-muted mb-0">Buat delivery berdasarkan pesanan distribusi.</p>
      </div>

      <button type="button" class="btn btn-light" @click="goBack">
        <i class="ti ti-arrow-left me-1"></i>
        Kembali
      </button>
    </div>

    <div v-if="loading" class="card">
      <div class="card-body text-center py-5">
        <div class="spinner-border text-primary"></div>
        <p class="text-muted mt-2 mb-0">Memuat data pesanan...</p>
      </div>
    </div>

    <template v-else-if="order">
      <div v-if="!canCreateDelivery" class="alert alert-warning">Pesanan ini sudah selesai atau dibatalkan. Dokumen pengiriman baru tidak dapat dibuat.</div>

      <!-- Order Reference -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Referensi Pesanan</h5>
        </div>

        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="text-muted small"> Nomor Pesanan </label>
              <div class="fw-semibold">
                {{ order.order_number }}
              </div>
            </div>

            <div class="col-md-4">
              <label class="text-muted small"> Pelanggan </label>
              <div class="fw-semibold">
                {{ order.customer_name || `Pelanggan #${order.customer_id}` }}
              </div>
            </div>

            <div class="col-md-4">
              <label class="text-muted small"> Tanggal Pesanan </label>
              <div>{{ order.order_date }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Order Items -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Produk dalam Pesanan</h5>
        </div>

        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>Produk</th>
                  <th class="text-end">Kuantitas Pesanan</th>
                  <th class="text-end">Harga Satuan</th>
                  <th class="text-end">Subtotal</th>
                </tr>
              </thead>

              <tbody>
                <tr v-for="item in items" :key="String(item.id)">
                  <td>
                    <div class="fw-semibold">
                      {{ item.product_name || `Produk #${item.product_id}` }}
                    </div>
                    <small class="text-muted">
                      {{ item.sku || "-" }}
                    </small>
                  </td>

                  <td class="text-end">
                    {{ formatNumber(item.quantity) }}
                  </td>

                  <td class="text-end">
                    {{ formatCurrency(item.unit_price) }}
                  </td>

                  <td class="text-end">
                    {{ formatCurrency(item.subtotal) }}
                  </td>
                </tr>

                <tr v-if="items.length === 0">
                  <td colspan="4" class="text-center text-muted py-3">Tidak ada item pesanan.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Delivery Form -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Informasi Pengiriman</h5>
        </div>

        <div class="card-body">
          <form @submit.prevent="submit">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">
                  Nomor Pengiriman
                  <span class="text-danger">*</span>
                </label>

                <input v-model="form.delivery_number" type="text" class="form-control" placeholder="Contoh: DO-2026-001" maxlength="100" required :disabled="!canCreateDelivery || saving" />
              </div>

              <div class="col-md-6">
                <label class="form-label">
                  Tanggal Pengiriman
                  <span class="text-danger">*</span>
                </label>

                <input v-model="form.delivery_date" type="date" class="form-control" required :disabled="!canCreateDelivery || saving" />
              </div>

              <div class="col-md-6">
                <label class="form-label"> Nama Penerima </label>

                <input v-model="form.recipient_name" type="text" class="form-control" maxlength="150" placeholder="Nama penerima barang" :disabled="!canCreateDelivery || saving" />
              </div>

              <div class="col-md-6">
                <label class="form-label"> Nomor Telepon Penerima </label>

                <input v-model="form.recipient_phone" type="tel" class="form-control" maxlength="30" placeholder="Nomor telepon penerima" :disabled="!canCreateDelivery || saving" />
              </div>

              <div class="col-12">
                <label class="form-label"> Alamat Pengiriman </label>

                <textarea v-model="form.shipping_address" class="form-control" rows="3" placeholder="Alamat tujuan pengiriman" :disabled="!canCreateDelivery || saving"></textarea>
              </div>

              <div class="col-12">
                <label class="form-label">Catatan</label>

                <textarea v-model="form.notes" class="form-control" rows="3" placeholder="Catatan tambahan pengiriman" :disabled="!canCreateDelivery || saving"></textarea>
              </div>
            </div>

            <hr class="my-4" />

            <div class="d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-light" :disabled="saving" @click="goBack">Batal</button>

              <button type="submit" class="btn btn-primary" :disabled="saving || !canCreateDelivery">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>

                <i v-else class="ti ti-device-floppy me-1"></i>

                {{ saving ? "Menyimpan..." : "Simpan Dokumen Pengiriman" }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </template>

    <div v-else class="card">
      <div class="card-body text-center py-5">
        <i class="ti ti-file-off fs-1 text-muted"></i>
        <h6 class="mt-3">Data pesanan tidak tersedia</h6>

        <button type="button" class="btn btn-light mt-2" @click="goBack">Kembali</button>
      </div>
    </div>
  </div>
</template>
