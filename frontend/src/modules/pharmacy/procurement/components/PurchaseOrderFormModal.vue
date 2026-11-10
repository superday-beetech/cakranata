<script setup lang="ts">
import { computed, reactive, ref } from "vue";

import OptionSelect from "@/components/ui/OptionSelect.vue";
import ButtonSpin from "@/components/ui/ButtonSpin.vue";
import CurrencyInput from "@/components/ui/CurrencyInput.vue";
import DiscountInput from "@/components/ui/DiscountInput.vue";

import { useDiscount, type DiscountMode } from "@/composables/useDiscount";
import type { OptionItem } from "@/composables/useOption";

import type { CreatePurchaseOrderPayload, PurchaseOrder, PurchaseOrderItem } from "../types/purchaseOrder";

// ==================================================
// Interfaces
// ==================================================

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
  selling_price?: number | string;
}

interface FormItem {
  product_id: number | "";
  quantity: number;
  unit_cost: number;
  discount_amount: number;
  discount_mode: DiscountMode;
  notes: string;
}

type NumberMode = "auto" | "manual";

interface PurchaseOrderFormState {
  order_number: string;
  supplier_id: number | "";
  order_date: string;
  expected_date: string;
  discount_amount: number;
  tax_amount: number;
  notes: string;
  items: FormItem[];
}

// ==================================================
// Props & Emits
// ==================================================

const props = defineProps<{
  suppliers: SupplierOption[];
  products: ProductOption[];
  order?: PurchaseOrder;
}>();

const emit = defineEmits<{
  select: [payload: CreatePurchaseOrderPayload];
  close: [];
}>();

// ==================================================
// Composables
// ==================================================

const { calculateDiscount, isDiscountValid, calculateNet } = useDiscount();

// ==================================================
// Helpers
// ==================================================

function toNumber(value: unknown): number {
  const number = Number(value);

  return Number.isFinite(number) ? number : 0;
}

function getLocalDate(): string {
  const date = new Date();

  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");

  return `${year}-${month}-${day}`;
}

function generateOrderNumber(): string {
  const date = getLocalDate().replaceAll("-", "");
  const suffix = String(Math.floor(Math.random() * 1_000_000)).padStart(6, "0");

  return `PO-${date}-${suffix}`;
}

function createEmptyItem(): FormItem {
  return {
    product_id: "",
    quantity: 1,
    unit_cost: 0,
    discount_amount: 0,
    discount_mode: "percent",
    notes: "",
  };
}

function formatCurrency(value: number): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(toNumber(value));
}

// ==================================================
// Form State
// ==================================================

const isEdit = computed(() => Boolean(props.order));

const numberMode = ref<NumberMode>(props.order ? "manual" : "auto");

const form = reactive<PurchaseOrderFormState>({
  order_number: props.order?.order_number ?? generateOrderNumber(),

  supplier_id: props.order?.supplier_id ?? "",

  order_date: props.order?.order_date?.slice(0, 10) ?? getLocalDate(),

  expected_date: props.order?.expected_date?.slice(0, 10) ?? "",

  discount_amount: toNumber(props.order?.discount_amount),

  tax_amount: toNumber(props.order?.tax_amount),

  notes: props.order?.notes ?? "",

  items: props.order?.items?.length
    ? props.order.items.map(
        (item: PurchaseOrderItem): FormItem => ({
          product_id: Number(item.product_id),
          quantity: toNumber(item.quantity),
          unit_cost: toNumber(item.unit_cost),
          discount_amount: toNumber(item.discount_amount),
          discount_mode: "amount",
          notes: item.notes ?? "",
        }),
      )
    : [],
});

// ==================================================
// Supplier Selection
// ==================================================

const supplierOptions = computed<OptionItem[]>(() =>
  props.suppliers.map((supplier) => ({
    value: Number(supplier.id),
    label: supplier.name,
    description: supplier.code ? `Kode: ${supplier.code}` : undefined,
    keywords: supplier.code ?? "",
  })),
);

// ==================================================
// Product Selection
// ==================================================

const productToAdd = ref<number | "">("");

const selectedProductIds = computed<number[]>(() => form.items.filter((item) => item.product_id !== "").map((item) => Number(item.product_id)));

