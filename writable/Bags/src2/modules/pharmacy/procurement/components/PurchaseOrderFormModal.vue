<script setup lang="ts">
import { computed, reactive } from "vue";
import type { CreatePurchaseOrderPayload, PurchaseOrder } from "../types/purchaseOrder";

interface SupplierOption {
  id: number;
  name: string;
  code?: string;
}

interface ProductOption {
  id: number;
  name: string;
  sku?: string;
  selling_price?: number | string;
  purchase_price?: number | string;
}

const props = defineProps<{
  suppliers: SupplierOption[];
  products: ProductOption[];
  order?: PurchaseOrder;
}>();

const emit = defineEmits<{
  select: [payload: CreatePurchaseOrderPayload];
  close: [];
}>();

interface FormItem {
  product_id: number | "";
  quantity: number;
  unit_cost: number;
  discount_amount: number;
  notes: string;
}

type PurchaseOrderFormState = {
  order_number: string;
  supplier_id: number | "";
  order_date: string;
  expected_date: string;
  discount_amount: number;
  tax_amount: number;
  notes: string;
  items: FormItem[];
};

const form = reactive<PurchaseOrderFormState>({
  order_number: props.order?.order_number ?? "",

  supplier_id: props.order?.supplier_id ?? "",

  order_date: props.order?.order_date?.slice(0, 10) ?? new Date().toISOString().slice(0, 10),

  expected_date: props.order?.expected_date?.slice(0, 10) ?? "",

  discount_amount: Number(props.order?.discount_amount ?? 0),
  tax_amount: Number(props.order?.tax_amount ?? 0),
  notes: props.order?.notes ?? "",

  items: props.order?.items?.length
    ? props.order.items.map(
        (item): FormItem => ({
          product_id: item.product_id,
          quantity: Number(item.quantity),
          unit_cost: Number(item.unit_cost),
          discount_amount: Number(item.discount_amount),
          notes: item.notes ?? "",
        }),
      )
    : [
        {
          product_id: "",
          quantity: 1,
          unit_cost: 0,
          discount_amount: 0,
          notes: "",
        },
      ],
});
const subtotal = computed(() =>
  form.items.reduce((total: number, item: FormItem) => {
    return total + Math.max(0, item.quantity * item.unit_cost - item.discount_amount);
  }, 0),
);
/* const subtotal = computed(() =>
  form.items.reduce((total, item) => {
    return total + Math.max(0, Number(item.quantity) * Number(item.unit_cost) - Number(item.discount_amount));
  }, 0),
); */

const total = computed(() => Math.max(0, subtotal.value - Number(form.discount_amount) + Number(form.tax_amount)));

function addItem() {
  form.items.push({
    product_id: "",
    quantity: 1,
    unit_cost: 0,
    discount_amount: 0,
    notes: "",
  });
}

function removeItem(index: number) {
  if (form.items.length <= 1) return;
  form.items.splice(index, 1);
}

function onProductChange(item: FormItem) {
  const product = props.products.find((product) => product.id === Number(item.product_id));

  if (product) {
    item.unit_cost = Number(product.purchase_price ?? 0);
  }
}

function formatCurrency(value: number) {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(value);
}

function submit() {
  if (!form.order_number.trim()) return;
  if (!form.supplier_id || !form.order_date) return;

  const validItems = form.items.filter((item) => item.product_id !== "" && Number(item.quantity) > 0 && Number(item.unit_cost) >= 0);

  if (validItems.length === 0) return;

  const payload: CreatePurchaseOrderPayload = {
    order_number: form.order_number.trim(),
    supplier_id: Number(form.supplier_id),
    order_date: form.order_date,
    expected_date: form.expected_date || null,
    discount_amount: Number(form.discount_amount),
    tax_amount: Number(form.tax_amount),
    notes: form.notes.trim() || null,
    items: validItems.map((item) => ({
      product_id: Number(item.product_id),
      quantity: Number(item.quantity),
      unit_cost: Number(item.unit_cost),
      discount_amount: Number(item.discount_amount),
      notes: item.notes.trim() || null,
    })),
  };

  emit("select", payload);
}
</script>

