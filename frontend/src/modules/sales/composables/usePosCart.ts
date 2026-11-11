import { ref, type Ref } from "vue";

import type { DiscountMode, PosCartItem, ProductSearchResult } from "../types/sales";

import { itemDiscountAmount, itemGross, roundMoney, toNumber } from "../utils/posMath";

// =============================================================
// OPTIONS
// =============================================================

export interface PosCartOptions {
  items?: Ref<PosCartItem[]>;
  ensureEditable?: () => void;
}

// =============================================================
// COMPOSABLE
// =============================================================

export function usePosCart(options: PosCartOptions = {}) {
  const cart = options.items ?? ref<PosCartItem[]>([]);

  function ensureEditable(): void {
    options.ensureEditable?.();
  }

  // =========================================================
  // VALIDATION
  // =========================================================

  function validateQuantity(quantity: number, productName: string): void {
    if (!Number.isFinite(quantity) || quantity <= 0) {
      throw new Error(`Kuantitas ${productName} tidak valid.`);
    }
  }

  function validateStock(item: PosCartItem, quantity: number): void {
    if (item.available_stock === undefined) {
      return;
    }

    const availableStock = toNumber(item.available_stock);

    if (quantity > availableStock) {
      throw new Error(`Stok ${item.product_name} tidak mencukupi. Tersedia: ${availableStock}.`);
    }
  }

  // =========================================================
  // DISCOUNT SYNCHRONIZATION
  // =========================================================

  /**
   * Sinkronisasi diskon item setelah qty atau harga berubah.
   *
   * Diskon persentase tetap menggunakan persentase.
   * Diskon nominal dibatasi sebesar nilai kotor.
   */
  function syncItemDiscount(item: PosCartItem): void {
    const mode = item.discount_mode ?? "amount";

    if (mode === "amount") {
      item.discount_value = Math.min(roundMoney(itemGross(item)), toNumber(item.discount_value ?? item.discount_amount));
    }

    item.discount_amount = roundMoney(itemDiscountAmount(item));
  }

  // =========================================================
  // CART OPERATIONS
  // =========================================================

  function addProduct(product: ProductSearchResult): void {
    ensureEditable();

    if (!product.is_active) {
      throw new Error("Produk tidak aktif.");
    }

    const existing = cart.value.find((item) => item.product_id === product.id);

    if (existing) {
      const nextQuantity = existing.quantity + 1;

      validateStock(existing, nextQuantity);

      existing.quantity = nextQuantity;
      syncItemDiscount(existing);
    } else {
      const item: PosCartItem = {
        product_id: product.id,
        sku: product.sku,
        barcode: product.barcode ?? null,
        product_name: product.name,
        unit_name: product.unit_name,

        quantity: 1,
        unit_price: toNumber(product.selling_price),

        discount_amount: 0,
        discount_mode: "amount",
        discount_value: 0,

        available_stock: product.available_stock === undefined ? undefined : toNumber(product.available_stock),
      };

      validateStock(item, item.quantity);

      cart.value.push(item);
    }
  }

  function updateQuantity(productId: number, quantity: number): void {
    ensureEditable();

    const item = cart.value.find((row) => row.product_id === productId);

    if (!item) return;

    if (!Number.isFinite(quantity) || quantity <= 0) {
      removeProduct(productId);
      return;
    }

    validateStock(item, quantity);

    item.quantity = quantity;

    syncItemDiscount(item);
  }

  function updateUnitPrice(productId: number, price: number): void {
    ensureEditable();

    const item = cart.value.find((row) => row.product_id === productId);

    if (!item) return;

    if (!Number.isFinite(price) || price < 0) {
      throw new Error("Harga jual tidak valid.");
    }

    item.unit_price = roundMoney(price);

    syncItemDiscount(item);
  }

  /**
   * Mendukung diskon nominal maupun persentase.
   */
  function updateItemDiscount(productId: number, mode: DiscountMode, value: number): void {
    ensureEditable();

    const item = cart.value.find((row) => row.product_id === productId);

    if (!item) return;

    if (!Number.isFinite(value) || value < 0) {
      throw new Error("Nilai diskon tidak valid.");
    }

    const gross = roundMoney(item.quantity * item.unit_price);

    const normalizedValue = mode === "percent" ? Math.min(100, value) : Math.min(gross, value);

    const amount = mode === "percent" ? roundMoney((gross * normalizedValue) / 100) : roundMoney(normalizedValue);

    item.discount_mode = mode;
    item.discount_value = normalizedValue;
    item.discount_amount = amount;
  }

  function updateItemNotes(productId: number, value: string): void {
    ensureEditable();

    const item = cart.value.find((row) => row.product_id === productId);

    if (!item) return;

    item.notes = value.trim() || null;
  }

  function removeProduct(productId: number): void {
    ensureEditable();

    cart.value = cart.value.filter((item) => item.product_id !== productId);
  }

  function clearCart(): void {
    ensureEditable();

    cart.value = [];
  }

  // =========================================================
  // PUBLIC API
  // =========================================================

  return {
    cart,

    validateQuantity,
    validateStock,
    syncItemDiscount,

    addProduct,
    updateQuantity,
    updateUnitPrice,
    updateItemDiscount,
    updateItemNotes,
    removeProduct,
    clearCart,
  };
}
