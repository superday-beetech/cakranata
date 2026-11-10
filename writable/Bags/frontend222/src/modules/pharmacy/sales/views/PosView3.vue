<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from "vue";

import { usePos } from "../composables/usePos";
import type { PaymentMethod, ProductSearchResult } from "../types/sales";

import productService from "@/modules/pharmacy/master/services/productService";
import CustomerAutocomplete from "@/modules/pharmacy/master/components/CustomerAutocomplete.vue";

// ============================================================
// TYPES
// ============================================================

type DiscountMode = "amount" | "percent";
type TaxMode = "amount" | "percent";
type PaymentMode = "paid" | "credit";
type CreditMode = "partial" | "full";

interface AdditionalCharge {
  id: number;
  type: string;
  description: string;
  amount: number;
}

const CHARGE_TYPES = ["Donasi", "Ongkir", "Kemasan", "Jasa", "Lainnya"];

const PAYMENT_METHODS: {
  value: PaymentMethod;
  label: string;
}[] = [
  { value: "cash", label: "Tunai" },
  { value: "transfer", label: "Transfer" },
  { value: "qris", label: "QRIS" },
  { value: "other", label: "Lainnya" },
];

// ============================================================
// STATE
// ============================================================

const barcodeInput = ref<HTMLInputElement | null>(null);
const customerSection = ref<HTMLElement | null>(null);
const cartSection = ref<HTMLElement | null>(null);
const paymentInput = ref<HTMLInputElement | null>(null);

const message = ref("");
const errorMessage = ref("");

let searchTimer: ReturnType<typeof setTimeout> | null = null;

const paymentMode = ref<PaymentMode>("paid");
const creditMode = ref<CreditMode>("partial");

const dueDate = ref(new Date().toISOString().slice(0, 10));

const transactionDiscount = ref(0);
const taxMode = ref<TaxMode>("percent");
const taxInput = ref(0);

const additionalCharges = ref<AdditionalCharge[]>([]);
let chargeSequence = 0;

const discountModes = ref<Record<number, DiscountMode>>({});
const itemNotes = ref<Record<number, string>>({});

const receiptAccount = ref({
  bank: "",
  accountName: "",
  accountNumber: "",
});

const qrisImage = ref("");

const shortcutHelp = ref(false);

// ============================================================
// HELPERS
// ============================================================

function toNumber(value: unknown): number {
  const number = Number(value);
  return Number.isFinite(number) ? number : 0;
}

function roundMoney(value: number): number {
  return Math.round((value + Number.EPSILON) * 100) / 100;
}

function formatCurrency(value: number | string | null | undefined): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(toNumber(value));
}

function getErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof Error) return error.message;

  const response = (
    error as {
      response?: {
        data?: {
          message?: string | string[];
        };
      };
    }
  )?.response?.data;

  const apiMessage = response?.message;

  if (Array.isArray(apiMessage)) return apiMessage.join(", ");

  return apiMessage || fallback;
}

function clearMessages(): void {
  message.value = "";
  errorMessage.value = "";
}

function focusBarcode(): void {
  barcodeInput.value?.focus();
}

// ============================================================
// PRODUCT SEARCH
// ============================================================

async function searchProducts(searchKeyword: string): Promise<ProductSearchResult[]> {
  const response = await productService.getAll({
    page: 1,
    per_page: 20,
    search: searchKeyword,
    is_active: 1,
  });

  if (response.data.error) {
    const apiMessage = response.data.message;

    throw new Error(Array.isArray(apiMessage) ? apiMessage.join(", ") : apiMessage || "Gagal mencari produk.");
  }

  const products = response.data.data?.items ?? [];

  return products.map((product) => ({
    id: product.id,
    sku: product.sku ?? "",
    barcode: product.barcode ?? null,
    name: product.name,
    unit_name: product.unit_name ?? undefined,
    selling_price: Number(product.selling_price),
    is_active: Number(product.is_active) === 1,
  }));
}

// ============================================================
// POS COMPOSABLE
// ============================================================

const {
  cart,
  keyword,
  searchResults,
  selectedCustomerId,

  paymentMethod,
  paymentAmount,
  notes,

  loadingSearch,
  processing,
  currentSale,

  isDraft,
  isPosted,
  isCancelled,
  transactionLocked,
  canCreateDraft,
  canPostSale,

  searchProduct,
  clearSearch,

  addProduct,
  updateQuantity,
  updateUnitPrice,
  updateDiscount,
  removeProduct,
  clearCart,

  setCustomer,
  setPaymentAmount,
  setPaymentMethod,

  createDraft,
  postCurrentSale,
  newTransaction,

  heldTransactions,
  loadingHeldTransactions,
  loadHeldTransactions,
  holdCurrentSale,
  resumeHeldTransaction,
} = usePos({
  searchProducts,
});

// ============================================================
// CART CALCULATION
// ============================================================

const itemSubtotal = computed(() => roundMoney(cart.value.reduce((total, item) => total + item.quantity * item.unit_price, 0)));

const itemDiscountTotal = computed(() => roundMoney(cart.value.reduce((total, item) => total + item.discount_amount, 0)));

const transactionDiscountAmount = computed(() => roundMoney(Math.min(Math.max(0, transactionDiscount.value), Math.max(0, itemSubtotal.value - itemDiscountTotal.value))));

const additionalChargeTotal = computed(() => roundMoney(additionalCharges.value.reduce((total, item) => total + Math.max(0, item.amount), 0)));

const taxBase = computed(() => Math.max(0, itemSubtotal.value - itemDiscountTotal.value - transactionDiscountAmount.value));

const taxAmount = computed(() => {
  if (taxMode.value === "amount") {
    return roundMoney(Math.max(0, taxInput.value));
  }

  return roundMoney((taxBase.value * Math.max(0, taxInput.value)) / 100);
});

const grandTotal = computed(() => roundMoney(Math.max(0, taxBase.value + taxAmount.value + additionalChargeTotal.value)));

const remainingAmount = computed(() => roundMoney(Math.max(0, grandTotal.value - paymentAmount.value)));

const changeAmount = computed(() => roundMoney(Math.max(0, paymentAmount.value - grandTotal.value)));

const paidNow = computed(() => {
  if (paymentMode.value === "credit" && creditMode.value === "full") {
    return 0;
  }

  return Math.min(paymentAmount.value, grandTotal.value);
});

const receivableAmount = computed(() => roundMoney(Math.max(0, grandTotal.value - paidNow.value)));

const isFullyPaid = computed(() => receivableAmount.value <= 0);

const canUsePaymentInput = computed(() => paymentMode.value === "paid" || (paymentMode.value === "credit" && creditMode.value === "partial"));

// ============================================================
// DISCOUNT
// ============================================================

function getDiscountMode(productId: number): DiscountMode {
  return discountModes.value[productId] ?? "amount";
}

