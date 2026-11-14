import type { DiscountMode } from "../types/sales";
import { toNumber } from "../utils/format";

interface CartRow {
  product_id: number;
  discount_value?: number | string | null;
  discount_amount?: number | string | null;
  discount_mode?: DiscountMode | null;
}

interface UsePosCartHandlersOptions {
  getCart: () => CartRow[];

  updateQuantity: (productId: number, quantity: number) => void;
  updateUnitPrice: (productId: number, price: number) => void;
  updateItemDiscount: (productId: number, mode: DiscountMode, value: number) => void;
  updateItemNotes: (productId: number, notes: string) => void;
  removeProduct: (productId: number) => void;
  clearCart: () => void;

  showError: (error: unknown) => void;
}

export function usePosCartHandlers(options: UsePosCartHandlersOptions) {
  function handleQuantityChange(productId: number, value: number): void {
    if (!Number.isFinite(value)) return;
    // Jangan silent remove — minimal 1 agar user sadar
    guard(() => options.updateQuantity(productId, Math.max(1, value)));
  }

  function handlePriceChange(productId: number, value: number): void {
    if (!Number.isFinite(value) || value < 0) return;
    guard(() => options.updateUnitPrice(productId, value));
  }

  function handleDiscountModeChange(productId: number, mode: DiscountMode): void {
    const item = options.getCart().find((row) => row.product_id === productId);
    if (!item) return;

    const raw = item.discount_value !== undefined ? item.discount_value : item.discount_amount;
    guard(() => options.updateItemDiscount(productId, mode, toNumber(raw)));
  }

  function handleDiscountChange(productId: number, value: number): void {
    const item = options.getCart().find((row) => row.product_id === productId);
    if (!item) return;

    if (!Number.isFinite(value) || value < 0) return;

    const mode = item.discount_mode ?? "amount";
    const normalized = mode === "percent" ? Math.min(100, value) : value;

    guard(() => options.updateItemDiscount(productId, mode, normalized));
  }

  function handleItemNoteChange(productId: number, value: string): void {
    guard(() => options.updateItemNotes(productId, value));
  }

  function handleRemoveProduct(productId: number): void {
    guard(() => options.removeProduct(productId));
  }

  function handleClearCart(): void {
    if (options.getCart().length === 0) return;
    guard(() => options.clearCart());
  }

  function guard(action: () => void): void {
    try {
      action();
    } catch (error) {
      options.showError(error);
    }
  }

  return {
    handleQuantityChange,
    handlePriceChange,
    handleDiscountModeChange,
    handleDiscountChange,
    handleItemNoteChange,
    handleRemoveProduct,
    handleClearCart,
  };
}
