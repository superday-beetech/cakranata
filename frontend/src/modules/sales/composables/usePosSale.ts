import { computed, onBeforeUnmount, ref, type ComputedRef, type Ref } from "vue";
import axios from "axios";
import { salesService } from "../services/salesService";

import type { PosCartItem, Sale, SaleItem, CreateSalePayload, DiscountMode, TaxMode, SettlementMode, PaymentMethod, PostSalePayload } from "../types/sales";

import { itemDiscountAmount, roundMoney, toNumber } from "../utils/posMath";
import { saveOfflineSale } from "@/offline/repositories/offlineRepository";
import type { LocalTransaction, OfflineStockPolicySnapshot } from "@/offline/types/offline";

// =============================================================
// OPTIONS
// =============================================================

export interface PosSaleOptions {
  cart: Ref<PosCartItem[]>;
  selectedCustomerId: Ref<number | null>;
  notes: Ref<string>;

  currentSale: Ref<Sale | null>;
  processing: Ref<boolean>;

  transactionDiscountMode: Ref<DiscountMode>;
  transactionDiscountValue: Ref<number>;

  taxMode: Ref<TaxMode>;
  taxValue: Ref<number>;

  calculatedTransactionDiscount: ComputedRef<number>;
  calculatedTaxAmount: ComputedRef<number>;

  isResumedSale: Ref<boolean>;

  additionalChargesTotal: ComputedRef<number>;

  payment: {
    isValid: ComputedRef<boolean>;
    validationErrors: ComputedRef<string[]>;
    buildPaymentData: () => PostSalePayload;
  };

  validateQuantity: (quantity: number, productName: string) => void;

  validateStock: (item: PosCartItem, quantity: number) => void;

  resetPaymentFields: () => void;
  resetAdjustments: () => void;
  clearSearch: () => void;
}

// =============================================================
// COMPOSABLE
// =============================================================

