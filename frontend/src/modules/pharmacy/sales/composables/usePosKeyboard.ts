import type { PaymentMethod, SettlementMode } from "../types/sales";
import { POS_DENOMINATIONS } from "../utils/posDenominations";
import type { AdjustTab } from "./usePosAdjustTabs";

interface UsePosKeyboardOptions {
  hostIsOpen: () => boolean;

  isProcessing: () => boolean;
  isPosted: () => boolean;
  getSettlementMode: () => SettlementMode;
  getPaymentMethod: () => PaymentMethod | null;

  setShortcutHelp: (visible: boolean) => void;

  focusBarcode: () => void;
  focusCustomer: () => void;
  focusCart: () => void;
  focusPayment: () => void;
  focusPaymentAmount: () => void;

  openAdjustTab: (tab: AdjustTab) => Promise<void>;

  handleResetPayment: () => void;
  handleExactPayment: () => void;
  handlePaymentAmount: (amount: number) => void;

  handleHoldSale: () => void | Promise<void>;
  handleNewTransaction: () => void | Promise<void>;
  handleProcessSale: () => void | Promise<void>;
}

function isTypingTarget(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) return false;

  return target.isContentEditable || ["INPUT", "TEXTAREA", "SELECT"].includes(target.tagName);
}

export function usePosKeyboard(options: UsePosKeyboardOptions) {
  function handleDenominationShortcut(event: KeyboardEvent): void {
    if (options.hostIsOpen()) return;

    if (!event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
      return;
    }

    const match = /^(?:Digit|Numpad)([0-8])$/.exec(event.code);

    if (!match) return;

    event.preventDefault();

    if (options.isProcessing() || options.isPosted() || options.getSettlementMode() === "receivable") {
      return;
    }

    if ((options.getPaymentMethod() ?? "cash") !== "cash") return;

    const digit = Number(match[1]);

    if (digit === 0) {
      options.handleResetPayment();
    } else if (digit === 8) {
      options.handleExactPayment();
    } else {
      options.handlePaymentAmount(POS_DENOMINATIONS[digit - 1]);
    }
  }

  function handleKeyboard(event: KeyboardEvent): void {
    if (options.hostIsOpen()) return;

    if (event.ctrlKey || event.altKey || event.metaKey || isTypingTarget(event.target)) {
      return;
    }

    switch (event.key) {
      case "Escape":
        options.setShortcutHelp(false);
        break;

      case "F1":
        event.preventDefault();
        options.focusBarcode();
        break;

      case "F2":
        event.preventDefault();
        options.focusCustomer();
        break;

      case "F3":
        event.preventDefault();
        options.focusCart();
        break;

      case "F4":
        event.preventDefault();
        void options.openAdjustTab("discount");
        break;

      case "F5":
        event.preventDefault();
        void options.openAdjustTab("charges");
        break;

      case "F6":
      case "F7":
        event.preventDefault();
        options.focusPayment();
        break;

      case "F8":
        event.preventDefault();
        options.focusPaymentAmount();
        break;

      case "F9":
        event.preventDefault();
        options.handleExactPayment();
        break;

      case "F10":
        event.preventDefault();
        void options.handleHoldSale();
        break;

      case "F11":
        event.preventDefault();
        void options.handleNewTransaction();
        break;

      case "F12":
        event.preventDefault();
        void options.handleProcessSale();
        break;
    }
  }

  return {
    handleKeyboard,
    handleDenominationShortcut,
  };
}
