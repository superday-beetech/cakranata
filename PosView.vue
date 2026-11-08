<script setup lang="ts">
import "../styles/pos.css";
import { usePosSound } from "../composables/usePosSound";
import { usePosDelight } from "../composables/usePosDelight";
import { useRouter } from "vue-router";

const router = useRouter();
const { soundEnabled, play: playPosSound, toggle: togglePosSound } = usePosSound();

import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { usePos } from "../composables/usePos";
import type { PaymentMethod, ProductSearchResult, PosAdditionalCharge, Sale, SettlementMode, DiscountMode, TaxMode } from "../types/sales";

import productService from "@/modules/pharmacy/master/services/productService";
import CustomerAutocomplete from "@/modules/pharmacy/master/components/CustomerAutocomplete.vue";

import PosProductSearch from "../components/pos/PosProductSearch.vue";
import PosCart from "../components/pos/PosCart.vue";
import PosSummary from "../components/pos/PosSummary.vue";
import PosDiscountInput from "../components/pos/PosDiscountInput.vue";
import PosTaxInput from "../components/pos/PosTaxInput.vue";
import PosAdditionalCharges from "../components/pos/PosAdditionalCharges.vue";
import PosPayment from "../components/pos/PosPayment.vue";
import PosTotalPanel from "../components/pos/PosTotalPanel.vue";
import PosHeldTransactions from "../components/pos/PosHeldTransactions.vue";
import PosTransactionNotes from "../components/pos/PosTransactionNotes.vue";
import PosShortcutHelp from "../components/pos/PosShortcutHelp.vue";
import PosHeader from "../components/pos/PosHeader.vue";
import PosPanel from "../components/pos/PosPanel.vue";

defineOptions({
  name: "PosView",
});
const goToDashboard = () => {
  router.push("/pharmacy/sales/dashboard");
};
/* --------------------------------------------------------------------------
 * TYPES
 * ----------------------------------------------------------------------- */

/* --------------------------------------------------------------------------
 * HELPERS
 * ----------------------------------------------------------------------- */

function toNumber(value: unknown): number {
  const number = Number(value);
  return Number.isFinite(number) ? number : 0;
}

function formatCurrency(value: number): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(toNumber(value));
}

function getErrorMessage(error: unknown): string {
  const err = error as {
    response?: {
      data?: {
        message?: string | string[];
      };
    };
    message?: string;
  };

  const message = err?.response?.data?.message;

  if (Array.isArray(message)) {
    return message.join(", ");
  }

  return message || err?.message || "Terjadi kesalahan.";
}

/* --------------------------------------------------------------------------
 * PRODUCT SEARCH
 * ----------------------------------------------------------------------- */

async function searchProducts(searchKeyword: string): Promise<ProductSearchResult[]> {
  const response = await productService.getAll({
    page: 1,
    per_page: 20,
    search: searchKeyword,
    is_active: 1,
  });

  if (response.data.error) {
    const apiMessage = response.data.message;

    throw new Error(Array.isArray(apiMessage) ? apiMessage.join(", ") : apiMessage || "Gagal mengambil data produk.");
  }

  const products = response.data.data?.items ?? [];

  return products.map((product) => ({
    id: product.id,
    sku: product.sku ?? "",
    barcode: product.barcode ?? null,
    name: product.name,
    unit_name: product.unit_name ?? undefined,
    selling_price: Number(product.selling_price),
    // available_stock: product.available_stock,
    is_active: Number(product.is_active) === 1,
  }));
}

/* --------------------------------------------------------------------------
 * POS COMPOSABLE
 * ----------------------------------------------------------------------- */

