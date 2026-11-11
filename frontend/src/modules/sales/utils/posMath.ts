/**
 * Aritmetika uang POS dalam satu tempat.
 * Dipakai bersama oleh usePosCalculation, usePos (payload), dan komponen preview,
 * agar angka di layar, di pratinjau, dan yang dikirim ke backend selalu sama.
 */
import type { DiscountMode, PosCartItem, TaxMode } from "../types/sales";
import { toNumber } from "./format";

export { toNumber };

export function nonNegative(value: unknown): number {
  return Math.max(0, toNumber(value));
}

export function roundMoney(value: number): number {
  return Math.round((value + Number.EPSILON) * 100) / 100;
}

/** Potongan dari `base` menurut mode: nominal (maks sebesar base) atau persen (maks 100%). */
export function calculateDiscount(base: number, mode: DiscountMode, value: number): number {
  const amount = nonNegative(value);

  if (base <= 0 || amount <= 0) return 0;

  const discount = mode === "percent" ? base * (Math.min(amount, 100) / 100) : amount;

  return Math.min(base, discount);
}

/** Pajak dari dasar pengenaan pajak: nominal rupiah atau persen (maks 100%). */
export function calculateTax(base: number, mode: TaxMode, value: number): number {
  const amount = nonNegative(value);

  return mode === "percent" ? Math.max(0, base) * (Math.min(amount, 100) / 100) : amount;
}

type ItemMoneyFields = Pick<PosCartItem, "quantity" | "unit_price" | "discount_amount" | "discount_mode" | "discount_value">;

export function itemGross(item: Pick<PosCartItem, "quantity" | "unit_price">): number {
  return nonNegative(item.quantity) * nonNegative(item.unit_price);
}

/**
 * Satu-satunya sumber nilai diskon item.
 * Bila mode+nilai ada, diskon dihitung ulang dari kuantitas/harga saat ini
 * (sehingga persen tidak pernah basi); jika tidak, pakai discount_amount data lama.
 */
export function itemDiscountAmount(item: ItemMoneyFields): number {
  const gross = itemGross(item);

  if (item.discount_mode && item.discount_value !== undefined) {
    return calculateDiscount(gross, item.discount_mode, item.discount_value);
  }

  return Math.min(gross, nonNegative(item.discount_amount));
}