function getDiscountInputValue(item: (typeof cart.value)[number]): number {
  const mode = getDiscountMode(item.product_id);

  if (mode === "percent") {
    const gross = item.quantity * item.unit_price;

    return gross > 0 ? roundMoney((item.discount_amount / gross) * 100) : 0;
  }

  return item.discount_amount;
}

function handleDiscountModeChange(productId: number, event: Event): void {
  const target = event.target as HTMLSelectElement;

  discountModes.value[productId] = target.value as DiscountMode;
}

function handleItemDiscount(productId: number, event: Event): void {
  const target = event.target as HTMLInputElement;
  const input = Math.max(0, toNumber(target.value));

  const item = cart.value.find((row) => row.product_id === productId);

  if (!item) return;

  const gross = item.quantity * item.unit_price;

  const amount = getDiscountMode(productId) === "percent" ? (gross * Math.min(input, 100)) / 100 : input;

  try {
    updateDiscount(productId, roundMoney(amount));
    clearMessages();
  } catch (error) {
    errorMessage.value = getErrorMessage(error, "Gagal mengubah diskon.");
  }
}

// ============================================================
// ITEM ACTIONS
// ============================================================

function handleQuantityChange(productId: number, event: Event): void {
  const target = event.target as HTMLInputElement;

  try {
    updateQuantity(productId, toNumber(target.value));
    clearMessages();
  } catch (error) {
    errorMessage.value = getErrorMessage(error, "Gagal mengubah kuantitas.");

    const item = cart.value.find((row) => row.product_id === productId);

    if (item) target.value = String(item.quantity);
  }
}

function handlePriceChange(productId: number, event: Event): void {
  const target = event.target as HTMLInputElement;

  try {
    updateUnitPrice(productId, toNumber(target.value));
    clearMessages();
  } catch (error) {
    errorMessage.value = getErrorMessage(error, "Gagal mengubah harga.");

    const item = cart.value.find((row) => row.product_id === productId);

    if (item) target.value = String(item.unit_price);
  }
}

function handleItemNote(productId: number, event: Event): void {
  const target = event.target as HTMLInputElement;
  itemNotes.value[productId] = target.value;
}

function handleRemoveProduct(productId: number): void {
  try {
    removeProduct(productId);
    clearMessages();
  } catch (error) {
    errorMessage.value = getErrorMessage(error, "Gagal menghapus produk.");
  }
}

// ============================================================
// PRODUCT SEARCH CONTROL
// ============================================================

function cancelSearchTimer(): void {
  if (searchTimer !== null) {
    clearTimeout(searchTimer);
    searchTimer = null;
  }
}

function resetProductSearch(): void {
  cancelSearchTimer();
  clearSearch();
}

async function handleSearch(): Promise<void> {
  if (transactionLocked.value) return;

  const value = keyword.value.trim();

  if (!value) {
    resetProductSearch();
    return;
  }

  cancelSearchTimer();
  clearMessages();

  try {
    await searchProduct(value);
  } catch (error) {
    errorMessage.value = getErrorMessage(error, "Gagal mencari produk.");
  }
}

function handleSearchInput(): void {
  if (transactionLocked.value) return;

  cancelSearchTimer();

  if (!keyword.value.trim()) {
    resetProductSearch();
    return;
  }

  searchTimer = setTimeout(() => {
    searchTimer = null;
    void handleSearch();
  }, 300);
}

function handleAddProduct(product: ProductSearchResult): void {
  try {
    addProduct(product);
    clearMessages();
    focusBarcode();
  } catch (error) {
    errorMessage.value = getErrorMessage(error, "Gagal menambahkan produk.");
  }
}

async function handleBarcodeEnter(): Promise<void> {
  const barcode = keyword.value.trim();

  if (!barcode || transactionLocked.value) return;

  cancelSearchTimer();
  clearMessages();

  try {
    await searchProduct(barcode);

    const normalized = barcode.toLowerCase();

    const exactMatch = searchResults.value.find((product) => product.barcode?.trim().toLowerCase() === normalized);

    if (!exactMatch) {
      errorMessage.value = "Barcode tidak ditemukan.";
      return;
    }

    addProduct(exactMatch);
    focusBarcode();
  } catch (error) {
    errorMessage.value = getErrorMessage(error, "Gagal memproses barcode.");
  }
}

// ============================================================
// CUSTOMER
// ============================================================

function handleCustomerChange(customerId: number | string | null): void {
  try {
    setCustomer(customerId);
    clearMessages();
  } catch (error) {
    errorMessage.value = getErrorMessage(error, "Pelanggan tidak valid.");
  }
}

// ============================================================
// ADDITIONAL CHARGES
// ============================================================

function addCharge(): void {
  additionalCharges.value.push({
    id: ++chargeSequence,
    type: "Ongkir",
    description: "",
    amount: 0,
  });
}

function removeCharge(id: number): void {
  additionalCharges.value = additionalCharges.value.filter((item) => item.id !== id);
}

function updateChargeAmount(id: number, event: Event): void {
  const target = event.target as HTMLInputElement;
  const charge = additionalCharges.value.find((item) => item.id === id);

  if (charge) {
    charge.amount = Math.max(0, toNumber(target.value));
  }
}

// ============================================================
// PAYMENT
// ============================================================

function handlePaymentModeChange(mode: PaymentMode): void {
  paymentMode.value = mode;

  if (mode === "paid") {
    creditMode.value = "partial";
    setPaymentAmount(grandTotal.value);
  } else {
    creditMode.value = "partial";
    setPaymentAmount(0);
  }
}

function handleCreditModeChange(mode: CreditMode): void {
  creditMode.value = mode;

  if (mode === "full") {
    setPaymentAmount(0);
  }
}

function handlePaymentAmount(event: Event): void {
  const target = event.target as HTMLInputElement;

  try {
    setPaymentAmount(Math.max(0, toNumber(target.value)));
    clearMessages();
  } catch (error) {
    errorMessage.value = getErrorMessage(error, "Nominal pembayaran tidak valid.");
  }
}

function handlePaymentMethod(event: Event): void {
  const target = event.target as HTMLSelectElement;

  setPaymentMethod(target.value as PaymentMethod);
}

function setExactPayment(): void {
  setPaymentAmount(grandTotal.value);
}

function addCashAmount(amount: number): void {
  setPaymentAmount(paymentAmount.value + amount);
}

function resetPaymentAmount(): void {
  setPaymentAmount(0);
}

function handlePaidAmountChange(): void {
  if (paymentMode.value === "paid") {
    setPaymentAmount(grandTotal.value);
  }
}

function handlePaymentModeAfterCalculation(): void {
  if (paymentMode.value === "paid") {
    setPaymentAmount(grandTotal.value);
  }
}

// ============================================================
// TRANSACTION ACTIONS
// ============================================================

const hasUnsupportedDraftValues = computed(() => transactionDiscountAmount.value > 0 || taxAmount.value > 0 || additionalChargeTotal.value > 0 || paymentMode.value !== "paid");