<template>
  <form @submit.prevent="submit">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Nomor PO</label>
        <input v-model="form.order_number" type="text" class="form-control" placeholder="Contoh: PO-2026-001" required />
      </div>

      <div class="col-md-6">
        <label class="form-label">Supplier</label>
        <select v-model="form.supplier_id" class="form-select" required>
          <option value="">Pilih supplier</option>
          <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
            {{ supplier.code ? `${supplier.code} - ` : "" }}
            {{ supplier.name }}
          </option>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Tanggal Order</label>
        <input v-model="form.order_date" type="date" class="form-control" required />
      </div>

      <div class="col-md-6">
        <label class="form-label">Estimasi Kedatangan</label>
        <input v-model="form.expected_date" type="date" class="form-control" />
      </div>
    </div>

    <hr class="my-4" />

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="mb-0 fw-semibold">Item Purchase Order</h6>
      <button type="button" class="btn btn-sm btn-outline-primary" @click="addItem">
        <i class="ti ti-plus me-1"></i>
        Tambah Produk
      </button>
    </div>

    <div v-for="(item, index) in form.items" :key="index" class="border rounded p-3 mb-3">
      <div class="d-flex justify-content-between mb-3">
        <strong>Item {{ index + 1 }}</strong>

        <button v-if="form.items.length > 1" type="button" class="btn btn-sm btn-outline-danger" @click="removeItem(index)">
          <i class="ti ti-trash"></i>
        </button>
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Produk</label>
          <select v-model="item.product_id" class="form-select" required @change="onProductChange(item)">
            <option value="">Pilih produk</option>
            <option v-for="product in products" :key="product.id" :value="product.id">
              {{ product.sku ? `${product.sku} - ` : "" }}
              {{ product.name }}
            </option>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Qty</label>
          <input v-model.number="item.quantity" type="number" min="0.001" step="0.001" class="form-control" required />
        </div>

        <div class="col-md-4">
          <label class="form-label">Harga Satuan</label>
          <input v-model.number="item.unit_cost" type="number" min="0" class="form-control" required />
        </div>

        <div class="col-md-4">
          <label class="form-label">Diskon Item</label>
          <input v-model.number="item.discount_amount" type="number" min="0" class="form-control" />
        </div>

        <div class="col-md-8">
          <label class="form-label">Catatan Item</label>
          <input v-model="item.notes" type="text" class="form-control" />
        </div>
      </div>
    </div>

    <div class="row justify-content-end">
      <div class="col-md-6">
        <div class="d-flex justify-content-between mb-2">
          <span>Subtotal</span>
          <strong>{{ formatCurrency(subtotal) }}</strong>
        </div>

        <div class="mb-2">
          <label class="form-label">Diskon PO</label>
          <input v-model.number="form.discount_amount" type="number" min="0" class="form-control" />
        </div>

        <div class="mb-3">
          <label class="form-label">Pajak</label>
          <input v-model.number="form.tax_amount" type="number" min="0" class="form-control" />
        </div>

        <div class="d-flex justify-content-between border-top pt-3">
          <strong>Total PO</strong>
          <strong class="text-primary fs-5">
            {{ formatCurrency(total) }}
          </strong>
        </div>
      </div>
    </div>

    <div class="mt-3">
      <label class="form-label">Catatan Purchase Order</label>
      <textarea v-model="form.notes" class="form-control" rows="2"></textarea>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
      <button type="button" class="btn btn-light" @click="emit('close')">Batal</button>

      <button type="submit" class="btn btn-primary">
        {{ props.order ? "Simpan Perubahan" : "Simpan Purchase Order" }}
      </button>
    </div>
  </form>
</template>