const {
  cart,
  keyword,
  searchResults,
  selectedCustomerId,
  paymentMethod,
  paymentAmount,
  notes,

  transactionDiscountMode,
  transactionDiscountValue,
  taxMode,
  taxValue,
  additionalCharges,
  settlementMode,
  dueDate,
  paymentReferenceNumber,

  subtotal,
  itemDiscount,
  transactionDiscount,
  discountTotal,
  dpp,
  taxAmount,
  additionalChargesTotal,
  grandTotal,

  appliedAmount,
  changeAmount,
  remainingAmount,

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
  updateItemDiscount,
  updateItemNotes,
  removeProduct,
  clearCart,
  setCustomer,
  setPaymentAmount,
  setPaymentMethod,
  setTransactionDiscount,
  setTax,
  addAdditionalCharge,
  updateAdditionalCharge,
  removeAdditionalCharge,
  setSettlementMode,
  setDueDate,
  setPaymentReferenceNumber,
  payExact,
  resetPaymentAmount,
  //   payExactAmount,
  createDraft,
  postCurrentSale,
  newTransaction,

  heldTransactions,
  loadingHeldTransactions,
  loadHeldTransactions,
  holdCurrentSale,
  resumeHeldTransaction,
  paymentConfirmed,
  isPaymentValid,
  dueDateError,
  setPaymentConfirmed,
} = usePos({
  searchProducts,
});

/* Sentuhan visual (jam, sapaan, kedip keranjang). Harus setelah usePos karena membaca subtotal. */
const { clockTime, greeting, cartFlash } = usePosDelight(subtotal);

/* --------------------------------------------------------------------------
 * LOCAL UI STATE
 * ----------------------------------------------------------------------- */

const message = ref("");
const errorMessage = ref("");
const shortcutHelp = ref(false);

type AdjustTab = "discount" | "charges" | "notes" | "held";
const adjustTab = ref<AdjustTab>("discount");

const saleStatus = computed(() => {
  if (isPosted.value) return "Selesai";
  if (isDraft.value) return "Draft";
  return "Baru";
});

const barcodeSection = ref<HTMLElement | null>(null);
const customerSection = ref<HTMLElement | null>(null);
const cartSection = ref<HTMLElement | null>(null);
const discountSection = ref<HTMLElement | null>(null);
const chargesSection = ref<HTMLElement | null>(null);
const paymentSection = ref<HTMLElement | null>(null);
const paymentInput = ref<HTMLElement | null>(null);

const bankName = ref("");
const accountNumber = ref("");
const accountHolder = ref("");
const qrisImageUrl = ref<string | null>(null);
// const selectedPaymentMethod = computed<PaymentMethod>(() => paymentMethod.value ?? "cash");
const settlementLabel = computed(() => {
  switch (settlementMode.value) {
    case "partial":
      return "Tempo - Bayar Sebagian";
    case "receivable":
      return "Tempo - Seluruhnya Piutang";
    default:
      return "Lunas";
  }
});

/* --------------------------------------------------------------------------
 * MESSAGES
 * ----------------------------------------------------------------------- */

let messageTimer: ReturnType<typeof setTimeout> | undefined;

function clearMessages(): void {
  clearTimeout(messageTimer);
  message.value = "";
  errorMessage.value = "";
}

function showError(error: unknown): void {
  errorMessage.value = getErrorMessage(error);
  message.value = "";
}

function showSuccess(text: string): void {
  clearTimeout(messageTimer);
  message.value = text;
  errorMessage.value = "";
  messageTimer = setTimeout(() => {
    message.value = "";
  }, 5000);
}

/* --------------------------------------------------------------------------
 * PRODUCT HANDLERS
 * ----------------------------------------------------------------------- */

let searchTimer: ReturnType<typeof setTimeout> | undefined;

function cancelSearchTimer(): void {
  if (searchTimer) {
    clearTimeout(searchTimer);
    searchTimer = undefined;
  }
}

watch(keyword, (value) => {
  cancelSearchTimer();

  if (!value.trim()) {
    clearSearch();
    return;
  }

  searchTimer = setTimeout(() => {
    void handleSearch();
  }, 300);
});

async function handleSearch(): Promise<void> {
  if (!keyword.value.trim() || transactionLocked.value) {
    return;
  }

  try {
    await searchProduct();
  } catch (error) {
    showError(error);
  }
}

function handleAddProduct(product: ProductSearchResult): void {
  const duplicate = cart.value.some((item) => item.product_id === product.id);

  try {
    clearMessages();
    addProduct(product);
    void playPosSound(duplicate ? "duplicate" : "success");
    clearSearch();
    focusBarcode();
  } catch (error) {
    void playPosSound("warning");
    showError(error);
  }
}