async function handleCreateDraft(): Promise<void> {
  if (!canCreateDraft.value) return;

  clearMessages();

  if (hasUnsupportedDraftValues.value) {
    errorMessage.value = "Fitur diskon transaksi, pajak fleksibel, biaya tambahan, dan tempo sedang menunggu integrasi backend. Draft belum dibuat agar nominal transaksi tidak salah tersimpan.";

    return;
  }

  try {
    const sale = await createDraft();

    if (sale) {
      message.value = `Draft ${sale.sale_number} berhasil dibuat.`;
    }
  } catch (error) {
    errorMessage.value = getErrorMessage(error, "Gagal membuat draft.");
  }
}

async function handlePostSale(): Promise<void> {
  if (!canPostSale.value) return;

  clearMessages();

  if (hasUnsupportedDraftValues.value) {
    errorMessage.value = "Transaksi belum dapat diposting karena terdapat komponen pembayaran atau biaya yang belum didukung backend.";

    return;
  }

  try {
    const sale = await postCurrentSale();

    if (sale) {
      message.value = `Penjualan ${sale.sale_number} berhasil diposting.`;

      resetProductSearch();
      focusBarcode();
    }
  } catch (error) {
    errorMessage.value = getErrorMessage(error, "Gagal memposting penjualan.");
  }
}

async function handleHoldSale(): Promise<void> {
  if (!isDraft.value || processing.value) return;

  clearMessages();

  try {
    const sale = await holdCurrentSale();

    if (sale) {
      await loadHeldTransactions();

      message.value = `Transaksi ${sale.sale_number} berhasil ditahan.`;

      focusBarcode();
    }
  } catch (error) {
    errorMessage.value = getErrorMessage(error, "Gagal menahan transaksi.");
  }
}

async function handleResumeSale(id: number): Promise<void> {
  if (processing.value || currentSale.value || cart.value.length > 0) {
    return;
  }

  clearMessages();

  try {
    const sale = await resumeHeldTransaction(id);

    if (sale) {
      message.value = `Transaksi ${sale.sale_number} berhasil dilanjutkan.`;

      focusBarcode();
    }
  } catch (error) {
    errorMessage.value = getErrorMessage(error, "Gagal melanjutkan transaksi.");
  }
}

function handleNewTransaction(): void {
  if (!isPosted.value || processing.value) return;

  try {
    newTransaction();

    transactionDiscount.value = 0;
    taxInput.value = 0;
    additionalCharges.value = [];
    itemNotes.value = {};
    discountModes.value = {};

    paymentMode.value = "paid";
    creditMode.value = "partial";
    dueDate.value = new Date().toISOString().slice(0, 10);

    resetProductSearch();
    clearMessages();
    focusBarcode();
  } catch (error) {
    errorMessage.value = getErrorMessage(error, "Gagal memulai transaksi baru.");
  }
}

function handleClearCart(): void {
  if (!cart.value.length || transactionLocked.value) return;

  try {
    clearCart();

    transactionDiscount.value = 0;
    taxInput.value = 0;
    additionalCharges.value = [];
    itemNotes.value = {};
    discountModes.value = {};

    clearMessages();
    focusBarcode();
  } catch (error) {
    errorMessage.value = getErrorMessage(error, "Gagal mengosongkan keranjang.");
  }
}

// ============================================================
// KEYBOARD SHORTCUTS
// ============================================================

function isTypingTarget(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) return false;

  return target.isContentEditable || ["INPUT", "TEXTAREA", "SELECT"].includes(target.tagName);
}

function handleKeyboard(event: KeyboardEvent): void {
  if (event.key === "Escape") {
    shortcutHelp.value = false;
    return;
  }

  if (isTypingTarget(event.target)) return;

  switch (event.key) {
    case "F1":
      event.preventDefault();
      focusBarcode();
      break;

    case "F2":
      event.preventDefault();
      customerSection.value?.scrollIntoView({
        behavior: "smooth",
        block: "center",
      });
      break;

    case "F3":
      event.preventDefault();
      cartSection.value?.scrollIntoView({
        behavior: "smooth",
        block: "center",
      });
      break;

    case "F8":
      event.preventDefault();
      paymentInput.value?.focus();
      break;

    case "F9":
      event.preventDefault();
      setExactPayment();
      break;

    case "F10":
      event.preventDefault();
      if (isDraft.value) void handleHoldSale();
      break;

    case "F11":
      event.preventDefault();
      handleNewTransaction();
      break;

    case "F12":
      event.preventDefault();

      if (isDraft.value) {
        void handlePostSale();
      } else {
        void handleCreateDraft();
      }
      break;
  }
}

// ============================================================
// LIFECYCLE
// ============================================================

onMounted(async () => {
  window.addEventListener("keydown", handleKeyboard);

  focusBarcode();

  try {
    await loadHeldTransactions();
  } catch (error) {
    errorMessage.value = getErrorMessage(error, "Gagal memuat transaksi tertahan.");
  }
});

onBeforeUnmount(() => {
  cancelSearchTimer();
  window.removeEventListener("keydown", handleKeyboard);
});
</script>

