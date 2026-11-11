import { computed, type Ref } from "vue";

import type { PaymentMethod, SettlementMode } from "../types/sales";
import { getToday } from "../utils/format";
import { nonNegative } from "../utils/posMath";

export interface PosPaymentOptions {
  total: Ref<number>;

  mode: Ref<SettlementMode>;
  paymentMethod: Ref<PaymentMethod | null>;
  paymentAmount: Ref<number>;

  dueDate: Ref<string>;
  customerId: Ref<number | null>;

  referenceNumber?: Ref<string>;
  paymentConfirmed?: Ref<boolean>;
  buyerName?: Ref<string>;
  buyerPhone?: Ref<string>;
  collateralNote?: Ref<string>;
}

function isValidDate(value: string): boolean {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    return false;
  }

  const [year, month, day] = value.split("-").map(Number);

  const date = new Date(Date.UTC(year, month - 1, day));

  return date.getUTCFullYear() === year && date.getUTCMonth() === month - 1 && date.getUTCDate() === day;
}

export function usePosPayment(options: PosPaymentOptions) {
  const total = computed(() => nonNegative(options.total.value));

  /**
   * Uang yang diterima dari pelanggan.
   */
  const amountReceived = computed(() => {
    if (options.mode.value === "receivable") {
      return 0;
    }

    return nonNegative(options.paymentAmount.value);
  });

  /**
   * Nominal yang diterapkan ke tagihan.
   */
  const appliedAmount = computed(() => {
    if (options.mode.value === "receivable") {
      return 0;
    }

    return Math.min(total.value, amountReceived.value);
  });

  /**
   * Kembalian hanya untuk pembayaran tunai lunas.
   */
  const changeAmount = computed(() => {
    if (options.mode.value !== "paid" || options.paymentMethod.value !== "cash") {
      return 0;
    }

    return Math.max(0, amountReceived.value - total.value);
  });

  /**
   * Sisa tagihan yang menjadi piutang.
   */
  const receivableAmount = computed(() => {
    return Math.max(0, total.value - appliedAmount.value);
  });

  const remainingAmount = computed(() => receivableAmount.value);

  const isReceivable = computed(() => options.mode.value === "receivable");

  const isPartial = computed(() => options.mode.value === "partial");

  const isPaid = computed(() => options.mode.value === "paid");

  const needsBuyerInfo = computed(() => options.mode.value !== "paid" && !options.customerId.value);

  /**
   * Transfer dan QRIS memerlukan konfirmasi operator.
   */
  const requiresConfirmation = computed(() => {
    return appliedAmount.value > 0 && (options.paymentMethod.value === "transfer" || options.paymentMethod.value === "qris");
  });

  /**
   * Validasi tanggal jatuh tempo.
   */
  const dueDateError = computed(() => {
    if (isPaid.value) {
      return null;
    }

    const dueDate = options.dueDate.value;

    if (!dueDate) {
      return "Tanggal jatuh tempo wajib diisi.";
    }

    if (!isValidDate(dueDate)) {
      return "Format tanggal jatuh tempo tidak valid.";
    }

    if (dueDate < getToday()) {
      return "Tanggal jatuh tempo tidak boleh sebelum hari ini.";
    }

    return null;
  });

  /**
   * Validasi seluruh data pembayaran.
   */
  const validationErrors = computed<string[]>(() => {
    const errors: string[] = [];

    const mode = options.mode.value;
    const method = options.paymentMethod.value;
    const amount = amountReceived.value;

    if (total.value <= 0) {
      errors.push("Total transaksi harus lebih dari nol.");
    }

    if (mode === "paid") {
      if (!method) {
        errors.push("Pilih metode pembayaran.");
      }

      if (amount < total.value) {
        errors.push("Uang diterima belum mencukupi total tagihan.");
      }

      if (method !== "cash" && amount > total.value) {
        errors.push("Nominal pembayaran non-tunai tidak boleh melebihi total tagihan.");
      }
    }

    if (mode === "partial") {
      if (method === "cash" && amount >= total.value) {
        errors.push("Pembayaran tunai sebagian tidak boleh mencapai total tagihan.");
      }

      /* if (!options.customerId.value) {
        errors.push("Pelanggan wajib dipilih untuk pembayaran sebagian.");
      } */

      if (!method) {
        errors.push("Pilih metode pembayaran.");
      }

      if (amount <= 0) {
        errors.push("Nominal pembayaran sebagian harus lebih dari nol.");
      }

      if (amount >= total.value) {
        errors.push("Pembayaran sebagian harus lebih kecil dari total tagihan.");
      }
    }

    /* if (mode === "receivable") {
      if (!options.customerId.value) {
        errors.push("Pelanggan wajib dipilih untuk transaksi piutang.");
      }
    } */

    if (mode !== "paid" && dueDateError.value) {
      errors.push(dueDateError.value);
    }

    if (requiresConfirmation.value && !options.paymentConfirmed?.value) {
      errors.push("Pembayaran transfer atau QRIS belum dikonfirmasi.");
    }

    if (needsBuyerInfo.value) {
      if (!options.buyerName?.value.trim()) {
        errors.push("Nama pembeli wajib diisi untuk tempo non-anggota.");
      }

      if (!options.collateralNote?.value.trim()) {
        errors.push("Jaminan wajib diisi untuk tempo non-anggota.");
      }
    }

    return errors;
  });

  const isValid = computed(() => validationErrors.value.length === 0);

  /**
   * Ringkasan pembayaran untuk tampilan POS.
   */
  const summary = computed(() => ({
    total: total.value,
    received: amountReceived.value,
    paid: appliedAmount.value,
    change: changeAmount.value,
    remaining: remainingAmount.value,
    receivable: receivableAmount.value,
  }));

  /**
   * Payload pembayaran untuk SalesApiController.
   */
  function buildPaymentData() {
    if (!isValid.value) {
      throw new Error(validationErrors.value[0]);
    }

    return {
      settlement_mode: options.mode.value,

      amount: appliedAmount.value,
      applied_amount: appliedAmount.value,

      amount_received: amountReceived.value,
      change_amount: changeAmount.value,

      payment_method: appliedAmount.value > 0 ? options.paymentMethod.value : null,

      reference_number: options.referenceNumber?.value.trim() || null,

      due_date: isPaid.value ? null : options.dueDate.value,

      payment_confirmed: options.paymentConfirmed?.value ?? false,
      buyer_name: needsBuyerInfo.value ? options.buyerName?.value.trim() || null : null,
      buyer_phone: needsBuyerInfo.value ? options.buyerPhone?.value.trim() || null : null,
      collateral_note: needsBuyerInfo.value ? options.collateralNote?.value.trim() || null : null,
    };
  }

  /**
   * Mengatur pembayaran pas dengan total tagihan.
   */
  function payExactAmount(): void {
    options.paymentAmount.value = total.value;
  }

  /**
   * Mengosongkan nominal pembayaran.
   */
  function resetAmount(): void {
    options.paymentAmount.value = 0;
  }

  /**
   * Mengatur tanggal jatuh tempo default.
   */
  function setDefaultDueDate(): void {
    if (!options.dueDate.value) {
      options.dueDate.value = getToday();
    }
  }

  return {
    total,
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
    validationErrors,
    isValid,
    summary,

    buildPaymentData,
    payExactAmount,
    resetAmount,
    setDefaultDueDate,

    needsBuyerInfo,
  };
}