async function handleBarcodeEnter(): Promise<void> {
  const barcode = keyword.value.trim();

  if (!barcode || transactionLocked.value) {
    return;
  }

  try {
    const products = await searchProducts(barcode);

    const product = products.find((item) => item.barcode?.trim().toLowerCase() === barcode.toLowerCase());

    if (!product) {
      await searchProduct();
      errorMessage.value = "Barcode tidak ditemukan.";
      void playPosSound("warning");
      return;
    }

    handleAddProduct(product);
  } catch (error) {
    void playPosSound("error");
    showError(error);
  }
}

function focusBarcode(): void {
  const input = barcodeSection.value?.querySelector<HTMLInputElement>("input");

  input?.focus();
  input?.select();
}

/* --------------------------------------------------------------------------
 * CART HANDLERS
 * ----------------------------------------------------------------------- */

function handleQuantityChange(productId: number, value: number): void {
  try {
    updateQuantity(productId, Math.max(0, value));
  } catch (error) {
    showError(error);
  }
}

function handlePriceChange(productId: number, value: number): void {
  try {
    updateUnitPrice(productId, Math.max(0, value));
  } catch (error) {
    showError(error);
  }
}

function handleDiscountModeChange(productId: number, mode: DiscountMode): void {
  const item = cart.value.find((row) => row.product_id === productId);
  if (!item) return;

  try {
    updateItemDiscount(productId, mode, toNumber(item.discount_value ?? item.discount_amount));
  } catch (error) {
    showError(error);
  }
}

function handleDiscountChange(productId: number, value: number): void {
  const item = cart.value.find((row) => row.product_id === productId);
  if (!item) return;

  try {
    updateItemDiscount(productId, item.discount_mode ?? "amount", Math.max(0, value));
  } catch (error) {
    showError(error);
  }
}

function handleItemNoteChange(productId: number, value: string): void {
  try {
    updateItemNotes(productId, value);
  } catch (error) {
    showError(error);
  }
}

function handleRemoveProduct(productId: number): void {
  try {
    removeProduct(productId);
  } catch (error) {
    showError(error);
  }
}

function handleClearCart(): void {
  try {
    clearCart();
  } catch (error) {
    showError(error);
  }
}

/* --------------------------------------------------------------------------
 * CUSTOMER
 * ----------------------------------------------------------------------- */

function handleCustomerChange(customerId: number | null): void {
  setCustomer(customerId);
}

/* --------------------------------------------------------------------------
 * TRANSACTION DISCOUNT AND TAX
 * ----------------------------------------------------------------------- */

function handleTransactionDiscountMode(mode: DiscountMode): void {
  setTransactionDiscount(mode, transactionDiscountValue.value);
}

function handleTransactionDiscountValue(value: number): void {
  setTransactionDiscount(transactionDiscountMode.value, Math.max(0, value));
}

function handleTaxMode(mode: TaxMode): void {
  setTax(mode, taxValue.value);
}

function handleTaxValue(value: number): void {
  setTax(taxMode.value, Math.max(0, value));
}

/* --------------------------------------------------------------------------
 * ADDITIONAL CHARGES
 * ----------------------------------------------------------------------- */

let chargeSequence = 0;

function addCharge(): void {
  chargeSequence++;

  try {
    addAdditionalCharge({
      id: `charge-${chargeSequence}`,
      type: "other",
      label: "Biaya lainnya",
      description: "",
      amount: 0,
    });
  } catch (error) {
    showError(error);
  }
}

function updateCharge(id: string, field: "type" | "description" | "amount", value: string | number): void {
  try {
    switch (field) {
      case "amount":
        updateAdditionalCharge(id, { amount: Math.max(0, Number(value) || 0) });
        break;
      case "description":
        updateAdditionalCharge(id, { description: String(value) });
        break;
      case "type":
        updateAdditionalCharge(id, { type: value as PosAdditionalCharge["type"] });
        break;
    }
  } catch (error) {
    showError(error);
  }
}