<template>
  <div class="pos-page">
    <!-- HEADER -->
    <header class="pos-header">
      <div>
        <div class="pos-eyebrow">POINT OF SALE</div>
        <h4 class="pos-heading">Kasir / Penjualan Langsung</h4>
      </div>

      <div class="pos-header-actions">
        <span v-if="isDraft" class="pos-status status-draft">
          <i class="ti ti-device-floppy"></i>
          Draft
        </span>

        <span v-if="isPosted" class="pos-status status-posted">
          <i class="ti ti-circle-check"></i>
          Posted
        </span>

        <button type="button" class="pos-icon-button" title="Bantuan shortcut" @click="shortcutHelp = !shortcutHelp">
          <i class="ti ti-keyboard"></i>
        </button>

        <button type="button" class="pos-button pos-button-light" :disabled="loadingHeldTransactions" @click="loadHeldTransactions">
          <i class="ti ti-player-pause"></i>
          <span>Tertahan ({{ heldTransactions.length }})</span>
        </button>
      </div>
    </header>

    <!-- SHORTCUT HELP -->
    <div v-if="shortcutHelp" class="pos-shortcut-panel">
      <div class="pos-shortcut-heading">
        <strong>Keyboard Shortcut</strong>
        <button type="button" class="pos-icon-button" @click="shortcutHelp = false">
          <i class="ti ti-x"></i>
        </button>
      </div>

      <div class="pos-shortcut-grid">
        <span><kbd>F1</kbd> Cari produk</span>
        <span><kbd>F2</kbd> Pelanggan</span>
        <span><kbd>F3</kbd> Keranjang</span>
        <span><kbd>F8</kbd> Nominal bayar</span>
        <span><kbd>F9</kbd> Uang pas</span>
        <span><kbd>F10</kbd> Tahan transaksi</span>
        <span><kbd>F11</kbd> Transaksi baru</span>
        <span><kbd>F12</kbd> Proses transaksi</span>
      </div>
    </div>

    <!-- MESSAGES -->
    <div v-if="message" class="pos-message message-success">
      <i class="ti ti-circle-check"></i>
      {{ message }}
      <button type="button" @click="message = ''">
        <i class="ti ti-x"></i>
      </button>
    </div>

    <div v-if="errorMessage" class="pos-message message-error">
      <i class="ti ti-alert-circle"></i>
      {{ errorMessage }}
      <button type="button" @click="errorMessage = ''">
        <i class="ti ti-x"></i>
      </button>
    </div>

    <!-- POSTED BANNER -->
    <div v-if="isPosted" class="pos-posted-banner">
      <div>
        <strong>Transaksi berhasil diposting</strong>
        <div class="pos-muted">
          {{ currentSale?.sale_number }}
        </div>
      </div>

      <button type="button" class="pos-button pos-button-primary" :disabled="processing" @click="handleNewTransaction">
        <i class="ti ti-plus"></i>
        Transaksi Baru
      </button>
    </div>

    <!-- MAIN LAYOUT -->
    <div class="pos-layout">
      <!-- LEFT COLUMN -->
      <main class="pos-main-column">
        <!-- PRODUCT SEARCH -->
        <section class="pos-card">
          <div class="pos-card-header">
            <div>
              <h5>Cari Produk</h5>
              <p>Scan barcode atau cari berdasarkan SKU dan nama barang.</p>
            </div>

            <kbd>F1</kbd>
          </div>

          <div class="pos-search">
            <i class="ti ti-barcode"></i>

            <input
              ref="barcodeInput"
              v-model="keyword"
              type="text"
              autocomplete="off"
              placeholder="Scan barcode / SKU / nama produk..."
              :disabled="transactionLocked || processing"
              @input="handleSearchInput"
              @keydown.enter.prevent="handleBarcodeEnter"
            />

            <button type="button" class="pos-button pos-button-primary" :disabled="loadingSearch || transactionLocked" @click="handleSearch">
              <span v-if="loadingSearch" class="pos-spinner"></span>
              <i v-else class="ti ti-search"></i>
              Cari
            </button>
          </div>

          <div v-if="searchResults.length" class="pos-search-results">
            <button v-for="product in searchResults" :key="product.id" type="button" class="pos-product-result" :disabled="transactionLocked || processing" @click="handleAddProduct(product)">
              <div class="pos-product-info">
                <strong>{{ product.name }}</strong>
                <small>
                  {{ product.sku || "-" }}
                  <span v-if="product.barcode"> · {{ product.barcode }}</span>
                  <span v-if="product.unit_name"> · {{ product.unit_name }}</span>
                </small>
              </div>

              <div class="pos-product-price">
                <strong>{{ formatCurrency(product.selling_price) }}</strong>
                <small>
                  {{ product.available_stock !== undefined ? `Stok: ${product.available_stock}` : "Stok diperiksa saat posting" }}
                </small>
              </div>
            </button>
          </div>
        </section>

        <!-- CART -->
        <section ref="cartSection" class="pos-card pos-cart-card">
          <div class="pos-card-header">
            <div>
              <h5>Keranjang Penjualan</h5>
              <p>{{ cart.length }} jenis produk dalam transaksi</p>
            </div>

            <button type="button" class="pos-button pos-button-danger-light" :disabled="!cart.length || transactionLocked || processing" @click="handleClearCart">
              <i class="ti ti-trash"></i>
              Kosongkan
            </button>
          </div>

          <div v-if="!cart.length" class="pos-empty">
            <i class="ti ti-shopping-cart"></i>
            <strong>Keranjang masih kosong</strong>
            <span>Scan barcode atau cari produk untuk memulai transaksi.</span>
          </div>

          <div v-else class="pos-table-wrapper">
            <table class="pos-table">
              <thead>
                <tr>
                  <th class="col-no">No</th>
                  <th>Barcode</th>
                  <th>Nama Barang</th>
                  <th>Satuan</th>
                  <th class="text-end">Qty</th>
                  <th class="text-end">Harga</th>
                  <th>Disc Jns</th>
                  <th class="text-end">Disc Total</th>
                  <th>Catatan</th>
                  <th></th>
                </tr>
              </thead>

              <tbody>
                <tr v-for="(item, index) in cart" :key="item.product_id">
                  <td class="col-no">{{ index + 1 }}</td>

                  <td class="pos-barcode-cell">
                    {{ item.barcode || "-" }}
                  </td>

                  <td class="pos-item-name">
                    <strong>{{ item.product_name }}</strong>
                    <small>{{ item.sku }}</small>
                  </td>

                  <td>{{ item.unit_name || "-" }}</td>

                  <td>
                    <input type="number" min="0.001" step="0.001" class="pos-table-input input-number" :value="item.quantity" :disabled="transactionLocked || processing" @change="handleQuantityChange(item.product_id, $event)" />
                  </td>

                  <td>
                    <input type="number" min="0" step="1" class="pos-table-input input-number" :value="item.unit_price" :disabled="transactionLocked || processing" @change="handlePriceChange(item.product_id, $event)" />
                  </td>

                  <td>
                    <select class="pos-table-select" :value="getDiscountMode(item.product_id)" :disabled="transactionLocked || processing" @change="handleDiscountModeChange(item.product_id, $event)">
                      <option value="amount">Rp</option>
                      <option value="percent">%</option>
                    </select>

                    <input type="number" min="0" step="0.01" class="pos-table-input input-number mt-1" :value="getDiscountInputValue(item)" :disabled="transactionLocked || processing" @change="handleItemDiscount(item.product_id, $event)" />
                  </td>

                  <td class="text-end pos-number">
                    {{ formatCurrency(item.discount_amount) }}
                  </td>

                  <td>
                    <input type="text" class="pos-table-input" placeholder="Catatan..." :value="itemNotes[item.product_id] || ''" :disabled="transactionLocked || processing" @input="handleItemNote(item.product_id, $event)" />
                  </td>

                  <td>
                    <button type="button" class="pos-remove-button" title="Hapus produk" :disabled="transactionLocked || processing" @click="handleRemoveProduct(item.product_id)">
                      <i class="ti ti-trash"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="cart.length" class="pos-cart-footer">
            <span
              >Total item: <strong>{{ cart.length }}</strong></span
            >
            <span
              >Subtotal: <strong>{{ formatCurrency(itemSubtotal) }}</strong></span
            >
          </div>
        </section>

        <!-- HELD TRANSACTIONS -->
        <section class="pos-card">
          <div class="pos-card-header">
            <div>
              <h5>Transaksi Tertahan</h5>
              <p>Transaksi yang dapat dilanjutkan kembali.</p>
            </div>

            <div class="pos-header-actions">
              <button v-if="isDraft" type="button" class="pos-button pos-button-warning" :disabled="processing" @click="handleHoldSale">
                <i class="ti ti-player-pause"></i>
                Tahan Transaksi
                <kbd>F10</kbd>
              </button>

              <button type="button" class="pos-icon-button" :disabled="loadingHeldTransactions || processing" @click="loadHeldTransactions">
                <i class="ti ti-refresh"></i>
              </button>
            </div>
          </div>

          <div v-if="loadingHeldTransactions" class="pos-empty compact">Memuat transaksi...</div>

          <div v-else-if="heldTransactions.length === 0" class="pos-empty compact">Belum ada transaksi tertahan.</div>

          <div v-else class="pos-held-list">
            <div v-for="sale in heldTransactions" :key="sale.id" class="pos-held-item">
              <div>
                <strong>{{ sale.sale_number }}</strong>
                <small>{{ sale.customer_name || "Pelanggan umum" }}</small>
                <span>{{ formatCurrency(sale.total_amount) }}</span>
              </div>

              <button type="button" class="pos-button pos-button-primary-light" :disabled="processing || !!currentSale || cart.length > 0" @click="handleResumeSale(sale.id)">
                <i class="ti ti-player-play"></i>
                Lanjutkan
              </button>
            </div>
          </div>
        </section>
      </main>

      <!-- RIGHT COLUMN -->
      <aside class="pos-side-column">
        <!-- CUSTOMER -->
        <section ref="customerSection" class="pos-card">
          <div class="pos-card-header">
            <div>
              <h5>Pelanggan</h5>
              <p>Pilih pelanggan atau transaksi umum.</p>
            </div>
            <kbd>F2</kbd>
          </div>

          <CustomerAutocomplete
            :model-value="selectedCustomerId"
            label="Pelanggan"
            placeholder="Cari nama atau kode pelanggan..."
            :min-characters="2"
            :limit="15"
            :disabled="transactionLocked || processing"
            @update:model-value="handleCustomerChange"
          />
        </section>

        <!-- SUMMARY -->
        <section class="pos-card">
          <div class="pos-card-header">
            <div>
              <h5>Ringkasan Belanja</h5>
              <p>Perhitungan nilai transaksi.</p>
            </div>
          </div>

          <div class="pos-summary">
            <div class="pos-summary-row">
              <span>Subtotal</span>
              <strong>{{ formatCurrency(itemSubtotal) }}</strong>
            </div>

            <div class="pos-summary-row">
              <span>Diskon item</span>
              <strong>{{ formatCurrency(itemDiscountTotal) }}</strong>
            </div>

            <div class="pos-form-group">
              <label>Diskon transaksi</label>
              <div class="pos-input-group">
                <span>Rp</span>
                <input v-model.number="transactionDiscount" type="number" min="0" step="1" :disabled="transactionLocked || processing" placeholder="0" />
              </div>
            </div>

            <div class="pos-form-group">
              <label>Pajak</label>
              <div class="pos-input-group">
                <select v-model="taxMode" :disabled="transactionLocked || processing">
                  <option value="percent">%</option>
                  <option value="amount">Rp</option>
                </select>

                <input v-model.number="taxInput" type="number" min="0" step="0.01" :disabled="transactionLocked || processing" placeholder="0" />
              </div>
            </div>

            <div class="pos-summary-row">
              <span>Dasar pengenaan pajak</span>
              <strong>{{ formatCurrency(taxBase) }}</strong>
            </div>

            <div class="pos-summary-row">
              <span>
                Pajak
                {{ taxMode === "percent" ? `(${taxInput || 0}%)` : "(Rp)" }}
              </span>
              <strong>{{ formatCurrency(taxAmount) }}</strong>
            </div>
          </div>
        </section>

        <!-- ADDITIONAL CHARGES -->
        <section class="pos-card">
          <div class="pos-card-header">
            <div>
              <h5>Biaya Tambahan</h5>
              <p>Ongkir, donasi, kemasan, jasa, dan lainnya.</p>
            </div>

            <button type="button" class="pos-button pos-button-light" :disabled="transactionLocked || processing" @click="addCharge">
              <i class="ti ti-plus"></i>
              Add Row
            </button>
          </div>

          <div v-if="!additionalCharges.length" class="pos-muted pos-small">Belum ada biaya tambahan.</div>

          <div v-for="charge in additionalCharges" :key="charge.id" class="pos-charge-row">
            <select v-model="charge.type" class="pos-table-select" :disabled="transactionLocked || processing">
              <option v-for="type in CHARGE_TYPES" :key="type" :value="type">
                {{ type }}
              </option>
            </select>

            <input v-if="charge.type === 'Lainnya'" v-model="charge.description" type="text" class="pos-table-input" placeholder="Nama biaya" :disabled="transactionLocked || processing" />

            <input type="number" min="0" step="1" class="pos-table-input input-number" :value="charge.amount" placeholder="Nominal" :disabled="transactionLocked || processing" @input="updateChargeAmount(charge.id, $event)" />

            <button type="button" class="pos-remove-button" :disabled="transactionLocked || processing" @click="removeCharge(charge.id)">
              <i class="ti ti-x"></i>
            </button>
          </div>

          <div class="pos-summary-row mt-2">
            <span>Total biaya tambahan</span>
            <strong>{{ formatCurrency(additionalChargeTotal) }}</strong>
          </div>
        </section>

        <!-- PAYMENT -->
        <section class="pos-card pos-payment-card">
          <div class="pos-card-header">
            <div>
              <h5>Pembayaran</h5>
              <p>Pilih penyelesaian transaksi.</p>
            </div>
          </div>

          <div class="pos-payment-mode">
            <button type="button" :class="{ active: paymentMode === 'paid' }" :disabled="transactionLocked || processing" @click="handlePaymentModeChange('paid')">
              <i class="ti ti-circle-check"></i>
              Lunas
            </button>

            <button type="button" :class="{ active: paymentMode === 'credit' }" :disabled="transactionLocked || processing" @click="handlePaymentModeChange('credit')">
              <i class="ti ti-calendar-due"></i>
              Tempo
            </button>
          </div>

          <div v-if="paymentMode === 'credit'" class="pos-form-group">
            <label>Jenis pembayaran tempo</label>

            <select v-model="creditMode" class="pos-select" :disabled="transactionLocked || processing" @change="handleCreditModeChange(creditMode)">
              <option value="partial">Bayar sebagian sekarang</option>
              <option value="full">Seluruh tagihan menjadi piutang</option>
            </select>
          </div>

          <template v-if="canUsePaymentInput">
            <div class="pos-form-group">
              <label>Metode pembayaran</label>

              <select :value="paymentMethod" class="pos-select" :disabled="transactionLocked || processing" @change="handlePaymentMethod">
                <option v-for="method in PAYMENT_METHODS" :key="method.value" :value="method.value">
                  {{ method.label }}
                </option>
              </select>
            </div>

            <div v-if="paymentMethod === 'transfer'" class="pos-payment-info">
              <strong>Informasi rekening</strong>
              <span>{{ receiptAccount.bank || "Rekening belum dikonfigurasi" }}</span>
              <span>{{ receiptAccount.accountName }}</span>
              <div class="pos-account-number">
                {{ receiptAccount.accountNumber || "Nomor rekening belum diatur" }}
              </div>
              <small>Konfigurasi rekening akan diambil dari pengaturan perusahaan.</small>
            </div>

            <div v-if="paymentMethod === 'qris'" class="pos-payment-info text-center">
              <strong>Pembayaran QRIS</strong>
              <div v-if="qrisImage" class="pos-qris">
                <img :src="qrisImage" alt="QRIS pembayaran" />
              </div>
              <div v-else class="pos-qris-placeholder">
                <i class="ti ti-qrcode"></i>
                <span>QRIS belum dikonfigurasi</span>
              </div>
              <small>QRIS dinamis memerlukan integrasi penyedia pembayaran.</small>
            </div>

            <div v-if="paymentMethod === 'other'" class="pos-payment-info">Metode pembayaran lainnya memerlukan konfigurasi lanjutan.</div>

            <div class="pos-form-group">
              <label>Nominal dibayar</label>

              <div class="pos-input-group pos-payment-input">
                <span>Rp</span>
                <input ref="paymentInput" :value="paymentAmount" type="number" min="0" step="1" :disabled="transactionLocked || processing" placeholder="Nominal pembayaran" @input="handlePaymentAmount" />
              </div>
            </div>

            <div class="pos-cash-buttons">
              <button type="button" class="cash-exact" :disabled="transactionLocked || processing" @click="setExactPayment">Uang Pas</button>

              <button v-for="amount in [1000, 2000, 5000, 10000, 20000, 50000, 100000]" :key="amount" type="button" :disabled="transactionLocked || processing" @click="addCashAmount(amount)">
                {{ amount.toLocaleString("id-ID") }}
              </button>

              <button type="button" class="cash-reset" :disabled="transactionLocked || processing" @click="resetPaymentAmount">Reset</button>
            </div>
          </template>

          <div v-else class="pos-credit-notice">
            <i class="ti ti-info-circle"></i>
            Seluruh tagihan akan dicatat sebagai piutang. Tidak ada pembayaran saat transaksi.
          </div>

          <div v-if="paymentMode === 'credit'" class="pos-form-group">
            <label>Tanggal rencana pembayaran</label>
            <input v-model="dueDate" type="date" class="pos-select" :disabled="transactionLocked || processing" />
          </div>

          <div class="pos-payment-result">
            <div class="pos-summary-row">
              <span>Nominal diterima</span>
              <strong>{{ formatCurrency(paidNow) }}</strong>
            </div>

            <div v-if="paymentMode === 'paid'" class="pos-summary-row">
              <span>Kembalian</span>
              <strong class="text-success">
                {{ formatCurrency(changeAmount) }}
              </strong>
            </div>

            <div v-if="paymentMode === 'credit'" class="pos-summary-row">
              <span>Sisa piutang</span>
              <strong class="text-danger">
                {{ formatCurrency(receivableAmount) }}
              </strong>
            </div>
          </div>
        </section>

        <!-- GENERAL NOTES -->
        <section class="pos-card">
          <div class="pos-form-group mb-0">
            <label>Catatan umum transaksi</label>
            <textarea v-model="notes" rows="2" class="pos-textarea" placeholder="Catatan untuk keseluruhan transaksi..." :disabled="transactionLocked || processing"></textarea>
          </div>
        </section>

        <!-- GRAND TOTAL -->
        <section class="pos-total-card">
          <div class="pos-total-label">TOTAL TAGIHAN</div>
          <div class="pos-total-value">
            {{ formatCurrency(grandTotal) }}
          </div>

          <div class="pos-total-detail">
            <span>{{ cart.length }} jenis barang</span>
            <span>{{ formatCurrency(additionalChargeTotal) }} biaya tambahan</span>
          </div>

          <button v-if="!isDraft && !isPosted" type="button" class="pos-button pos-button-primary pos-submit" :disabled="!canCreateDraft || processing" @click="handleCreateDraft">
            <span v-if="processing" class="pos-spinner"></span>
            <i v-else class="ti ti-device-floppy"></i>
            Buat Draft Penjualan
            <kbd>F12</kbd>
          </button>

          <button v-else-if="isDraft" type="button" class="pos-button pos-button-success pos-submit" :disabled="!canPostSale || processing" @click="handlePostSale">
            <span v-if="processing" class="pos-spinner"></span>
            <i v-else class="ti ti-check"></i>
            Posting Penjualan
            <kbd>F12</kbd>
          </button>

          <button v-else-if="isPosted" type="button" class="pos-button pos-button-primary pos-submit" :disabled="processing" @click="handleNewTransaction">
            <i class="ti ti-plus"></i>
            Transaksi Baru
            <kbd>F11</kbd>
          </button>

          <div v-if="isDraft" class="pos-draft-note">Draft tersimpan. Periksa kembali sebelum posting.</div>
        </section>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.pos-page {
  --pos-primary: #2563eb;
  --pos-primary-soft: #eff6ff;
  --pos-success: #15803d;
  --pos-success-soft: #f0fdf4;
  --pos-warning: #b45309;
  --pos-warning-soft: #fffbeb;
  --pos-danger: #dc2626;
  --pos-border: #e5e7eb;
  --pos-muted: #6b7280;
  --pos-text: #111827;
  --pos-surface: #ffffff;

  color: var(--pos-text);
  padding-bottom: 24px;
}

