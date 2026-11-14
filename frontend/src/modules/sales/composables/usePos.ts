import { computed, ref, watch } from "vue";

import { usePosCart } from "./usePosCart";
import { usePosAdjustments } from "./usePosAdjustments";
import { usePosCalculation } from "./usePosCalculation";
import { usePosPayment } from "./usePosPayment";
import { usePosSale } from "./usePosSale";

import type { DiscountMode, TaxMode, SettlementMode, PaymentMethod, Sale, ProductSearchResult } from "../types/sales";

import { getToday } from "../utils/format";
import { roundMoney, toNumber } from "../utils/posMath";

// =============================================================
// OPTIONS
// =============================================================

interface ProductSearch {
  (keyword: string): Promise<ProductSearchResult[]>;
}

interface UsePosOptions {
  searchProducts: ProductSearch;
  taxRate?: number;
}

// =============================================================
// COMPOSABLE
// =============================================================

export function usePos(options: UsePosOptions) {
  // =========================================================
  // TRANSACTION STATE
  // =========================================================

  const selectedCustomerId = ref<number | null>(null);

  const notes = ref("");

  const processing = ref(false);

  const currentSale = ref<Sale | null>(null);

  const isResumedSale = ref(false);
  // =========================================================
  // SEARCH STATE
  // =========================================================

  const keyword = ref("");
  const searchResults = ref<ProductSearchResult[]>([]);
  const loadingSearch = ref(false);

  let searchSequence = 0;

  async function searchProduct(value?: string): Promise<void> {
    const searchKeyword = (value ?? keyword.value).trim();
    const sequence = ++searchSequence;

    keyword.value = searchKeyword;

    if (!searchKeyword) {
      searchResults.value = [];
      loadingSearch.value = false;
      return;
    }

    loadingSearch.value = true;

    try {
      const results = await options.searchProducts(searchKeyword);

      if (sequence !== searchSequence) {
        return;
      }

      searchResults.value = results;
    } finally {
      if (sequence === searchSequence) {
        loadingSearch.value = false;
      }
    }
  }

  function clearSearch(): void {
    searchSequence++;

    keyword.value = "";
    searchResults.value = [];
    loadingSearch.value = false;
  }

  // =========================================================
  // PAYMENT STATE
  // =========================================================

  const paymentMethod = ref<PaymentMethod | null>("cash");

  const paymentAmount = ref(0);

  const paymentAmountTouched = ref(false);

  const paymentConfirmed = ref(false);

  const settlementMode = ref<SettlementMode>("paid");

  const dueDate = ref("");

  const paymentReferenceNumber = ref("");

  const buyerName = ref("");
  const buyerPhone = ref("");
  const collateralNote = ref("");

  // =========================================================
  // ENSURE EDITABLE
  // =========================================================

  function ensureEditable(): void {
    if (processing.value) {
      throw new Error("Transaksi sedang diproses.");
    }

    if (currentSale.value && !(currentSale.value.status === "draft" && isResumedSale.value)) {
      throw new Error("Transaksi sudah disimpan. Buat transaksi baru untuk melanjutkan.");
    }
  }

  // =========================================================
  // CART COMPOSABLE
  // =========================================================

  const cartState = usePosCart({
    ensureEditable,
  });

  const {
    cart,
    validateQuantity,
    validateStock,

    addProduct,
    updateQuantity,
    updateUnitPrice,
    updateItemDiscount,
    updateItemNotes,
    removeProduct,
  } = cartState;

  // =========================================================
  // ADJUSTMENTS COMPOSABLE
  // =========================================================

  const adjustments = usePosAdjustments({
    taxRate: options.taxRate,
  });

  const {
    transactionDiscountMode,
    transactionDiscountValue,

    taxMode,
    taxValue,

    additionalCharges,

    setTransactionDiscount,
    setTax,

    addAdditionalCharge,
    updateAdditionalCharge,
    removeAdditionalCharge,
  } = adjustments;

  // =========================================================
  // CALCULATION COMPOSABLE
  // =========================================================

  const calculation = usePosCalculation({
    items: cart,

    transactionDiscountMode,
    transactionDiscountValue,

    taxMode,
    taxValue,

    additionalCharges,
  });

  const subtotal = computed(() => roundMoney(calculation.subtotal.value));

  const itemDiscount = computed(() => roundMoney(calculation.itemDiscount.value));

  const afterItemDiscount = computed(() => roundMoney(calculation.afterItemDiscount.value));

  const transactionDiscount = computed(() => roundMoney(calculation.transactionDiscount.value));

  const discountTotal = computed(() => roundMoney(calculation.discountTotal.value));

  const dpp = computed(() => roundMoney(calculation.dpp.value));

  const taxAmount = computed(() => roundMoney(calculation.taxAmount.value));

  const additionalChargesTotal = computed(() => roundMoney(calculation.additionalChargesTotal.value));

  const grandTotal = computed(() => roundMoney(calculation.grandTotal.value));

  const payableTotal = computed(() => (currentSale.value ? roundMoney(toNumber(currentSale.value.total_amount)) : grandTotal.value));
  // =========================================================
  // PAYMENT COMPOSABLE
  // =========================================================

  const payment = usePosPayment({
    total: payableTotal,

    mode: settlementMode,
    paymentMethod,
    paymentAmount,

    dueDate,
    customerId: selectedCustomerId,

    referenceNumber: paymentReferenceNumber,
    paymentConfirmed,
    buyerName,
    buyerPhone,
    collateralNote,
  });

  // Lunas: nominal otomatis mengikuti total.
  watch(payableTotal, (total) => {
    if (processing.value) return;
    if (settlementMode.value !== "paid") return;
    if (paymentAmountTouched.value) return;

    paymentAmount.value = total;
  });

  const {
    amountReceived,
    appliedAmount,
    changeAmount,
    remainingAmount,
    receivableAmount,

    isPaid,
    isPartial,
    isReceivable,
    requiresConfirmation,

    dueDateError,
    validationErrors: paymentValidationErrors,
    isValid: isPaymentValid,
    summary: paymentSummary,

    buildPaymentData,
    payExactAmount,
    resetAmount,
    setDefaultDueDate,
    needsBuyerInfo,
  } = payment;

  // =========================================================
  // PAYMENT RESET
  // =========================================================

  function resetPaymentFields(): void {
    paymentConfirmed.value = false;

    paymentMethod.value = "cash";
    paymentAmount.value = 0;
    paymentAmountTouched.value = false;

    settlementMode.value = "paid";

    dueDate.value = "";
    paymentReferenceNumber.value = "";
    buyerName.value = "";
    buyerPhone.value = "";
    collateralNote.value = "";
  }

  // =========================================================
  // SALE COMPOSABLE
  // =========================================================

  const sale = usePosSale({
    cart,
    selectedCustomerId,
    notes,

    currentSale,
    processing,
    isResumedSale,

    transactionDiscountMode,
    transactionDiscountValue,

    taxMode,
    taxValue,

    calculatedTransactionDiscount: transactionDiscount,
    calculatedTaxAmount: taxAmount,

    additionalChargesTotal,

    payment: {
      isValid: isPaymentValid,
      validationErrors: paymentValidationErrors,
      buildPaymentData,
    },

    validateQuantity,
    validateStock,

    resetPaymentFields,
    resetAdjustments: adjustments.resetAdjustments,
    clearSearch,
  });

  const {
    isDraft,
    isPosted,
    transactionLocked,

    canCreateDraft,
    canPostSale,

    heldTransactions,
    loadingHeldTransactions,
    offlineTransaction,

    createDraft,
    postCurrentSale,
    newTransaction,

    checkoutCurrentSale,
    cancelCurrentSale,

    loadHeldTransactions,
    holdCurrentSale,
    resumeHeldTransaction,
  } = sale;

  // =========================================================
  // CART RESET
  // =========================================================

  function clearCart(): void {
    cartState.clearCart();

    selectedCustomerId.value = null;
    notes.value = "";

    resetPaymentFields();
    adjustments.resetAdjustments();

    clearSearch();
  }

  // =========================================================
  // CUSTOMER
  // =========================================================

  function setCustomer(customerId: number | string | null): void {
    ensureEditable();

    if (customerId === null || customerId === "") {
      selectedCustomerId.value = null;
      return;
    }

    const id = Number(customerId);

    if (!Number.isSafeInteger(id) || id <= 0) {
      throw new Error("Pelanggan tidak valid.");
    }

    selectedCustomerId.value = id;
  }

  // =========================================================
  // PAYMENT INPUT
  // =========================================================

  function setPaymentAmount(amount: number): void {
    if (processing.value) return;

    if (!Number.isFinite(amount) || amount < 0) {
      throw new Error("Nominal pembayaran tidak valid.");
    }

    paymentAmount.value = roundMoney(amount);
    paymentAmountTouched.value = true;
  }

  function setPaymentMethod(method: PaymentMethod | null): void {
    if (processing.value) return;

    if (paymentMethod.value !== method) {
      paymentConfirmed.value = false;
    }

    paymentMethod.value = method;
  }

  function setPaymentConfirmed(value: boolean): void {
    if (processing.value) return;

    paymentConfirmed.value = value;
  }

  function setSettlementMode(mode: SettlementMode): void {
    if (processing.value) return;

    paymentConfirmed.value = false;
    settlementMode.value = mode;

    if (mode === "paid") {
      dueDate.value = "";
      paymentMethod.value ??= "cash";

      paymentAmountTouched.value = false;

      payExactAmount();

      return;
    }

    if (!dueDate.value) {
      dueDate.value = getToday();
    }

    if (mode === "partial") {
      paymentMethod.value ??= "cash";
      return;
    }

    paymentAmount.value = 0;
    paymentMethod.value = null;
  }

  function setDueDate(value: string): void {
    if (processing.value) return;

    dueDate.value = value;
  }

  function setPaymentReferenceNumber(value: string): void {
    if (processing.value) return;

    paymentReferenceNumber.value = value;
  }

  function payExact(): void {
    paymentAmountTouched.value = false;

    if (processing.value) return;

    payExactAmount();
  }

  function resetPaymentAmount(): void {
    if (processing.value) return;

    if (settlementMode.value === "paid") {
      paymentAmountTouched.value = false;

      payExactAmount();

      return;
    }

    resetAmount();
  }

  // =========================================================
  // PUBLIC API
  // =========================================================

  return {
    // State
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
    offlineTransaction,

    // Transaction state
    isDraft,
    isPosted,
    transactionLocked,
    canCreateDraft,
    canPostSale,

    // Calculation state
    transactionDiscountMode,
    transactionDiscountValue,

    taxMode,
    taxValue,

    additionalCharges,

    subtotal,
    itemDiscount,
    transactionDiscount,
    dpp,
    taxAmount,
    additionalChargesTotal,
    grandTotal,
    payableTotal,

    // Payment state
    settlementMode,
    dueDate,
    dueDateError,

    paymentReferenceNumber,
    paymentConfirmed,

    isPaymentValid,

    appliedAmount,
    changeAmount,
    remainingAmount,

    // Search
    searchProduct,
    clearSearch,

    // Cart
    addProduct,
    updateQuantity,
    updateUnitPrice,
    updateItemDiscount,
    updateItemNotes,
    removeProduct,
    clearCart,

    // Customer
    setCustomer,

    // Discount / tax
    setTransactionDiscount,
    setTax,

    // Additional charges
    addAdditionalCharge,
    updateAdditionalCharge,
    removeAdditionalCharge,

    // Payment actions
    setPaymentAmount,
    setPaymentMethod,
    setPaymentConfirmed,
    setSettlementMode,
    setDueDate,
    setPaymentReferenceNumber,
    payExact,
    resetPaymentAmount,
    buyerName,
    buyerPhone,
    collateralNote,
    needsBuyerInfo,

    // Transaction actions
    createDraft,
    postCurrentSale,
    checkoutCurrentSale,
    cancelCurrentSale,
    newTransaction,

    // Held transactions
    heldTransactions,
    loadingHeldTransactions,

    loadHeldTransactions,
    holdCurrentSale,
    resumeHeldTransaction,
  };
}