function removeCharge(id: string): void {
  try {
    removeAdditionalCharge(id);
  } catch (error) {
    showError(error);
  }
}

/* --------------------------------------------------------------------------
 * PAYMENT
 * ----------------------------------------------------------------------- */

function handleSettlementMode(mode: SettlementMode): void {
  setSettlementMode(mode);
}

function handlePaymentAmount(value: number): void {
  setPaymentAmount(Math.max(0, value));
}

function handlePaymentMethod(method: PaymentMethod): void {
  setPaymentMethod(method);
}

function handleDueDate(value: string): void {
  setDueDate(value);
}

function handleReferenceNumber(value: string): void {
  setPaymentReferenceNumber(value);
}

function handleExactPayment(): void {
  if (settlementMode.value !== "paid") return;

  payExact();
}

function handleResetPayment(): void {
  resetPaymentAmount();
}

function handlePaymentConfirmation(value: boolean): void {
  setPaymentConfirmed(value);
}

/* --------------------------------------------------------------------------
 * TRANSACTION ACTIONS
 * ----------------------------------------------------------------------- */

/**
 * Diskon transaksi dan pajak sudah dipetakan ke payload backend.
 * Biaya tambahan dan settlement tempo belum didukung oleh API lama.
 */
const hasUnsupportedDraftValues = computed(() => {
  return additionalChargesTotal.value > 0;
});

async function handleCreateDraft(): Promise<void> {
  clearMessages();

  if (hasUnsupportedDraftValues.value) {
    errorMessage.value = "Biaya tambahan belum didukung oleh payload backend penjualan saat ini.";
    return;
  }

  try {
    await createDraft();
    showSuccess("Draft penjualan berhasil dibuat.");
  } catch (error) {
    showError(error);
  }
}

async function handlePostSale(): Promise<void> {
  clearMessages();

  if (hasUnsupportedDraftValues.value) {
    errorMessage.value = "Biaya tambahan belum didukung oleh backend penjualan.";
    return;
  }

  if (!canPostSale.value || !isPaymentValid.value) {
    errorMessage.value = "Pembayaran belum valid. Periksa metode, nominal, jatuh tempo, dan konfirmasi pembayaran.";
    return;
  }

  try {
    await postCurrentSale();

    void playPosSound("transaction");
    showSuccess("Penjualan berhasil diposting.");

    await loadHeldTransactions();
    focusBarcode();
  } catch (error) {
    showError(error);
  }
}

async function handleHoldSale(): Promise<void> {
  clearMessages();

  try {
    await holdCurrentSale();
    await loadHeldTransactions();
    showSuccess("Transaksi berhasil ditahan.");
  } catch (error) {
    showError(error);
  }
}

async function handleResumeSale(sale: Sale): Promise<void> {
  clearMessages();

  try {
    await resumeHeldTransaction(sale.id);
    showSuccess(`Transaksi ${sale.sale_number} berhasil dilanjutkan.`);
  } catch (error) {
    showError(error);
  }
}

async function handleNewTransaction(): Promise<void> {
  if (!isPosted.value) return;

  try {
    newTransaction();

    clearMessages();
    focusBarcode();
  } catch (error) {
    showError(error);
  }
}

async function handleRefreshHeld(): Promise<void> {
  try {
    await loadHeldTransactions();
  } catch (error) {
    showError(error);
  }
}

/* --------------------------------------------------------------------------
 * KEYBOARD SHORTCUTS
 * ----------------------------------------------------------------------- */

function isTypingTarget(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) return false;

  return target.isContentEditable || ["INPUT", "TEXTAREA", "SELECT"].includes(target.tagName);
}

function focusSection(section: HTMLElement | null): void {
  const input = section?.querySelector<HTMLElement>("input:not([disabled]), button:not([disabled]), select:not([disabled])");

  input?.focus();
}

async function openAdjustTab(tab: AdjustTab, section: HTMLElement | null): Promise<void> {
  adjustTab.value = tab;
  await nextTick();
  focusSection(section);
}