.pos-header,
.pos-header-actions,
.pos-card-header,
.pos-shortcut-heading,
.pos-posted-banner,
.pos-held-item,
.pos-summary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.pos-header {
  margin-bottom: 18px;
}

.pos-eyebrow {
  color: var(--pos-primary);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 1.4px;
  margin-bottom: 3px;
}

.pos-heading {
  font-size: 21px;
  font-weight: 750;
  margin: 0;
}

.pos-header-actions {
  flex-wrap: wrap;
}

.pos-card {
  background: var(--pos-surface);
  border: 1px solid var(--pos-border);
  border-radius: 11px;
  margin-bottom: 14px;
  padding: 16px;
  box-shadow: 0 2px 7px rgb(15 23 42 / 3%);
}

.pos-card-header {
  margin-bottom: 14px;
}

.pos-card-header h5 {
  font-size: 14px;
  font-weight: 750;
  margin: 0 0 3px;
}

.pos-card-header p {
  color: var(--pos-muted);
  font-size: 11px;
  margin: 0;
}

.pos-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.65fr) minmax(340px, 0.85fr);
  gap: 16px;
  align-items: start;
}

.pos-main-column,
.pos-side-column {
  min-width: 0;
}

.pos-button {
  align-items: center;
  border: 1px solid transparent;
  border-radius: 7px;
  display: inline-flex;
  font-size: 12px;
  font-weight: 650;
  gap: 7px;
  justify-content: center;
  min-height: 34px;
  padding: 7px 11px;
  transition: 0.15s ease;
  white-space: nowrap;
}

