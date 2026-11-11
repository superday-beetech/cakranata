export type DiscountMode = "percent" | "amount";

function toNumber(value: unknown): number {
  const number = Number(value);
  return Number.isFinite(number) ? number : 0;
}

function roundMoney(value: number): number {
  return Math.round((value + Number.EPSILON) * 100) / 100;
}

export function useDiscount() {
  /**
   * Menghitung nominal diskon.
   *
   * percent = persentase dari nilai dasar
   * amount  = nominal Rupiah
   */
  function calculateDiscount(baseAmount: number, discountValue: number, mode: DiscountMode): number {
    const base = Math.max(0, toNumber(baseAmount));
    const discount = Math.max(0, toNumber(discountValue));

    if (mode === "percent") {
      return roundMoney((base * Math.min(100, discount)) / 100);
    }

    return roundMoney(Math.min(base, discount));
  }

  /**
   * Memvalidasi nilai diskon terhadap nilai dasar.
   */
  function isDiscountValid(baseAmount: number, discountValue: number, mode: DiscountMode): boolean {
    const base = toNumber(baseAmount);
    const discount = toNumber(discountValue);

    if (!Number.isFinite(base) || !Number.isFinite(discount) || base < 0 || discount < 0) {
      return false;
    }

    if (mode === "percent") {
      return discount <= 100;
    }

    return discount <= base;
  }

  /**
   * Menghitung nilai bersih setelah diskon.
   */
  function calculateNet(baseAmount: number, discountValue: number, mode: DiscountMode): number {
    const base = Math.max(0, toNumber(baseAmount));
    const discount = calculateDiscount(base, discountValue, mode);

    return roundMoney(Math.max(0, base - discount));
  }

  return {
    calculateDiscount,
    isDiscountValid,
    calculateNet,
  };
}
