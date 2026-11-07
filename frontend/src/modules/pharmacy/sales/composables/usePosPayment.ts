import { computed, type Ref } from "vue";

import type {
  PaymentMethod,
  SettlementMode,
} from "../types/sales";

export interface PosPaymentOptions {
  total: Ref<number>;

  mode: Ref<SettlementMode>;
  paymentMethod: Ref<PaymentMethod | null>;
  paymentAmount: Ref<number>;

  dueDate: Ref<string>;
  customerId: Ref<number | null>;
}

function toNumber(value: unknown): number {
  const number = Number(value);
  return Number.isFinite(number) ? number : 0;
}

function nonNegative(value: unknown): number {
  return Math.max(0, toNumber(value));
}

function getToday(): string {
  const date = new Date();
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");

  return `${year}-${month}-${day}`;
}

export function usePosPayment(options: PosPaymentOptions) {
  const total = computed(() => nonNegative(options.total.value));

  /**
   * Uang yang diserahkan atau diterima dari pelanggan.
   */
  const amountReceived = computed(() => {
    if (options.mode.value === "receivable") {
      return 0;
    }

    return nonNegative(options.paymentAmount.value);
  });

  /**
   * Nominal yang benar-benar diterapkan ke tagihan.
   */
  const appliedAmount = computed(() => {
    if (options.mode.value === "receivable") {
      return 0;
    }

    return Math.min(total.value, amountReceived.value);
  });

  /**
   * Kembalian hanya berlaku untuk pembayaran tunai.
   */
  const changeAmount = computed(() => {
    if (
      options.mode.value !== "paid" ||
      options.paymentMethod.value !== "cash"
    ) {
      return 0;
    }

    return Math.max(0, amountReceived.value - total.value);
  });

  /**
   * Sisa tagihan yang menjadi piutang.
   */
  const receivableAmount = computed(() => {
    if (options.mode.value === "paid") {
      return 0;
    }

    return Math.max(0, total.value - appliedAmount.value);
  });

  const remainingAmount = computed(() => {
    return Math.max(0, total.value - appliedAmount.value);
  });

  const isReceivable = computed(() => {
    return options.mode.value === "receivable";
  });

  const isPartial = computed(() => {
    return options.mode.value === "partial";
  });

  const isPaid = computed(() => {
    return options.mode.value === "paid";
  });

  /**
   * Transfer dan QRIS membutuhkan konfirmasi pembayaran.
   * Pemilihan metode saja bukan bukti pembayaran berhasil.
   */
  const requiresConfirmation = computed(() => {
    return (
      appliedAmount.value > 0 &&
      (
        options.paymentMethod.value === "transfer" ||
        options.paymentMethod.value === "qris"
      )
    );
  });

  /**
   * Validasi pembayaran sebelum transaksi dikirim.
   */
  const validationErrors = computed<string[]>(() => {
    const errors: string[] = [];
    const amount = amountReceived.value;
    const method = options.paymentMethod.value;
    const mode = options.mode.value;

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

      if (
        method !== "cash" &&
        amount > total.value
      ) {
        errors.push(
          "Nominal pembayaran non-tunai tidak boleh melebihi total tagihan.",
        );
      }
    }

    if (mode === "partial") {
      if (!options.customerId.value) {
        errors.push("Pelanggan wajib dipilih untuk pembayaran tempo.");
      }

      if (!method) {
        errors.push("Pilih metode pembayaran untuk pembayaran sebagian.");
      }

      if (amount <= 0) {
        errors.push("Nominal pembayaran sebagian harus lebih dari nol.");
      }

      if (amount >= total.value) {
        errors.push(
          "Pembayaran sebagian harus lebih kecil dari total tagihan.",
        );
      }

      if (!options.dueDate.value) {
        errors.push("Tanggal jatuh tempo wajib diisi.");
      }
    }

    if (mode === "receivable") {
      if (!options.customerId.value) {
        errors.push("Pelanggan wajib dipilih untuk transaksi piutang.");
      }

      if (!options.dueDate.value) {
        errors.push("Tanggal jatuh tempo wajib diisi.");
      }
    }

    return errors;
  });

  const isValid = computed(() => {
    return validationErrors.value.length === 0;
  });

  /**
   * Data ringkasan pembayaran untuk ditampilkan di POS.
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
   * Data pembayaran yang nantinya dikirim ke backend.
   *
   * Ini belum menggantikan payload backend lama.
   * Integrasi payload baru dilakukan saat controller dan service
   * backend mendukung settlement mode dan due date.
   */
  function buildPaymentData() {
    if (!isValid.value) {
      throw new Error(validationErrors.value[0]);
    }

    return {
      settlement_mode: options.mode.value,
      payment_method:
        appliedAmount.value > 0
          ? options.paymentMethod.value
          : null,
      amount_received: amountReceived.value,
      applied_amount: appliedAmount.value,
      change_amount: changeAmount.value,
      receivable_amount: receivableAmount.value,
      due_date:
        options.mode.value === "paid"
          ? null
          : options.dueDate.value,
    };
  }

  /**
   * Mengatur nominal pembayaran pas dengan total tagihan.
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
   * Mengatur tanggal jatuh tempo default hari ini.
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

    validationErrors,
    isValid,
    summary,

    buildPaymentData,
    payExactAmount,
    resetAmount,
    setDefaultDueDate,
  };
}
