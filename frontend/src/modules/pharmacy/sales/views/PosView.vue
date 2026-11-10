<script setup lang="ts">
import "../styles/pos.css";
import { usePosSound } from "../composables/usePosSound";

const { soundEnabled, play: playPosSound, toggle: togglePosSound } = usePosSound();

import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
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

defineOptions({
  name: "PosView",
});

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
} = usePos({
  searchProducts,
});

/* --------------------------------------------------------------------------
 * LOCAL UI STATE
 * ----------------------------------------------------------------------- */

const message = ref("");
const errorMessage = ref("");
const shortcutHelp = ref(false);

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
const selectedPaymentMethod = computed<PaymentMethod>(() => paymentMethod.value ?? "cash");
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

function clearMessages(): void {
  message.value = "";
  errorMessage.value = "";
}

function showError(error: unknown): void {
  errorMessage.value = getErrorMessage(error);
  message.value = "";
}

function showSuccess(text: string): void {
  message.value = text;
  errorMessage.value = "";
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

/* function handleExactPayment(): void {
  if (settlementMode.value !== "paid") return;

  payExactAmount();
} */

function handleResetPayment(): void {
  resetPaymentAmount();
}

/* --------------------------------------------------------------------------
 * TRANSACTION ACTIONS
 * ----------------------------------------------------------------------- */

/**
 * Diskon transaksi dan pajak sudah dipetakan ke payload backend.
 * Biaya tambahan dan settlement tempo belum didukung oleh API lama.
 */
const hasUnsupportedDraftValues = computed(() => {
  return additionalChargesTotal.value > 0 || settlementMode.value !== "paid";
});

async function handleCreateDraft(): Promise<void> {
  clearMessages();

  if (hasUnsupportedDraftValues.value) {
    errorMessage.value = "Biaya tambahan dan transaksi tempo belum didukung oleh payload backend penjualan saat ini.";
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
    errorMessage.value = "Biaya tambahan atau transaksi tempo belum dapat diposting karena backend belum menyimpan seluruh komponennya.";
    return;
  }

  if (!canPostSale.value) {
    errorMessage.value = "Transaksi belum dapat diposting.";
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
      focusSection(discountSection.value);
      break;

    case "F5":
      event.preventDefault();
      focusSection(chargesSection.value);
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
      focusSection(paymentInput.value);
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
  window.removeEventListener("keydown", handleKeyboard);
});
</script>

<template>
  <div class="pos-page pos-modern">
    <!-- TOP BAR -->
    <header class="pos-topbar">
      <div class="pos-brand">
        <div class="pos-brand-icon">
          <i class="ti ti-heartbeat"></i>
        </div>

        <div class="pos-brand-text">
          <strong>ACN <span>Pharmacy</span></strong>
          <small>Point of Sale</small>
        </div>
      </div>

      <div class="pos-topbar-center">
        <div class="pos-terminal-status">
          <span class="pos-status-dot"></span>
          Terminal Kasir Aktif
        </div>

        <div class="pos-transaction-number">
          <span>TRANSAKSI</span>
          <strong>{{ currentSale?.sale_number || "Transaksi Baru" }}</strong>
        </div>
      </div>

      <div class="pos-topbar-actions">
        <button type="button" class="pos-icon-action" title="Bantuan shortcut" @click="shortcutHelp = !shortcutHelp">
          <i class="ti ti-keyboard"></i>
        </button>

        <button type="button" class="pos-icon-action" title="Muat ulang transaksi tertahan" :disabled="loadingHeldTransactions" @click="handleRefreshHeld">
          <i class="ti ti-refresh"></i>
        </button>

        <button type="button" class="pos-new-transaction" :disabled="processing" @click="handleNewTransaction">
          <i class="ti ti-plus"></i>
          <span>Transaksi Baru</span>
        </button>

        <button type="button" class="pos-sound-toggle" :class="{ 'is-muted': !soundEnabled }" :aria-pressed="soundEnabled" :title="soundEnabled ? 'Nonaktifkan suara' : 'Aktifkan suara'" @click="togglePosSound">
          <span aria-hidden="true">
            {{ soundEnabled ? "🔊" : "🔇" }}
          </span>
          <span>{{ soundEnabled ? "Suara aktif" : "Suara mati" }}</span>
        </button>
      </div>
    </header>

    <!-- SHORTCUT HELP -->
    <PosShortcutHelp :visible="shortcutHelp" @close="shortcutHelp = false" />

    <!-- NOTIFICATIONS -->
    <div v-if="message" class="pos-alert pos-alert-success" role="status">
      <i class="ti ti-circle-check"></i>
      <span>{{ message }}</span>
      <button type="button" @click="message = ''">
        <i class="ti ti-x"></i>
      </button>
    </div>

    <div v-if="errorMessage" class="pos-alert pos-alert-danger" role="alert">
      <i class="ti ti-alert-circle"></i>
      <span>{{ errorMessage }}</span>
      <button type="button" @click="errorMessage = ''">
        <i class="ti ti-x"></i>
      </button>
    </div>

    <!-- POSTED STATE -->
    <div v-if="isPosted" class="pos-completed-banner">
      <div class="pos-completed-icon">
        <i class="ti ti-check"></i>
      </div>

      <div class="pos-completed-content">
        <strong>Transaksi berhasil diselesaikan</strong>
        <span>{{ currentSale?.sale_number }}</span>
      </div>

      <button type="button" class="pos-button-primary" @click="handleNewTransaction">
        <i class="ti ti-plus"></i>
        Transaksi Baru
      </button>
    </div>

    <!-- MAIN WORKSPACE -->
    <main class="pos-workspace">
      <!-- LEFT: PRODUCT & CART -->
      <section class="pos-workspace-main">
        <!-- PRODUCT SEARCH -->
        <section ref="barcodeSection" class="pos-product-panel">
          <div class="pos-section-heading">
            <div class="pos-heading-icon">
              <i class="ti ti-scan"></i>
            </div>

            <div>
              <h5>Tambah Produk</h5>
              <p>Scan barcode atau cari produk berdasarkan nama dan SKU.</p>
            </div>

            <div class="pos-heading-shortcut">
              <kbd>F1</kbd>
            </div>
          </div>

          <PosProductSearch v-model:keyword="keyword" :results="searchResults" :loading="loadingSearch" :disabled="transactionLocked || processing" @search="handleSearch" @enter="handleBarcodeEnter" @select="handleAddProduct" />
        </section>

        <!-- CART -->
        <section ref="cartSection" class="pos-cart-panel">
          <div class="pos-section-heading pos-cart-heading">
            <div class="pos-heading-icon pos-heading-icon-purple">
              <i class="ti ti-shopping-bag"></i>
            </div>

            <div>
              <h5>Keranjang Belanja</h5>
              <p>{{ cart.length }} jenis produk dalam transaksi</p>
            </div>

            <div class="pos-cart-heading-right">
              <span class="pos-cart-count"> {{ cart.length }} ITEM </span>

              <kbd>F3</kbd>
            </div>
          </div>

          <div class="pos-cart-content">
            <PosCart
              :items="cart"
              :subtotal="subtotal"
              :disabled="transactionLocked || processing"
              @quantity-change="handleQuantityChange"
              @price-change="handlePriceChange"
              @discount-mode-change="handleDiscountModeChange"
              @discount-change="handleDiscountChange"
              @note-change="handleItemNoteChange"
              @remove="handleRemoveProduct"
              @clear="handleClearCart"
            />
          </div>

          <div class="pos-cart-footer">
            <span>{{ cart.length }} jenis produk</span>
            <strong>{{ formatCurrency(subtotal) }}</strong>
          </div>
        </section>

        <!-- HELD TRANSACTIONS -->
        <details class="pos-held-drawer">
          <summary>
            <span>
              <i class="ti ti-player-pause"></i>
              Transaksi Ditahan
              <small>{{ heldTransactions.length }}</small>
            </span>

            <i class="ti ti-chevron-down"></i>
          </summary>

          <div class="pos-held-content">
            <PosHeldTransactions :transactions="heldTransactions" :loading="loadingHeldTransactions" :disabled="processing" @refresh="handleRefreshHeld" @resume="handleResumeSale" />
          </div>
        </details>
      </section>

      <!-- RIGHT: CHECKOUT -->
      <aside class="pos-checkout-panel">
        <!-- CUSTOMER -->
        <section ref="customerSection" class="pos-checkout-section">
          <div class="pos-compact-heading">
            <div class="pos-heading-icon pos-heading-icon-blue">
              <i class="ti ti-user"></i>
            </div>

            <div>
              <h5>Pelanggan</h5>
              <small>Informasi pembeli</small>
            </div>

            <kbd>F2</kbd>
          </div>

          <CustomerAutocomplete :model-value="selectedCustomerId" :disabled="transactionLocked || processing" @update:model-value="handleCustomerChange" />
        </section>

        <!-- DISCOUNT -->
        <section ref="discountSection" class="pos-checkout-section">
          <div class="pos-compact-heading">
            <div class="pos-heading-icon pos-heading-icon-orange">
              <i class="ti ti-discount-2"></i>
            </div>

            <div>
              <h5>Diskon Transaksi</h5>
              <small>Potongan keseluruhan</small>
            </div>

            <kbd>F4</kbd>
          </div>

          <PosDiscountInput
            :mode="transactionDiscountMode"
            :value="transactionDiscountValue"
            :base-amount="Math.max(0, subtotal - itemDiscount)"
            :disabled="transactionLocked || processing"
            @update:mode="handleTransactionDiscountMode"
            @update:value="handleTransactionDiscountValue"
          />
        </section>

        <!-- SUMMARY -->
        <section class="pos-summary-panel">
          <div class="pos-compact-heading">
            <div class="pos-heading-icon pos-heading-icon-green">
              <i class="ti ti-receipt"></i>
            </div>

            <div>
              <h5>Ringkasan Belanja</h5>
              <small>Rincian perhitungan transaksi</small>
            </div>
          </div>

          <PosSummary :subtotal="subtotal" :item-discount="itemDiscount" :transaction-discount="transactionDiscount" :dpp="dpp" :tax="taxAmount" :additional-charges="additionalChargesTotal" :total="grandTotal" />

          <div class="pos-tax-row">
            <div class="pos-tax-label">
              <i class="ti ti-percentage"></i>
              <span>Pajak</span>
            </div>

            <PosTaxInput :mode="taxMode" :value="taxValue" :base-amount="dpp" :disabled="transactionLocked || processing" @update:mode="handleTaxMode" @update:value="handleTaxValue" />
          </div>
        </section>

        <!-- ADDITIONAL CHARGES -->
        <section ref="chargesSection" class="pos-checkout-section">
          <div class="pos-compact-heading">
            <div class="pos-heading-icon pos-heading-icon-orange">
              <i class="ti ti-package"></i>
            </div>

            <div>
              <h5>Biaya Tambahan</h5>
              <small>Ongkir, kemasan, dan jasa</small>
            </div>

            <kbd>F5</kbd>
          </div>

          <PosAdditionalCharges :charges="additionalCharges" :disabled="transactionLocked || processing" @add="addCharge" @update="updateCharge" @remove="removeCharge" />
        </section>

        <!-- PAYMENT -->
        <section ref="paymentSection" class="pos-payment-panel">
          <div class="pos-payment-heading">
            <div>
              <span class="pos-payment-eyebrow">CHECKOUT</span>
              <h5>Pembayaran</h5>
            </div>

            <div class="pos-payment-icon">
              <i class="ti ti-wallet"></i>
            </div>
          </div>

          <div ref="paymentInput">
            <PosPayment
              :mode="settlementMode"
              :payment-method="selectedPaymentMethod"
              :payment-amount="paymentAmount"
              :total="grandTotal"
              :due-date="dueDate"
              :reference-number="paymentReferenceNumber"
              :disabled="transactionLocked || processing"
              :bank-name="bankName"
              :account-number="accountNumber"
              :account-holder="accountHolder"
              :qris-image-url="qrisImageUrl"
              @update:mode="handleSettlementMode"
              @update:payment-method="handlePaymentMethod"
              @update:payment-amount="handlePaymentAmount"
              @update:due-date="handleDueDate"
              @update:reference-number="handleReferenceNumber"
              @submit="isDraft ? handlePostSale() : handleCreateDraft()"
            />
          </div>

          <PosTotalPanel :total="grandTotal" :paid="appliedAmount" :change="changeAmount" :remaining="remainingAmount" :settlement-label="settlementLabel" />
        </section>

        <!-- TRANSACTION NOTES -->
        <section class="pos-notes-panel">
          <PosTransactionNotes v-model="notes" :disabled="transactionLocked || processing" />
        </section>

        <!-- FINAL TOTAL & ACTION -->
        <section class="pos-final-panel">
          <div class="pos-final-total">
            <div>
              <span>TOTAL TAGIHAN</span>
              <small>{{ cart.length }} jenis produk</small>
            </div>

            <strong>{{ formatCurrency(grandTotal) }}</strong>
          </div>

          <button v-if="!isDraft && !isPosted" type="button" class="pos-final-button pos-final-button-primary" :disabled="!canCreateDraft || processing" @click="handleCreateDraft">
            <span v-if="processing" class="pos-spinner"></span>
            <i v-else class="ti ti-device-floppy"></i>

            <span>Buat Draft Penjualan</span>
            <kbd>F12</kbd>
          </button>

          <button v-else-if="isDraft" type="button" class="pos-final-button pos-final-button-success" :disabled="!canPostSale || processing" @click="handlePostSale">
            <span v-if="processing" class="pos-spinner"></span>
            <i v-else class="ti ti-check"></i>

            <span>Posting Penjualan</span>
            <kbd>F12</kbd>
          </button>

          <button v-else-if="isPosted" type="button" class="pos-final-button pos-final-button-primary" :disabled="processing" @click="handleNewTransaction">
            <i class="ti ti-plus"></i>
            <span>Transaksi Baru</span>
            <kbd>F11</kbd>
          </button>

          <div v-if="isDraft" class="pos-draft-information">
            <i class="ti ti-info-circle"></i>
            Draft tersimpan. Periksa transaksi sebelum posting.
          </div>
        </section>
      </aside>
    </main>

    <!-- BOTTOM STATUS BAR -->
    <footer class="pos-bottom-bar">
      <div class="pos-bottom-left">
        <span class="pos-status-dot"></span>
        <span>ACN Pharmacy POS</span>
        <span class="pos-bottom-divider"></span>
        <span>Ready for transaction</span>
      </div>

      <div class="pos-bottom-shortcuts">
        <span><kbd>F1</kbd> Produk</span>
        <span><kbd>F2</kbd> Pelanggan</span>
        <span><kbd>F10</kbd> Tahan</span>
        <span><kbd>F12</kbd> Proses</span>
      </div>
    </footer>
  </div>
</template>