export function usePosSale(options: PosSaleOptions) {
  // =========================================================
  // HELD TRANSACTIONS
  // =========================================================

  const heldTransactions = ref<Sale[]>([]);
  const loadingHeldTransactions = ref(false);
  const offlineTransaction = ref<LocalTransaction | null>(null);

  function handleOfflineTransactionSynced(event: Event): void {
    const detail = (event as CustomEvent<{ localId?: string; serverId?: number }>).detail;

    const current = offlineTransaction.value;
    if (!detail?.localId || !current || detail.localId !== current.localId) return;

    offlineTransaction.value = {
      ...current,
      status: "synced",
      serverId: detail.serverId,
      syncedAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
    };
  }

  function handleOfflineTransactionConflict(event: Event): void {
    const detail = (event as CustomEvent<{ localId?: string; message?: string }>).detail;

    const current = offlineTransaction.value;
    if (!detail?.localId || !current || detail.localId !== current.localId) return;

    offlineTransaction.value = {
      ...current,
      status: "conflict",
      errorMessage: detail.message ?? "Transaksi perlu diperiksa sebelum dapat disinkronkan.",
      updatedAt: new Date().toISOString(),
    };
  }

  function handleOfflineTransactionDeferred(event: Event): void {
    const detail = (event as CustomEvent<{ localId?: string; message?: string; state?: "pending" | "failed" }>).detail;

    const current = offlineTransaction.value;
    if (!detail?.localId || !current || detail.localId !== current.localId) return;

    offlineTransaction.value = {
      ...current,
      status: detail.state === "failed" ? "failed" : current.status,
      errorMessage: detail.message ?? "Sinkronisasi akan dicoba kembali.",
      updatedAt: new Date().toISOString(),
    };
  }

  if (typeof window !== "undefined") {
    window.addEventListener("offline:transaction-synced", handleOfflineTransactionSynced);
    window.addEventListener("offline:transaction-conflict", handleOfflineTransactionConflict);
    window.addEventListener("offline:transaction-deferred", handleOfflineTransactionDeferred);
  }

  onBeforeUnmount(() => {
    if (typeof window === "undefined") return;

    window.removeEventListener("offline:transaction-synced", handleOfflineTransactionSynced);
    window.removeEventListener("offline:transaction-conflict", handleOfflineTransactionConflict);
    window.removeEventListener("offline:transaction-deferred", handleOfflineTransactionDeferred);
  });

  // =========================================================
  // TRANSACTION STATE
  // =========================================================

  const isDraft = computed(() => options.currentSale.value?.status === "draft");

  const isPosted = computed(() => options.currentSale.value?.status === "posted");

  const transactionLocked = computed(() => options.processing.value || (isDraft.value && !options.isResumedSale.value) || isPosted.value);

  const canCreateDraft = computed(() => options.cart.value.length > 0 && !options.processing.value && !options.currentSale.value);

  const canPostSale = computed(() => isDraft.value && !options.processing.value && options.payment.isValid.value);

  const canProcessPayment = canPostSale;

  // =========================================================
  // BUILD SALE PAYLOAD
  // =========================================================

  function buildSalePayload(): CreateSalePayload {
    if (options.cart.value.length === 0) {
      throw new Error("Keranjang masih kosong.");
    }

    /**
     * Backend saat ini belum menyimpan biaya tambahan.
     * Cegah perbedaan total POS dengan total transaksi tersimpan.
     */
    if (options.additionalChargesTotal.value > 0) {
      throw new Error("Biaya tambahan belum dapat disimpan oleh API penjualan. Integrasikan tabel biaya transaksi terlebih dahulu.");
    }

    const items = options.cart.value.map((item) => {
      options.validateQuantity(item.quantity, item.product_name);

      options.validateStock(item, item.quantity);

      const gross = roundMoney(item.quantity * item.unit_price);

      const discount = roundMoney(itemDiscountAmount(item));

      if (discount < 0 || discount > gross) {
        throw new Error(`Diskon ${item.product_name} tidak valid.`);
      }

      return {
        product_id: item.product_id,
        quantity: item.quantity,
        unit_price: item.unit_price,
        discount_amount: discount,
        notes: item.notes ?? null,
      };
    });

    return {
      customer_id: options.selectedCustomerId.value,
      discount_amount: roundMoney(transactionDiscountValueForPayload()),
      tax_amount: roundMoney(taxAmountForPayload()),
      notes: options.notes.value.trim() || null,
      items,
    };
  }

  /**
   * Menggunakan nilai hasil perhitungan aktual.
   * Nilai diskon header dan pajak header mengikuti API lama.
   */
  function transactionDiscountValueForPayload(): number {
    return options.calculatedTransactionDiscount.value;
  }

  function taxAmountForPayload(): number {
    return options.calculatedTaxAmount.value;
  }
  // =========================================================
  // CREATE DRAFT
  // =========================================================

  async function createDraft(): Promise<Sale | null> {
    if (options.processing.value) return null;

    if (options.currentSale.value) {
      throw new Error("Transaksi sebelumnya belum selesai.");
    }

    options.processing.value = true;

    try {
      const payload = buildSalePayload();

      options.currentSale.value = await salesService.createSale(payload);
      options.isResumedSale.value = false;

      return options.currentSale.value;
    } finally {
      options.processing.value = false;
    }
  }

  // =========================================================
  // POST SALE
  // =========================================================

  async function postCurrentSale(): Promise<Sale | null> {
    if (options.processing.value) return null;

    if (!options.currentSale.value) {
      throw new Error("Draft penjualan belum dibuat.");
    }

    if (options.currentSale.value.status !== "draft") {
      throw new Error("Transaksi bukan berstatus draft.");
    }

    if (!options.payment.isValid.value) {
      throw new Error(options.payment.validationErrors.value[0] ?? "Data pembayaran belum valid.");
    }

    options.processing.value = true;

    try {
      const paymentData = options.payment.buildPaymentData();

      options.currentSale.value = await salesService.postSale(options.currentSale.value.id, paymentData);

      options.isResumedSale.value = false;

      return options.currentSale.value;
    } finally {
      options.processing.value = false;
    }
  }

  async function persistOfflineCheckout(
    payload: CreateSalePayload,
    paymentData: PostSalePayload,
    idempotencyKey: string,
  ): Promise<{ status: "queued"; transaction: LocalTransaction }> {
    const policySnapshot: OfflineStockPolicySnapshot = {
      policy: "allow_negative",
      settingsVersion: 0,
      createdAt: new Date().toISOString(),
    };

    const transaction = await saveOfflineSale(
      { sale: payload, payment: paymentData },
      null,
      policySnapshot,
      idempotencyKey,
    );

    resetTransactionState();
    offlineTransaction.value = transaction;

    if (typeof window !== "undefined") {
      window.dispatchEvent(new CustomEvent("offline:queue-updated"));
    }

    return { status: "queued", transaction };
  }

  async function checkoutCurrentSale(): Promise<
    { status: "synced"; sale: Sale } | { status: "queued"; transaction: LocalTransaction } | null
  > {
    if (options.processing.value) return null;

    if (options.currentSale.value) {
      throw new Error("Transaksi sudah disimpan.");
    }

    if (!options.payment.isValid.value) {
      throw new Error(options.payment.validationErrors.value[0] ?? "Data pembayaran belum valid.");
    }

    options.processing.value = true;

    try {
      const payload = buildSalePayload();
      const paymentData = options.payment.buildPaymentData();
      const idempotencyKey = crypto.randomUUID();

      if (typeof navigator !== "undefined" && !navigator.onLine) {
        return await persistOfflineCheckout(payload, paymentData, idempotencyKey);
      }

      try {
        const sale = await salesService.checkout(payload, paymentData, idempotencyKey);
        options.currentSale.value = sale;

        return { status: "synced", sale };
      } catch (error) {
        const shouldQueue =
          axios.isAxiosError(error) &&
          (!error.response ||
            error.response.status >= 500 ||
            error.response.status === 408 ||
            error.response.status === 429);

        if (!shouldQueue) {
          throw error;
        }

        // Key yang sama dipakai lagi saat sync. Aman jika server sebenarnya
        // sudah memproses checkout tetapi response-nya hilang.
        return await persistOfflineCheckout(payload, paymentData, idempotencyKey);
      }
    } finally {
      options.processing.value = false;
    }
  }

  // =========================================================
  // RESET TRANSACTION STATE
  // =========================================================

  function resetTransactionState(): void {
    options.cart.value = [];
    options.selectedCustomerId.value = null;
    options.notes.value = "";
    options.currentSale.value = null;
    options.isResumedSale.value = false;

    options.resetPaymentFields();
    options.resetAdjustments();
    options.clearSearch();
  }

  // =========================================================
  // NEW TRANSACTION
  // =========================================================

  function newTransaction(): void {
    if (options.processing.value) {
      throw new Error("Transaksi sedang diproses.");
    }

    if (options.currentSale.value && options.currentSale.value.status === "draft") {
      throw new Error("Draft belum diselesaikan. Jangan tinggalkan transaksi ini.");
    }

    resetTransactionState();
  }

  // =========================================================
  // LOAD HELD TRANSACTIONS
  // =========================================================

  async function loadHeldTransactions(): Promise<void> {
    if (loadingHeldTransactions.value) return;

    loadingHeldTransactions.value = true;

    try {
      const result = await salesService.getSales({
        page: 1,
        per_page: 100,
        status: "held",
      });

      heldTransactions.value = result.data;
    } catch (error) {
      // Silent — atau log
      heldTransactions.value = []; // reset
    } finally {
      loadingHeldTransactions.value = false;
    }
  }

  // =========================================================
  // HOLD CURRENT SALE
  // =========================================================

  async function holdCurrentSale(): Promise<Sale | null> {
    if (options.processing.value) return null;

    if (!options.currentSale.value) {
      await createDraft();
    }

    if (!options.currentSale.value || options.currentSale.value.status !== "draft") {
      throw new Error("Hanya draft aktif yang dapat ditahan.");
    }

    options.processing.value = true;

    try {
      const heldSale = await salesService.holdSale(options.currentSale.value.id);

      heldTransactions.value = [heldSale, ...heldTransactions.value.filter((item) => item.id !== heldSale.id)];

      resetTransactionState();

      return heldSale;
    } finally {
      options.processing.value = false;
    }
  }

  // =========================================================
  // RESUME HELD TRANSACTION
  // =========================================================

  async function resumeHeldTransaction(saleId: number): Promise<Sale | null> {
    if (options.processing.value) return null;

    if (options.currentSale.value || options.cart.value.length > 0) {
      throw new Error("Selesaikan atau tahan transaksi aktif sebelum melanjutkan transaksi lain.");
    }

    const id = Number(saleId);

    if (!Number.isSafeInteger(id) || id <= 0) {
      throw new Error("ID transaksi tidak valid.");
    }

    options.processing.value = true;

    try {
      const detail = await salesService.getSale(id);

      if (String(detail.status) !== "held") {
        throw new Error("Transaksi tidak ditemukan dalam daftar transaksi tertahan.");
      }

      const resumedSale = await salesService.resumeSale(id);

      if (resumedSale.status !== "draft") {
        throw new Error("Transaksi gagal dilanjutkan.");
      }

      // Pulihkan keranjang.
      options.cart.value = (detail.items ?? []).map(
        (item: SaleItem): PosCartItem => ({
          product_id: item.product_id,
          sku: item.sku ?? "",
          barcode: item.barcode ?? null,
          product_name: item.product_name ?? "Produk",
          unit_name: undefined,

          quantity: toNumber(item.quantity),
          unit_price: toNumber(item.unit_price),

          discount_amount: toNumber(item.discount_amount),
          discount_mode: "amount",
          discount_value: toNumber(item.discount_amount),

          available_stock: undefined,
          notes: item.notes ?? null,
        }),
      );

      // Pulihkan pelanggan.
      options.selectedCustomerId.value = detail.customer_id ?? null;

      // Biaya tambahan belum didukung backend.
      // Reset pembayaran.
      options.resetPaymentFields();
      options.resetAdjustments();

      // Pulihkan diskon dan pajak setelah reset.
      options.transactionDiscountMode.value = "amount";
      options.transactionDiscountValue.value = toNumber(detail.discount_amount);

      options.taxMode.value = "amount";
      options.taxValue.value = toNumber(detail.tax_amount);

      // Pulihkan catatan.
      options.notes.value = detail.notes ?? "";

      if (!resumedSale.items || !resumedSale.sale_number) {
        throw new Error("Respons resumeSale tidak lengkap.");
      }
      // Pulihkan transaksi.
      options.currentSale.value = {
        ...detail,
        status: "draft",
        updated_at: resumedSale.updated_at,
      };

      options.isResumedSale.value = true;

      heldTransactions.value = heldTransactions.value.filter((item) => item.id !== id);
      options.clearSearch();

      return options.currentSale.value;
    } finally {
      options.processing.value = false;
    }
  }

  // =========================================================
  // CANCEL
  // =========================================================

  async function cancelCurrentSale(restoreHeld: boolean = false): Promise<void> {
    const sale = options.currentSale.value;

    if (options.processing.value) return;

    if (!sale || sale.status !== "draft") {
      throw new Error("Tidak ada draft aktif untuk dibatalkan.");
    }

    options.processing.value = true;

    try {
      await salesService.cancelSale(sale.id, { restoreHeld });

      resetTransactionState();
    } finally {
      options.processing.value = false;
    }
  }

  // =========================================================
  // PUBLIC API
  // =========================================================

  return {
    isDraft,
    isPosted,
    transactionLocked,

    canCreateDraft,
    canPostSale,
    canProcessPayment,

    heldTransactions,
    loadingHeldTransactions,
    offlineTransaction,

    buildSalePayload,
    createDraft,
    postCurrentSale,
    checkoutCurrentSale,
    cancelCurrentSale,

    resetTransactionState,
    newTransaction,

    loadHeldTransactions,
    holdCurrentSale,
    resumeHeldTransaction,
  };
}
