import { computed, type Ref } from "vue";

import type {
  PosCartItem,
  PosAdditionalCharge,
  DiscountMode,
  TaxMode,
} from "../types/sales";

export interface PosCalculationOptions {
  items: Ref<PosCartItem[]>;

  transactionDiscountMode: Ref<DiscountMode>;
  transactionDiscountValue: Ref<number>;

  taxMode: Ref<TaxMode>;
  taxValue: Ref<number>;

  additionalCharges: Ref<PosAdditionalCharge[]>;
}

function toNumber(value: unknown): number {
  const number = Number(value);
  return Number.isFinite(number) ? number : 0;
}

function nonNegative(value: unknown): number {
  return Math.max(0, toNumber(value));
}

function calculateDiscount(
  base: number,
  mode: DiscountMode,
  value: number,
): number {
  const amount = nonNegative(value);

  if (base <= 0 || amount <= 0) {
    return 0;
  }

  const discount =
    mode === "percent"
      ? base * (Math.min(amount, 100) / 100)
      : amount;

  return Math.min(base, discount);
}

export function usePosCalculation(options: PosCalculationOptions) {
  /**
   * Nilai kotor seluruh barang sebelum diskon.
   */
  const subtotal = computed(() => {
    return options.items.value.reduce((total, item) => {
      const quantity = nonNegative(item.quantity);
      const price = nonNegative(item.unit_price);

      return total + quantity * price;
    }, 0);
  });

  /**
   * Total diskon per item.
   *
   * Jika discount_mode tersedia, discount_value menjadi sumber input.
   * Jika tidak, gunakan discount_amount untuk kompatibilitas data lama.
   */
  const itemDiscount = computed(() => {
    return options.items.value.reduce((total, item) => {
      const gross = nonNegative(item.quantity) *
        nonNegative(item.unit_price);

      const discount =
        item.discount_mode && item.discount_value !== undefined
          ? calculateDiscount(
              gross,
              item.discount_mode,
              item.discount_value,
            )
          : Math.min(gross, nonNegative(item.discount_amount));

      return total + discount;
    }, 0);
  });

  /**
   * Nilai setelah diskon item.
   */
  const afterItemDiscount = computed(() => {
    return Math.max(0, subtotal.value - itemDiscount.value);
  });

  /**
   * Diskon keseluruhan transaksi.
   */
  const transactionDiscount = computed(() => {
    return calculateDiscount(
      afterItemDiscount.value,
      options.transactionDiscountMode.value,
      options.transactionDiscountValue.value,
    );
  });

  /**
   * Total seluruh diskon.
   */
  const discountTotal = computed(() => {
    return itemDiscount.value + transactionDiscount.value;
  });

  /**
   * Dasar Pengenaan Pajak.
   */
  const dpp = computed(() => {
    return Math.max(0, subtotal.value - discountTotal.value);
  });

  /**
   * Pajak dapat berupa nominal rupiah atau persentase.
   */
  const taxAmount = computed(() => {
    const value = nonNegative(options.taxValue.value);

    if (options.taxMode.value === "percent") {
      return dpp.value * (value / 100);
    }

    return value;
  });

  /**
   * Biaya tambahan transaksi.
   */
  const additionalChargesTotal = computed(() => {
    return options.additionalCharges.value.reduce((total, charge) => {
      return total + nonNegative(charge.amount);
    }, 0);
  });

  /**
   * Total akhir tagihan.
   */
  const grandTotal = computed(() => {
    return Math.max(
      0,
      dpp.value +
        taxAmount.value +
        additionalChargesTotal.value,
    );
  });

  const summary = computed(() => ({
    subtotal: subtotal.value,
    itemDiscount: itemDiscount.value,
    transactionDiscount: transactionDiscount.value,
    discount: discountTotal.value,
    dpp: dpp.value,
    tax: taxAmount.value,
    additionalCharges: additionalChargesTotal.value,
    total: grandTotal.value,

    // Nilai pembayaran diisi oleh usePosPayment.
    paid: 0,
    change: 0,
    receivable: grandTotal.value,
  }));

  return {
    subtotal,
    itemDiscount,
    afterItemDiscount,
    transactionDiscount,
    discountTotal,
    dpp,
    taxAmount,
    additionalChargesTotal,
    grandTotal,
    summary,
  };
}
