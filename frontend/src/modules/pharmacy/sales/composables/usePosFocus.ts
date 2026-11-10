import type { ComponentPublicInstance } from "vue";

export type PosFocusTarget = HTMLElement | ComponentPublicInstance | null;

export function usePosFocus() {
  function elementOf(target: PosFocusTarget): HTMLElement | null {
    if (!target) return null;

    return target instanceof HTMLElement ? target : ((target.$el as HTMLElement | null) ?? null);
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