const availableProducts = computed<OptionItem[]>(() =>
  props.products
    .filter((product) => !selectedProductIds.value.includes(Number(product.id)))
    .map((product) => ({
      value: Number(product.id),
      label: product.name,
      description: product.sku ? `SKU: ${product.sku}` : undefined,
      keywords: product.sku ?? "",
    })),
);

function addProduct(option: OptionItem | null): void {
  if (!option) return;

  const productId = Number(option.value);

  if (!Number.isInteger(productId) || productId <= 0) {
    productToAdd.value = "";
    return;
  }

  const product = props.products.find((item) => Number(item.id) === productId);

  if (!product) {
    productToAdd.value = "";
    return;
  }

  if (selectedProductIds.value.includes(productId)) {
    productToAdd.value = "";
    return;
  }

  form.items.push({
    ...createEmptyItem(),
    product_id: productId,
    unit_cost: toNumber(product.purchase_price),
  });

  productToAdd.value = "";
}

function removeItem(index: number): void {
  if (index < 0 || index >= form.items.length) return;

  form.items.splice(index, 1);
}

function getProduct(productId: number | ""): ProductOption | undefined {
  if (productId === "") return undefined;

  return props.products.find((product) => Number(product.id) === Number(productId));
}

function getProductName(productId: number | ""): string {
  return getProduct(productId)?.name ?? "Produk tidak ditemukan";
}

function getProductSku(productId: number | ""): string {
  return getProduct(productId)?.sku ?? "-";
}

// ==================================================
// Purchase Order Number
// ==================================================

function useAutomaticNumber(): void {
  numberMode.value = "auto";
  form.order_number = generateOrderNumber();
}

function useManualNumber(): void {
  numberMode.value = "manual";
}

// ==================================================
// Item Calculations
// ==================================================

function getItemGross(item: FormItem): number {
  return Math.max(0, toNumber(item.quantity) * toNumber(item.unit_cost));
}

function getItemDiscount(item: FormItem): number {
  return calculateDiscount(getItemGross(item), item.discount_amount, item.discount_mode);
}

function getItemNet(item: FormItem): number {
  return calculateNet(getItemGross(item), item.discount_amount, item.discount_mode);
}

// ==================================================
// Purchase Order Calculations
// ==================================================

const subtotal = computed(() => form.items.reduce((total, item) => total + getItemNet(item), 0));

const invalidItemDiscount = computed(() => form.items.some((item) => !isDiscountValid(getItemGross(item), item.discount_amount, item.discount_mode)));

const invalidOrderDiscount = computed(() => {
  const discount = toNumber(form.discount_amount);

  return discount < 0 || discount > subtotal.value;
});

const invalidTax = computed(() => toNumber(form.tax_amount) < 0);

const duplicateProducts = computed(() => {
  const selected = selectedProductIds.value;

  return selected.length !== new Set(selected).size;
});

const invalidExpectedDate = computed(() => Boolean(form.expected_date) && form.expected_date < form.order_date);

const invalidItems = computed(
  () =>
    form.items.length === 0 ||
    form.items.some((item) => {
      const quantity = toNumber(item.quantity);
      const unitCost = toNumber(item.unit_cost);

      return item.product_id === "" || quantity <= 0 || unitCost < 0;
    }),
);

const total = computed(() => {
  const discount = Math.max(0, toNumber(form.discount_amount));

  const tax = Math.max(0, toNumber(form.tax_amount));

  return Math.max(0, subtotal.value - discount + tax);
});

// ==================================================
// Submit Validation
// ==================================================

const canSubmit = computed(
  () =>
    Boolean(form.order_number.trim()) &&
    Boolean(form.supplier_id) &&
    Boolean(form.order_date) &&
    form.items.length > 0 &&
    !invalidExpectedDate.value &&
    !duplicateProducts.value &&
    !invalidItemDiscount.value &&
    !invalidOrderDiscount.value &&
    !invalidTax.value &&
    !invalidItems.value,
);

// ==================================================
// Submit
// ==================================================

