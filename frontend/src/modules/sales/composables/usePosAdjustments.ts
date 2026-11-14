import { ref } from "vue";

import type { DiscountMode, TaxMode, PosAdditionalCharge } from "../types/sales";

import { roundMoney, toNumber } from "../utils/posMath";

const MAX_ADDITIONAL_CHARGES = 10;

// =============================================================
// OPTIONS
// =============================================================

export interface PosAdjustmentsOptions {
  taxRate?: number;
}

// =============================================================
// COMPOSABLE
// =============================================================

export function usePosAdjustments(options: PosAdjustmentsOptions = {}) {
  // =========================================================
  // TRANSACTION DISCOUNT
  // =========================================================

  const transactionDiscountMode = ref<DiscountMode>("amount");
  const transactionDiscountValue = ref(0);

  // =========================================================
  // TAX
  // =========================================================

  const taxMode = ref<TaxMode>("percent");

  const taxValue = ref(Math.max(0, toNumber(options.taxRate)));

  // =========================================================
  // ADDITIONAL CHARGES
  // =========================================================

  const additionalCharges = ref<PosAdditionalCharge[]>([]);

  // =========================================================
  // TRANSACTION DISCOUNT
  // =========================================================

  function setTransactionDiscount(mode: DiscountMode, value: number): void {
    if (!Number.isFinite(value) || value < 0) {
      throw new Error("Nilai diskon transaksi tidak valid.");
    }

    transactionDiscountMode.value = mode;

    transactionDiscountValue.value = mode === "percent" ? Math.min(100, value) : roundMoney(value);
  }

  // =========================================================
  // TAX
  // =========================================================

  function setTax(mode: TaxMode, value: number): void {
    if (!Number.isFinite(value) || value < 0) {
      throw new Error("Nilai pajak tidak valid.");
    }

    taxMode.value = mode;
    taxValue.value = value;
  }

  // =========================================================
  // ADDITIONAL CHARGES
  // =========================================================

  function addAdditionalCharge(charge: PosAdditionalCharge): void {
    if (additionalCharges.value.length >= MAX_ADDITIONAL_CHARGES) {
      throw new Error(`Maksimal ${MAX_ADDITIONAL_CHARGES} biaya tambahan.`);
    }

    if (!charge.label.trim()) {
      throw new Error("Nama biaya tambahan wajib diisi.");
    }

    if (!Number.isFinite(charge.amount) || charge.amount < 0) {
      throw new Error("Nominal biaya tambahan tidak valid.");
    }

    if (additionalCharges.value.some((item) => item.id === charge.id)) {
      throw new Error("ID biaya tambahan sudah digunakan.");
    }

    additionalCharges.value.push({
      ...charge,
      label: charge.label.trim(),
      amount: roundMoney(charge.amount),
    });
  }

  function updateAdditionalCharge(id: string, changes: Partial<PosAdditionalCharge>): void {
    const index = additionalCharges.value.findIndex((item) => item.id === id);

    if (index === -1) return;

    const current = additionalCharges.value[index];

    const amount = changes.amount === undefined ? current.amount : Number(changes.amount);

    if (!Number.isFinite(amount) || amount < 0) {
      throw new Error("Nominal biaya tambahan tidak valid.");
    }

    const label = changes.label !== undefined ? changes.label.trim() : current.label;

    if (!label) {
      throw new Error("Nama biaya tambahan wajib diisi.");
    }

    additionalCharges.value[index] = {
      ...current,
      ...changes,
      label,
      amount: roundMoney(amount),
    };
  }

  function removeAdditionalCharge(id: string): void {
    additionalCharges.value = additionalCharges.value.filter((item) => item.id !== id);
  }

  function clearAdditionalCharges(): void {
    additionalCharges.value = [];
  }

  // =========================================================
  // RESET
  // =========================================================

  function resetAdjustments(): void {
    transactionDiscountMode.value = "amount";
    transactionDiscountValue.value = 0;

    taxMode.value = "percent";
    taxValue.value = Math.max(0, toNumber(options.taxRate));

    additionalCharges.value = [];
  }

  // =========================================================
  // PUBLIC API
  // =========================================================

  return {
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
    clearAdditionalCharges,

    resetAdjustments,
  };
}