function focusPaymentAmount(): void {
  const amount = paymentInput.value?.querySelector<HTMLInputElement>("#pos-payment-amount");

  if (amount && !amount.disabled) {
    amount.focus();
    amount.select();
    return;
  }

  focusSection(paymentInput.value);
}

function handleKeyboard(event: KeyboardEvent): void {
  if (event.ctrlKey || event.altKey || event.metaKey || isTypingTarget(event.target)) {
    return;
  }

  switch (event.key) {
    case "Escape":
      shortcutHelp.value = false;
      break;

    case "F1":
      event.preventDefault();
      focusBarcode();
      break;

    case "F2":
      event.preventDefault();
      focusSection(customerSection.value);
      break;

    case "F3":
      event.preventDefault();
      focusSection(cartSection.value);
      break;

    case "F4":
      event.preventDefault();
      void openAdjustTab("discount", discountSection.value);
      break;

    case "F5":
      event.preventDefault();
      void openAdjustTab("charges", chargesSection.value);
      break;

    case "F6":
      event.preventDefault();
      focusSection(paymentSection.value);
      break;

    case "F7":
      event.preventDefault();
      focusSection(paymentSection.value);
      break;

    case "F8":
      event.preventDefault();
      focusPaymentAmount();
      break;

    case "F9":
      event.preventDefault();
      handleExactPayment();
      break;

    case "F10":
      event.preventDefault();

      if (isDraft.value) {
        void handleHoldSale();
      }

      break;

    case "F11":
      event.preventDefault();
      void handleNewTransaction();
      break;

    case "F12":
      event.preventDefault();

      if (isDraft.value) {
        void handlePostSale();
      } else if (!isPosted.value) {
        void handleCreateDraft();
      }

      break;
  }
}

/* --------------------------------------------------------------------------
 * LIFECYCLE
 * ----------------------------------------------------------------------- */

onMounted(async () => {
  window.addEventListener("keydown", handleKeyboard);

  try {
    await loadHeldTransactions();
  } catch (error) {
    showError(error);
  }

  focusBarcode();
});

onBeforeUnmount(() => {
  cancelSearchTimer();
  clearTimeout(messageTimer);
  window.removeEventListener("keydown", handleKeyboard);
});
</script>