function submit(): void {
  if (!canSubmit.value) return;

  const payload: CreatePurchaseOrderPayload = {
    order_number: form.order_number.trim(),

    supplier_id: Number(form.supplier_id),

    order_date: form.order_date,

    expected_date: form.expected_date || null,

    discount_amount: toNumber(form.discount_amount),

    tax_amount: toNumber(form.tax_amount),

    notes: form.notes.trim() || null,

    items: form.items.map((item) => ({
      product_id: Number(item.product_id),

      quantity: toNumber(item.quantity),

      unit_cost: toNumber(item.unit_cost),

      discount_amount: getItemDiscount(item),

      notes: item.notes.trim() || null,
    })),
  };

  emit("select", payload);
}
</script>

<template>
  <form @submit.prevent="submit">
    <!-- HEADER -->
    <div class="row g-3">
      <!-- PO NUMBER -->
      <div class="col-md-6">
        <label class="form-label">
          Nomor Purchase Order
          <span class="text-danger">*</span>
        </label>

        <div class="input-group">
          <input v-model="form.order_number" type="text" class="form-control" placeholder="Contoh: PO-20261109-123456" maxlength="50" required :readonly="numberMode === 'auto'" />

          <button type="button" class="btn" :class="numberMode === 'auto' ? 'btn-primary' : 'btn-outline-primary'" @click="useAutomaticNumber">
            <i class="ti ti-refresh me-1"></i>
            Otomatis
          </button>

          <button type="button" class="btn" :class="numberMode === 'manual' ? 'btn-secondary' : 'btn-outline-secondary'" @click="useManualNumber">
            <i class="ti ti-edit me-1"></i>
            Manual
          </button>
        </div>

        <small class="text-muted">
          {{ numberMode === "auto" ? "Nomor dibuat otomatis. Klik Manual jika ingin mengubahnya." : "Masukkan nomor PO secara manual." }}
        </small>
      </div>

      <!-- SUPPLIER -->
      <div class="col-md-6">
        <label class="form-label">
          Supplier
          <span class="text-danger">*</span>
        </label>

        <OptionSelect v-model="form.supplier_id" :options="supplierOptions" placeholder="Pilih supplier..." search-placeholder="Cari nama supplier..." empty-text="Supplier tidak ditemukan" />
      </div>

      <!-- ORDER DATE -->
      <div class="col-md-6">
        <label class="form-label">
          Tanggal Order
          <span class="text-danger">*</span>
        </label>

        <input v-model="form.order_date" type="date" class="form-control" required />
      </div>

      <!-- EXPECTED DATE -->
      <div class="col-md-6">
        <label class="form-label"> Estimasi Kedatangan </label>

        <input v-model="form.expected_date" type="date" class="form-control" :min="form.order_date" />

        <small v-if="invalidExpectedDate" class="text-danger"> Tanggal estimasi tidak boleh sebelum tanggal order. </small>
      </div>
    </div>

    <hr class="my-4" />

    <!-- PRODUCT SELECTION -->
    <div class="mb-3">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h6 class="mb-0 fw-semibold">Item Purchase Order</h6>

          <small class="text-muted"> {{ form.items.length }} produk dipilih </small>
        </div>
      </div>

      <label class="form-label small fw-semibold"> Cari dan Tambahkan Produk </label>

      <OptionSelect
        v-model="productToAdd"
        :options="availableProducts"
        placeholder="Cari nama produk atau SKU..."
        search-placeholder="Ketik nama produk atau SKU..."
        empty-text="Produk tidak ditemukan atau sudah dipilih"
        @change="addProduct"
      />

      <small class="text-muted d-block mt-1"> Pilih produk untuk memasukkannya ke daftar Purchase Order. </small>
    </div>

    <!-- ITEM TABLE -->
    <div class="table-responsive border rounded">
      <table class="table table-sm align-middle mb-0 po-items-table">
        <thead class="table-light">
          <tr>
            <th style="min-width: 220px">Produk</th>
            <th style="width: 100px">Qty</th>
            <th style="width: 160px">Harga Satuan</th>
            <th style="width: 150px">Diskon</th>
            <th style="width: 145px" class="text-end">Subtotal</th>
            <th style="width: 45px"></th>
          </tr>
        </thead>

        <tbody>
          <!-- EMPTY STATE -->
          <tr v-if="form.items.length === 0">
            <td colspan="6" class="text-center py-5">
              <i class="ti ti-shopping-cart fs-2 text-muted d-block mb-2"></i>

              <div class="fw-semibold">Belum ada produk</div>

              <small class="text-muted"> Cari dan tambahkan produk melalui kolom pencarian di atas. </small>
            </td>
          </tr>

          <!-- PRODUCT ROWS -->
          <tr v-for="(item, index) in form.items" :key="String(item.product_id)">
            <!-- PRODUCT -->
            <td>
              <div class="d-flex flex-column">
                <span class="fw-semibold">
                  {{ getProductName(item.product_id) }}
                </span>

                <small class="text-muted"> SKU: {{ getProductSku(item.product_id) }} </small>

                <small v-if="!getProduct(item.product_id)" class="text-danger"> Produk tidak ditemukan dalam daftar produk saat ini. </small>
              </div>
            </td>

            <!-- QUANTITY -->
            <td>
              <ButtonSpin v-model="item.quantity" :min="0.001" :step="1" size="sm" />
            </td>

            <!-- UNIT COST -->
            <td>
              <CurrencyInput v-model="item.unit_cost" :min="0" placeholder="Harga" size="sm" />
            </td>

            <!-- DISCOUNT -->
            <td>
              <DiscountInput v-model="item.discount_amount" v-model:mode="item.discount_mode" />

              <small v-if="!isDiscountValid(getItemGross(item), item.discount_amount, item.discount_mode)" class="text-danger d-block mt-1"> Diskon tidak valid </small>
            </td>

            <!-- SUBTOTAL -->
            <td class="text-end">
              <strong class="text-nowrap">
                {{ formatCurrency(getItemNet(item)) }}
              </strong>
            </td>

            <!-- DELETE -->
            <td class="text-center">
              <button type="button" class="btn btn-sm btn-light text-danger" title="Hapus produk" @click="removeItem(index)">
                <i class="ti ti-trash"></i>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- VALIDATION -->
    <div v-if="invalidItems" class="alert alert-warning py-2 mt-3 mb-2">
      <i class="ti ti-alert-triangle me-1"></i>
      Tambahkan minimal satu produk dan periksa kuantitas serta harga satuan.
    </div>

    <div v-if="duplicateProducts" class="alert alert-warning py-2 mt-2 mb-2">Produk yang sama tidak boleh dimasukkan lebih dari satu kali.</div>

    <div v-if="invalidItemDiscount" class="alert alert-warning py-2 mt-2 mb-2">Periksa nilai diskon item.</div>

    <div v-if="invalidOrderDiscount" class="alert alert-warning py-2 mt-2 mb-2">Diskon PO tidak boleh melebihi subtotal.</div>

    <div v-if="invalidTax" class="alert alert-warning py-2 mt-2 mb-2">Nilai pajak tidak boleh negatif.</div>

    <!-- TOTAL -->
    <div class="row justify-content-end mt-3">
      <div class="col-md-5 col-lg-4">
        <div class="d-flex justify-content-between mb-2">
          <span>Subtotal</span>

          <strong>
            {{ formatCurrency(subtotal) }}
          </strong>
        </div>

        <div class="mb-2">
          <label class="form-label small mb-1"> Diskon PO </label>

          <CurrencyInput v-model="form.discount_amount" :min="0" placeholder="Diskon PO" size="sm" />
        </div>

        <div class="mb-3">
          <label class="form-label small mb-1"> Pajak </label>

          <CurrencyInput v-model="form.tax_amount" :min="0" placeholder="Pajak" size="sm" />
        </div>

        <div class="d-flex justify-content-between border-top pt-3">
          <strong>Total PO</strong>

          <strong class="text-primary fs-5">
            {{ formatCurrency(total) }}
          </strong>
        </div>
      </div>
    </div>

    <!-- NOTES -->
    <div class="mt-3">
      <label class="form-label"> Catatan Purchase Order </label>

      <textarea v-model="form.notes" class="form-control" rows="2" placeholder="Catatan tambahan untuk PO..."></textarea>
    </div>

    <!-- ACTIONS -->
    <div class="d-flex justify-content-end gap-2 mt-4">
      <button type="button" class="btn btn-light" @click="emit('close')">Batal</button>

      <button type="submit" class="btn btn-primary" :disabled="!canSubmit">
        <i class="ti ti-device-floppy me-1"></i>

        {{ isEdit ? "Simpan Perubahan" : "Simpan Purchase Order" }}
      </button>
    </div>
  </form>
</template>