.pos-button:disabled,
.pos-icon-button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.pos-button-primary {
  background: var(--pos-primary);
  color: white;
}

.pos-button-primary:hover:not(:disabled) {
  background: #1d4ed8;
}

.pos-button-success {
  background: var(--pos-success);
  color: white;
}

.pos-button-warning {
  background: var(--pos-warning-soft);
  border-color: #fcd34d;
  color: var(--pos-warning);
}

.pos-button-light {
  background: #f9fafb;
  border-color: var(--pos-border);
  color: #374151;
}

.pos-button-danger-light {
  background: #fef2f2;
  border-color: #fecaca;
  color: var(--pos-danger);
}

.pos-button-primary-light {
  background: var(--pos-primary-soft);
  color: var(--pos-primary);
}

.pos-icon-button {
  align-items: center;
  background: #f9fafb;
  border: 1px solid var(--pos-border);
  border-radius: 7px;
  color: #4b5563;
  display: inline-flex;
  height: 34px;
  justify-content: center;
  width: 34px;
}

.pos-icon-button:hover {
  background: #f3f4f6;
}

.pos-status {
  border-radius: 30px;
  font-size: 11px;
  font-weight: 700;
  padding: 6px 10px;
}

.status-draft {
  background: var(--pos-warning-soft);
  color: var(--pos-warning);
}

