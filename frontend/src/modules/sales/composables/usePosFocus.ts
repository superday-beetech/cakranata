import type { ComponentPublicInstance } from "vue";

export type PosFocusTarget = HTMLElement | ComponentPublicInstance | null;

export function usePosFocus() {
  function elementOf(target: unknown): HTMLElement | null {
    if (!target) return null;
    if (target instanceof HTMLElement) return target;
    const el = (target as { $el?: unknown }).$el;
    return el instanceof HTMLElement ? el : null;
  }
  function focusSection(section: PosFocusTarget): void {
    const input = elementOf(section)?.querySelector<HTMLElement>("input:not([disabled]), button:not([disabled]), select:not([disabled])");

    input?.focus();
  }

  function focusPaymentAmount(paymentInput: HTMLElement | null): void {
    const amount = paymentInput?.querySelector<HTMLInputElement>("#pos-payment-amount");

    if (amount && !amount.disabled) {
      amount.focus();
      amount.select();
      return;
    }

    focusSection(paymentInput);
  }

  return {
    elementOf,
    focusSection,
    focusPaymentAmount,
  };
}
