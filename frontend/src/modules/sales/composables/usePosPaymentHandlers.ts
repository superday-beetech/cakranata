import type { PaymentMethod, SettlementMode } from "../types/sales";

interface UsePosPaymentHandlersOptions {
  getSettlementMode: () => SettlementMode;

  setSettlementMode: (mode: SettlementMode) => void;
  setPaymentAmount: (value: number) => void;
  setPaymentMethod: (method: PaymentMethod | null) => void; // ← null
  setDueDate: (value: string) => void;
  setPaymentReferenceNumber: (value: string) => void;
  payExact: () => void;
  resetPaymentAmount: () => void;
  setPaymentConfirmed: (value: boolean) => void;

  showError?: (error: unknown) => void; // ← opsional
}

export function usePosPaymentHandlers(options: UsePosPaymentHandlersOptions) {
  function guard(action: () => void): void {
    try {
      action();
    } catch (error) {
      options.showError?.(error);
    }
  }

  function handleSettlementMode(mode: SettlementMode): void {
    guard(() => options.setSettlementMode(mode));
  }

  function handlePaymentAmount(value: number): void {
    if (!Number.isFinite(value) || value < 0) return;
    guard(() => options.setPaymentAmount(value));
  }

  function handlePaymentMethod(method: PaymentMethod | null): void {
    guard(() => options.setPaymentMethod(method));
  }

  function handleDueDate(value: string): void {
    guard(() => options.setDueDate(value));
  }

  function handleReferenceNumber(value: string): void {
    guard(() => options.setPaymentReferenceNumber(value));
  }

  function handleExactPayment(): void {
    if (options.getSettlementMode() !== "paid") return;
    guard(() => options.payExact());
  }

  function handleResetPayment(): void {
    guard(() => options.resetPaymentAmount());
  }

  function handlePaymentConfirmation(value: boolean): void {
    guard(() => options.setPaymentConfirmed(value));
  }

  return {
    handleSettlementMode,
    handlePaymentAmount,
    handlePaymentMethod,
    handleDueDate,
    handleReferenceNumber,
    handleExactPayment,
    handleResetPayment,
    handlePaymentConfirmation,
  };
}