.status-posted {
  background: var(--pos-success-soft);
  color: var(--pos-success);
}

.pos-search {
  align-items: center;
  background: #f9fafb;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  display: flex;
  gap: 10px;
  padding: 5px 6px 5px 12px;
}

.pos-search > i {
  color: var(--pos-primary);
  font-size: 20px;
}

.pos-search input {
  background: transparent;
  border: 0;
  flex: 1;
  font-size: 13px;
  min-width: 0;
  outline: 0;
  padding: 8px 0;
}

.pos-search-results {
  border: 1px solid var(--pos-border);
  border-radius: 8px;
  margin-top: 10px;
  max-height: 240px;
  overflow-y: auto;
}

.pos-product-result {
  align-items: center;
  background: white;
  border: 0;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 11px 12px;
  text-align: left;
  width: 100%;
}

.pos-product-result:hover:not(:disabled) {
  background: #f8fafc;
}

.pos-product-info,
.pos-product-price {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}

.pos-product-info strong {
  font-size: 12px;
}

.pos-product-info small,
.pos-product-price small {
  color: var(--pos-muted);
  font-size: 10px;
}

.pos-product-price {
  flex-shrink: 0;
  text-align: right;
}

.pos-product-price strong {
  color: var(--pos-primary);
  font-size: 12px;
}

.pos-cart-card {
  padding: 0;
  overflow: hidden;
}

.pos-cart-card > .pos-card-header {
  padding: 16px 16px 0;
}

.pos-table-wrapper {
  max-height: 430px;
  overflow: auto;
  width: 100%;
}

.pos-table {
  border-collapse: separate;
  border-spacing: 0;
  font-size: 11px;
  min-width: 1050px;
  width: 100%;
}

.pos-table th {
  background: #f8fafc;
  border-bottom: 1px solid var(--pos-border);
  color: #64748b;
  font-size: 10px;
  font-weight: 750;
  padding: 10px 8px;
  position: sticky;
  text-align: left;
  text-transform: uppercase;
  top: 0;
  white-space: nowrap;
  z-index: 2;
}

.pos-table td {
  border-bottom: 1px solid #f1f5f9;
  padding: 8px;
  vertical-align: middle;
}

.pos-table tbody tr:hover {
  background: #fafcff;
}

.pos-table .col-no {
  color: var(--pos-muted);
  text-align: center;
  width: 35px;
}

.pos-barcode-cell {
  color: #64748b;
  font-size: 10px;
  white-space: nowrap;
}

.pos-item-name {
  min-width: 150px;
}

.pos-item-name strong,
.pos-item-name small {
  display: block;
}

.pos-item-name strong {
  font-size: 11px;
}

.pos-item-name small {
  color: var(--pos-muted);
  font-size: 10px;
  margin-top: 2px;
}

.pos-table-input,
.pos-table-select,
.pos-select,
.pos-textarea {
  background: white;
  border: 1px solid #dbe1e8;
  border-radius: 5px;
  color: #1f2937;
  font-size: 11px;
  min-height: 30px;
  padding: 5px 7px;
  width: 100%;
}

.pos-table-input:focus,
.pos-table-select:focus,
.pos-select:focus,
.pos-textarea:focus {
  border-color: var(--pos-primary);
  box-shadow: 0 0 0 2px rgb(37 99 235 / 10%);
  outline: none;
}

.pos-table-input.input-number {
  min-width: 68px;
  text-align: right;
}

.pos-table-select {
  min-width: 65px;
}

.pos-remove-button {
  align-items: center;
  background: transparent;
  border: 0;
  color: #ef4444;
  display: inline-flex;
  font-size: 15px;
  justify-content: center;
  padding: 5px;
}

.pos-cart-footer {
  align-items: center;
  background: #f8fafc;
  color: var(--pos-muted);
  display: flex;
  font-size: 11px;
  justify-content: space-between;
  padding: 11px 15px;
}

.pos-cart-footer strong {
  color: var(--pos-text);
}

.pos-empty {
  align-items: center;
  color: var(--pos-muted);
  display: flex;
  flex-direction: column;
  gap: 7px;
  justify-content: center;
  min-height: 180px;
  padding: 20px;
  text-align: center;
}

.pos-empty > i {
  color: #cbd5e1;
  font-size: 35px;
}

.pos-empty strong {
  color: #475569;
  font-size: 13px;
}

.pos-empty span {
  font-size: 11px;
}

.pos-empty.compact {
  min-height: 70px;
  font-size: 11px;
}

.pos-held-list {
  display: flex;
  flex-direction: column;
}

.pos-held-item {
  border-top: 1px solid #f1f5f9;
  padding: 10px 0;
}