<template>
  <div class="pos-page">
    <!-- HEADER -->
    <PosHeader
      :sale-number="currentSale?.sale_number"
      :status="saleStatus"
      :processing="processing"
      :loading-held="loadingHeldTransactions"
      :can-hold="isDraft"
      :can-new="isPosted"
      :clock-time="clockTime"
      :greeting="greeting"
      :sound-enabled="soundEnabled"
      @back="goToDashboard"
      @new-transaction="handleNewTransaction"
      @hold-transaction="handleHoldSale"
      @refresh="handleRefreshHeld"
      @toggle-sound="togglePosSound"
      @toggle-shortcuts="shortcutHelp = !shortcutHelp"
    />

    <!-- BANTUAN SHORTCUT & NOTIFIKASI (melayang, tidak menggeser layout) -->
    <PosShortcutHelp :visible="shortcutHelp" @close="shortcutHelp = false" />

    <div v-if="message || errorMessage" class="pos-toasts">
      <div v-if="message" class="alert alert-success" role="status">
        <i class="ti ti-circle-check fs-5"></i>
        <span>{{ message }}</span>
        <button type="button" class="btn btn-icon btn-sm btn-soft-success" aria-label="Tutup pesan" @click="message = ''">
          <i class="ti ti-x"></i>
        </button>
      </div>

      <div v-if="errorMessage" class="alert alert-danger" role="alert">
        <i class="ti ti-alert-circle fs-5"></i>
        <span>{{ errorMessage }}</span>
        <button type="button" class="btn btn-icon btn-sm btn-soft-danger" aria-label="Tutup pesan" @click="errorMessage = ''">
          <i class="ti ti-x"></i>
        </button>
      </div>
    </div>

    <main class="pos-main">
      <!-- KIRI: INPUT & KERANJANG -->
      <section class="pos-left">
        <div v-if="isPosted" class="card pos-posted">
          <i class="ti ti-circle-check text-success"></i>

          <div>
            <strong>Transaksi berhasil diselesaikan</strong>
            <small>{{ currentSale?.sale_number }}</small>
          </div>

          <button type="button" class="btn btn-primary btn-sm" @click="handleNewTransaction">
            <i class="ti ti-plus"></i>
            Transaksi baru
          </button>
        </div>

        <div class="pos-entry">
          <div ref="barcodeSection" class="pos-contents">
            <PosProductSearch v-model:keyword="keyword" :results="searchResults" :loading="loadingSearch" :disabled="transactionLocked || processing" @search="handleSearch" @enter="handleBarcodeEnter" @select="handleAddProduct" />
          </div>

          <div ref="customerSection" class="card pos-customer">
            <label class="pos-field-label">
              <i class="ti ti-user"></i>
              Pelanggan
              <kbd>F2</kbd>
            </label>

            <CustomerAutocomplete :model-value="selectedCustomerId" :disabled="transactionLocked || processing" @update:model-value="handleCustomerChange" />
          </div>
        </div>

        <div ref="cartSection" class="pos-contents">
          <PosCart
            class="pos-cart"
            :class="{ 'is-flash': cartFlash }"
            :items="cart"
            :subtotal="subtotal"
            :disabled="transactionLocked || processing"
            shortcut="F3"
            @quantity-change="handleQuantityChange"
            @price-change="handlePriceChange"
            @discount-mode-change="handleDiscountModeChange"
            @discount-change="handleDiscountChange"
            @note-change="handleItemNoteChange"
            @remove="handleRemoveProduct"
            @clear="handleClearCart"
          />
        </div>
      </section>

      <!-- TENGAH: PENYESUAIAN & RINGKASAN -->
      <section class="pos-middle">
        <!-- PENYESUAIAN TRANSAKSI (tab agar hemat ruang) -->
        <section class="card pos-adjust">
          <div class="pos-tabs" role="tablist" aria-label="Penyesuaian transaksi">
            <button type="button" class="pos-tab" :class="{ 'is-active': adjustTab === 'discount' }" role="tab" :aria-selected="adjustTab === 'discount'" @click="adjustTab = 'discount'">
              <i class="ti ti-discount-2"></i>
              Diskon
              <kbd>F4</kbd>
            </button>

            <button type="button" class="pos-tab" :class="{ 'is-active': adjustTab === 'charges' }" role="tab" :aria-selected="adjustTab === 'charges'" @click="adjustTab = 'charges'">
              <i class="ti ti-package"></i>
              Biaya tambahan
              <span v-if="additionalCharges.length" class="pos-tab-badge">{{ additionalCharges.length }}</span>
              <kbd>F5</kbd>
            </button>

            <button type="button" class="pos-tab" :class="{ 'is-active': adjustTab === 'notes' }" role="tab" :aria-selected="adjustTab === 'notes'" @click="adjustTab = 'notes'">
              <i class="ti ti-notes"></i>
              Catatan
              <span v-if="notes" class="pos-tab-badge">1</span>
            </button>

            <button type="button" class="pos-tab" :class="{ 'is-active': adjustTab === 'held' }" role="tab" :aria-selected="adjustTab === 'held'" @click="adjustTab = 'held'">
              <i class="ti ti-player-pause"></i>
              Ditahan
              <span v-if="heldTransactions.length" class="pos-tab-badge">{{ heldTransactions.length }}</span>
            </button>
          </div>

          <div class="pos-adjust-body" role="tabpanel">
            <div v-show="adjustTab === 'discount'" ref="discountSection">
              <PosDiscountInput
                :mode="transactionDiscountMode"
                :value="transactionDiscountValue"
                :base-amount="Math.max(0, subtotal - itemDiscount)"
                :disabled="transactionLocked || processing"
                @update:mode="handleTransactionDiscountMode"
                @update:value="handleTransactionDiscountValue"
              />
            </div>

            <div v-show="adjustTab === 'charges'" ref="chargesSection">
              <PosAdditionalCharges :charges="additionalCharges" :disabled="transactionLocked || processing" @add="addCharge" @update="updateCharge" @remove="removeCharge" />
            </div>

            <div v-show="adjustTab === 'notes'" class="h-100">
              <PosTransactionNotes v-model="notes" :disabled="transactionLocked || processing" />
            </div>

            <div v-show="adjustTab === 'held'">
              <PosHeldTransactions :transactions="heldTransactions" :loading="loadingHeldTransactions" :disabled="processing" @refresh="handleRefreshHeld" @resume="handleResumeSale" />
            </div>
          </div>
        </section>

        <PosPanel title="Ringkasan" subtitle="Rincian perhitungan transaksi" icon="ti ti-receipt" tone="success">
          <PosSummary :subtotal="subtotal" :item-discount="itemDiscount" :transaction-discount="transactionDiscount" :dpp="dpp" :tax="taxAmount" :additional-charges="additionalChargesTotal" :total="grandTotal" :show-total="false" />

          <div class="pos-tax-row">
            <label><i class="ti ti-percentage"></i>Atur pajak</label>

            <PosTaxInput :mode="taxMode" :value="taxValue" :base-amount="dpp" :disabled="transactionLocked || processing" :show-preview="false" @update:mode="handleTaxMode" @update:value="handleTaxValue" />
          </div>
        </PosPanel>
      </section>

      <!-- KANAN: PEMBAYARAN & AKSI -->
      <aside class="pos-right">
        <div class="pos-right-scroll">
          <div ref="paymentSection" class="pos-contents">
            <PosPanel title="Pembayaran" subtitle="Tentukan penyelesaian tagihan" icon="ti ti-wallet" tone="primary" shortcut="F6">
              <div ref="paymentInput">
                <PosPayment
                  :mode="settlementMode"
                  :payment-method="paymentMethod"
                  :payment-amount="paymentAmount"
                  :total="grandTotal"
                  :due-date="dueDate"
                  :due-date-error="dueDateError"
                  :reference-number="paymentReferenceNumber"
                  :payment-confirmed="paymentConfirmed"
                  :disabled="processing || isPosted"
                  :bank-name="bankName"
                  :account-number="accountNumber"
                  :account-holder="accountHolder"
                  :qris-image-url="qrisImageUrl"
                  @update:mode="handleSettlementMode"
                  @update:payment-method="handlePaymentMethod"
                  @update:payment-amount="handlePaymentAmount"
                  @update:due-date="handleDueDate"
                  @update:reference-number="handleReferenceNumber"
                  @update:payment-confirmed="handlePaymentConfirmation"
                />
              </div>
            </PosPanel>
          </div>
        </div>

        <!-- TOTAL & AKSI: selalu terlihat di bawah -->
        <section class="card pos-final">
          <PosTotalPanel :total="grandTotal" :paid="appliedAmount" :change="changeAmount" :remaining="remainingAmount" :settlement-label="settlementLabel" />

          <div class="pos-final-actions">
            <button v-if="!isDraft && !isPosted" type="button" class="btn btn-primary btn-lg" :disabled="!canCreateDraft || processing" @click="handleCreateDraft">
              <span v-if="processing" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
              <i v-else class="ti ti-device-floppy"></i>
              Buat draft penjualan
              <kbd>F12</kbd>
            </button>

            <button v-else-if="isDraft" type="button" class="btn btn-success btn-lg" :disabled="!canPostSale || !isPaymentValid || processing" @click="handlePostSale">
              <span v-if="processing" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
              <i v-else class="ti ti-check"></i>
              Posting penjualan
              <kbd>F12</kbd>
            </button>

            <button v-else type="button" class="btn btn-primary btn-lg" :disabled="processing" @click="handleNewTransaction">
              <i class="ti ti-plus"></i>
              Transaksi baru
              <kbd>F11</kbd>
            </button>

            <small v-if="isDraft">
              <i class="ti ti-info-circle"></i>
              Draft tersimpan. Periksa transaksi sebelum posting.
            </small>
          </div>
        </section>
      </aside>
    </main>
  </div>
</template>
