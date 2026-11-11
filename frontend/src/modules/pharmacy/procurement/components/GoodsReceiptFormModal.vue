<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";

import purchaseOrderService from "../services/purchaseOrderService";
import type { PurchaseOrder } from "../types/purchaseOrder";
import type { GoodsReceiptPayload } from "../types/goodsReceipt";

interface PurchaseOrderOption {
  id: number;
  order_number: string;
  supplier_name?: string;
  status: string;
}

interface ReceiptFormItem {
  purchase_order_item_id: number;
  product_id: number;
  product_name: string;
  sku: string;
  quantity_ordered: number;
  quantity_received: number;
  unit_cost: number;
  notes: string;
}

const emit = defineEmits<{
  select: [payload: GoodsReceiptPayload];
  close: [];
}>();

const loadingOrders = ref(false);
const loadingOrderDetail = ref(false);
const submitting = ref(false);

const purchaseOrders = ref<PurchaseOrderOption[]>([]);
const selectedOrder = ref<PurchaseOrder | null>(null);

const today = new Date().toISOString().slice(0, 10);

const form = reactive({
  receipt_number: "",
  purchase_order_id: 0,
  received_date: today,
  supplier_delivery_number: "",
  notes: "",
  items: [] as ReceiptFormItem[],
});

const canSubmit = computed(() => {
  return form.receipt_number.trim() !== "" && form.purchase_order_id > 0 && form.received_date !== "" && form.items.length > 0 && form.items.some((item) => item.quantity_received > 0);
});

const formatNumber = (value: number) => new Intl.NumberFormat("id-ID").format(value || 0);

async function loadPurchaseOrders() {
  loadingOrders.value = true;

  try {
    const response = await purchaseOrderService.getAll({
      page: 1,
      per_page: 100,
    });

    const result = response.data;

    purchaseOrders.value = (result?.items ?? [])
      .filter((order: PurchaseOrder) => ["draft", "approved", "ordered", "partial"].includes(order.status))
      .map((order: PurchaseOrder) => ({
        id: order.id,
        order_number: order.order_number,
        supplier_name: order.supplier_name,
        status: order.status,
      }));
  } catch (error) {
    console.error("Gagal memuat Purchase Order:", error);
  } finally {
    loadingOrders.value = false;
  }
}

async function selectPurchaseOrder() {
  selectedOrder.value = null;
  form.items = [];

  if (!form.purchase_order_id) return;

  loadingOrderDetail.value = true;

  try {
    const response = await purchaseOrderService.getById(form.purchase_order_id);

    const order = response.data;

    if (!order) {
      selectedOrder.value = null;
      form.items = [];
      return;
    }

    form.items = (order.items ?? []).map((item) => ({
      purchase_order_item_id: Number(item.id),
      product_id: Number(item.product_id),
      product_name: item.product_name ?? "-",
      sku: item.sku ?? "-",
      quantity_ordered: Number(item.quantity),
      quantity_received: 0,
      unit_cost: Number(item.unit_cost),
      notes: "",
    }));
  } catch (error) {
    console.error("Gagal memuat detail PO:", error);
  } finally {
    loadingOrderDetail.value = false;
  }
}

function submit() {
  if (!canSubmit.value || submitting.value) return;

  const payload: GoodsReceiptPayload = {
    receipt_number: form.receipt_number.trim(),
    purchase_order_id: form.purchase_order_id,
    received_date: form.received_date,
    supplier_delivery_number: form.supplier_delivery_number.trim() || null,
    notes: form.notes.trim() || null,
    items: form.items
      .filter((item) => item.quantity_received > 0)
      .map((item) => ({
        purchase_order_item_id: item.purchase_order_item_id,
        quantity_received: item.quantity_received,
        unit_cost: item.unit_cost,
        notes: item.notes.trim() || null,
      })),
  };

  emit("select", payload);
}

onMounted(loadPurchaseOrders);
</script>

<template>
  <div class="goods-receipt-form">
    <div class="mb-3">
      <label class="form-label"> Nomor Penerimaan <span class="text-danger">*</span> </label>
      <input v-model="form.receipt_number" type="text" class="form-control" placeholder="Contoh: GR-2026-0001" />
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label"> Purchase Order <span class="text-danger">*</span> </label>

        <select v-model.number="form.purchase_order_id" class="form-select" :disabled="loadingOrders || loadingOrderDetail" @change="selectPurchaseOrder">
          <option :value="0">
            {{ loadingOrders ? "Memuat PO..." : "Pilih PO" }}
          </option>
          <option v-for="order in purchaseOrders" :key="order.id" :value="order.id">
            {{ order.order_number }} -
            {{ order.supplier_name }}
          </option>
        </select>

        <small v-if="!loadingOrders && purchaseOrders.length === 0" class="text-muted"> Tidak ada PO yang dapat menerima barang. </small>
      </div>

      <div class="col-md-6 mb-3">
        <label class="form-label"> Tanggal Penerimaan <span class="text-danger">*</span> </label>
        <input v-model="form.received_date" type="date" class="form-control" />
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Nomor Surat Jalan Supplier</label>
      <input v-model="form.supplier_delivery_number" type="text" class="form-control" placeholder="Nomor surat jalan" />
    </div>

    <div v-if="selectedOrder" class="card border mb-3">
      <div class="card-body py-3">
        <div class="row">
          <div class="col-md-6">
            <small class="text-muted">Nomor PO</small>
            <div class="fw-semibold">
              {{ selectedOrder.order_number }}
            </div>
          </div>
          <div class="col-md-6">
            <small class="text-muted">Supplier</small>
            <div class="fw-semibold">
              {{ selectedOrder.supplier_name || "-" }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="loadingOrderDetail" class="text-center py-4">
      <div class="spinner-border spinner-border-sm" />
      <span class="ms-2">Memuat detail PO...</span>
    </div>

    <template v-else-if="form.items.length > 0">
      <h6 class="fw-semibold mb-3">Item Penerimaan</h6>

      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th>Produk</th>
              <th class="text-end">Qty PO</th>
              <th style="width: 130px">Qty Diterima</th>
              <th class="text-end">Harga Satuan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in form.items" :key="item.purchase_order_item_id">
              <td>
                <div class="fw-semibold">
                  {{ item.product_name }}
                </div>
                <small class="text-muted">
                  {{ item.sku }}
                </small>
              </td>
              <td class="text-end">
                {{ formatNumber(item.quantity_ordered) }}
              </td>
              <td>
                <input v-model.number="item.quantity_received" type="number" min="0" step="0.001" class="form-control form-control-sm" />
              </td>
              <td class="text-end">
                {{ formatNumber(item.unit_cost) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <small class="text-muted"> Isi jumlah aktual yang diterima. Item dengan jumlah nol tidak akan dikirim. </small>
    </template>

    <div v-else-if="form.purchase_order_id > 0" class="alert alert-warning">Purchase Order ini tidak memiliki item.</div>

    <div class="mb-3 mt-3">
      <label class="form-label">Catatan</label>
      <textarea v-model="form.notes" class="form-control" rows="2" placeholder="Catatan penerimaan..." />
    </div>

    <div class="d-flex justify-content-end gap-2">
      <button type="button" class="btn btn-light" :disabled="submitting" @click="emit('close')">Batal</button>

      <button type="button" class="btn btn-primary" :disabled="!canSubmit || submitting" @click="submit">
        <span v-if="submitting" class="spinner-border spinner-border-sm me-1" />
        Simpan Draft
      </button>
    </div>
  </div>
</template>