.pos-held-item > div {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.pos-held-item strong {
  font-size: 12px;
}

.pos-held-item small {
  color: var(--pos-muted);
  font-size: 10px;
}

.pos-held-item span {
  font-size: 11px;
  font-weight: 700;
}

.pos-summary {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.pos-summary-row {
  font-size: 11px;
}

.pos-summary-row > span {
  color: #64748b;
}

.pos-summary-row > strong {
  color: #1f2937;
  font-size: 12px;
  font-variant-numeric: tabular-nums;
}

.pos-form-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
  margin-bottom: 12px;
}

.pos-form-group label {
  color: #475569;
  font-size: 11px;
  font-weight: 650;
}

.pos-input-group {
  align-items: center;
  border: 1px solid #dbe1e8;
  border-radius: 6px;
  display: flex;
  overflow: hidden;
}

.pos-input-group > span,
.pos-input-group > select {
  background: #f8fafc;
  border: 0;
  border-right: 1px solid #dbe1e8;
  color: #475569;
  font-size: 11px;
  min-height: 34px;
  padding: 7px 10px;
}

.pos-input-group > input {
  border: 0;
  flex: 1;
  font-size: 12px;
  min-width: 0;
  outline: 0;
  padding: 8px;
  text-align: right;
}

.pos-charge-row {
  align-items: center;
  display: grid;
  gap: 6px;
  grid-template-columns: minmax(75px, 0.9fr) minmax(75px, 1fr) 28px;
  margin-bottom: 7px;
}

.pos-payment-mode {
  background: #f1f5f9;
  border-radius: 8px;
  display: grid;
  gap: 5px;
  grid-template-columns: 1fr 1fr;
  margin-bottom: 14px;
  padding: 4px;
}

.pos-payment-mode button {
  align-items: center;
  background: transparent;
  border: 0;
  border-radius: 6px;
  color: #64748b;
  display: flex;
  font-size: 12px;
  font-weight: 700;
  gap: 7px;
  justify-content: center;
  padding: 10px;
}

.pos-payment-mode button.active {
  background: white;
  box-shadow: 0 1px 4px rgb(15 23 42 / 10%);
  color: var(--pos-primary);
}

.pos-payment-info {
  background: #f8fafc;
  border: 1px solid var(--pos-border);
  border-radius: 7px;
  display: flex;
  flex-direction: column;
  font-size: 11px;
  gap: 5px;
  margin-bottom: 12px;
  padding: 11px;
}

.pos-payment-info small {
  color: var(--pos-muted);
  font-size: 10px;
}

.pos-account-number {
  color: var(--pos-primary);
  font-size: 16px;
  font-weight: 800;
  letter-spacing: 1px;
}

.pos-qris {
  display: flex;
  justify-content: center;
  padding: 8px;
}

.pos-qris img {
  max-height: 180px;
  max-width: 180px;
  object-fit: contain;
}

.pos-qris-placeholder {
  align-items: center;
  color: var(--pos-muted);
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px;
}

.pos-qris-placeholder i {
  font-size: 45px;
}

.pos-cash-buttons {
  display: grid;
  gap: 5px;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  margin: 8px 0 14px;
}

.pos-cash-buttons button {
  background: #f8fafc;
  border: 1px solid var(--pos-border);
  border-radius: 5px;
  color: #475569;
  font-size: 10px;
  font-weight: 650;
  min-height: 29px;
  padding: 4px;
}

.pos-cash-buttons button:hover:not(:disabled) {
  background: var(--pos-primary-soft);
  border-color: #bfdbfe;
  color: var(--pos-primary);
}

.pos-cash-buttons .cash-exact {
  background: var(--pos-success-soft);
  border-color: #bbf7d0;
  color: var(--pos-success);
  grid-column: span 2;
}

.pos-cash-buttons .cash-reset {
  background: #fef2f2;
  border-color: #fecaca;
  color: var(--pos-danger);
}

.pos-credit-notice {
  background: var(--pos-warning-soft);
  border: 1px solid #fde68a;
  border-radius: 7px;
  color: #92400e;
  display: flex;
  font-size: 11px;
  gap: 8px;
  line-height: 1.5;
  margin-bottom: 13px;
  padding: 11px;
}

.pos-payment-result {
  background: #f8fafc;
  border-radius: 7px;
  display: flex;
  flex-direction: column;
  gap: 9px;
  margin-top: 12px;
  padding: 12px;
}

.pos-total-card {
  background: #172554;
  border-radius: 11px;
  color: white;
  margin-bottom: 14px;
  padding: 18px;
}

.pos-total-label {
  color: #bfdbfe;
  font-size: 10px;
  font-weight: 750;
  letter-spacing: 1.3px;
}

.pos-total-value {
  font-size: clamp(25px, 3vw, 34px);
  font-weight: 800;
  letter-spacing: -1px;
  margin: 5px 0;
  overflow-wrap: anywhere;
}

.pos-total-detail {
  color: #cbd5e1;
  display: flex;
  font-size: 10px;
  justify-content: space-between;
  margin-bottom: 15px;
}

.pos-submit {
  font-size: 13px;
  min-height: 44px;
  width: 100%;
}

.pos-submit kbd {
  margin-left: auto;
}

.pos-draft-note {
  color: #fde68a;
  font-size: 10px;
  margin-top: 9px;
  text-align: center;
}

.pos-message {
  align-items: center;
  border-radius: 7px;
  display: flex;
  font-size: 12px;
  gap: 9px;
  margin-bottom: 12px;
  padding: 11px 13px;
}

.pos-message button {
  background: transparent;
  border: 0;
  margin-left: auto;
}

.message-success {
  background: var(--pos-success-soft);
  color: var(--pos-success);
}

.message-error {
  background: #fef2f2;
  color: var(--pos-danger);
}

.pos-posted-banner {
  background: var(--pos-success-soft);
  border: 1px solid #bbf7d0;
  border-radius: 9px;
  margin-bottom: 14px;
  padding: 13px 15px;
}

.pos-posted-banner strong {
  color: var(--pos-success);
  font-size: 13px;
}

.pos-muted {
  color: var(--pos-muted);
  font-size: 11px;
}

.pos-small {
  font-size: 10px;
}

.pos-shortcut-panel {
  background: white;
  border: 1px solid var(--pos-border);
  border-radius: 8px;
  box-shadow: 0 8px 25px rgb(15 23 42 / 8%);
  margin-bottom: 14px;
  padding: 13px;
}

.pos-shortcut-heading {
  margin-bottom: 10px;
}

.pos-shortcut-grid {
  display: grid;
  gap: 9px 18px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.pos-shortcut-grid span {
  align-items: center;
  color: #475569;
  display: flex;
  font-size: 11px;
  gap: 8px;
}

kbd {
  background: #f1f5f9;
  border: 1px solid #dbe1e8;
  border-radius: 4px;
  color: #475569;
  font-family: inherit;
  font-size: 9px;
  font-weight: 750;
  padding: 3px 5px;
}

@media (min-width: 1500px) {
  .pos-layout {
    grid-template-columns: minmax(0, 1.8fr) minmax(380px, 0.8fr);
  }

  .pos-table-wrapper {
    max-height: 520px;
  }
}

@media (max-width: 1199px) {
  .pos-layout {
    grid-template-columns: minmax(0, 1fr);
  }

  .pos-side-column {
    display: grid;
    gap: 0 14px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: start;
  }

  .pos-total-card {
    align-self: start;
  }
}

@media (max-width: 767px) {
  .pos-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .pos-side-column {
    display: block;
  }

  .pos-heading {
    font-size: 18px;
  }

  .pos-card {
    padding: 12px;
  }

  .pos-cart-card {
    padding: 0;
  }

  .pos-cart-card > .pos-card-header {
    padding: 12px 12px 0;
  }

  .pos-shortcut-grid {
    grid-template-columns: 1fr;
  }

  .pos-total-value {
    font-size: 27px;
  }
}
</style>
