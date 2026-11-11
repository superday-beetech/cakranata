import type { PaymentMethod, SettlementMode } from "../types/sales";

interface UsePosPaymentHandlersOptions {
  getSettlementMode: () => SettlementMode;

  setSettlementMode: (mode: SettlementMode) => void;
  setPaymentAmount: (value: number) => void;
  setPaymentMethod: (method: PaymentMethod) => void;
  setDueDate: (value: string) => void;
  setPaymentReferenceNumber: (value: string) => void;
  payExact: () => void;
  resetPaymentAmount: () => void;
  setPaymentConfirmed: (value: boolean) => void;
}

export function usePosPaymentHandlers(options: UsePosPaymentHandlersOptions) {
  function handleSettlementMode(mode: SettlementMode): void {
    options.setSettlementMode(mode);
  }

  function handlePaymentAmount(value: number): void {
    options.setPaymentAmount(Math.max(0, value));
  }

  function handlePaymentMethod(method: PaymentMethod): void {
    options.setPaymentMethod(method);
  }

  function handleDueDate(value: string): void {
    options.setDueDate(value);
  }

  function handleReferenceNumber(value: string): void {
    options.setPaymentReferenceNumber(value);
  }

  function handleExactPayment(): void {
    if (options.getSettlementMode() !== "paid") return;

    options.payExact();
  }

  function handleResetPayment(): void {
    options.resetPaymentAmount();
  }

  function handlePaymentConfirmation(value: boolean): void {
    options.setPaymentConfirmed(value);
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
