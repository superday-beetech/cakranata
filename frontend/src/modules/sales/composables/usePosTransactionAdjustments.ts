import type { DiscountMode, TaxMode } from "../types/sales";

interface UsePosTransactionAdjustmentsOptions {
  getTransactionDiscountMode: () => DiscountMode;
  getTransactionDiscountValue: () => number;
  getTaxMode: () => TaxMode;
  getTaxValue: () => number;

  setTransactionDiscount: (mode: DiscountMode, value: number) => void;

  setTax: (mode: TaxMode, value: number) => void;
}

export function usePosTransactionAdjustments(options: UsePosTransactionAdjustmentsOptions) {
  function handleTransactionDiscountMode(mode: DiscountMode): void {
    options.setTransactionDiscount(mode, options.getTransactionDiscountValue());
  }

  function handleTransactionDiscountValue(value: number): void {
    options.setTransactionDiscount(options.getTransactionDiscountMode(), Math.max(0, value));
  }

  function handleTaxMode(mode: TaxMode): void {
    options.setTax(mode, options.getTaxValue());
  }

  function handleTaxValue(value: number): void {
    options.setTax(options.getTaxMode(), Math.max(0, value));
  }

  return {
    handleTransactionDiscountMode,
    handleTransactionDiscountValue,
    handleTaxMode,
    handleTaxValue,
  };
}
