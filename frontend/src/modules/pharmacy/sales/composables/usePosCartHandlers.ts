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
  /*  function handleQuantityChange(productId: number, value: number): void {
    try {
      options.updateQuantity(productId, Math.max(0, value));
    } catch (error) {
      options.showError(error);
    }
  } */

  function handleQuantityChange(productId: number, value: number): void {
    guard(() => options.updateQuantity(productId, Math.max(0, value)));
  }

  function handlePriceChange(productId: number, value: number): void {
    guard(() => options.updateUnitPrice(productId, Math.max(0, value)));
  }

  function handleDiscountModeChange(productId: number, mode: DiscountMode): void {
    const item = options.getCart().find((row) => row.product_id === productId);

    if (!item) return;

    guard(() => options.updateItemDiscount(productId, mode, toNumber(item.discount_value ?? item.discount_amount)));
  }

  function handleDiscountChange(productId: number, value: number): void {
    const item = options.getCart().find((row) => row.product_id === productId);

    if (!item) return;

    guard(() => options.updateItemDiscount(productId, item.discount_mode ?? "amount", Math.max(0, value)));
  }

  function handleItemNoteChange(productId: number, value: string): void {
    guard(() => options.updateItemNotes(productId, value));
  }

  function handleRemoveProduct(productId: number): void {
    guard(() => options.removeProduct(productId));
  }

  function handleClearCart(): void {
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
