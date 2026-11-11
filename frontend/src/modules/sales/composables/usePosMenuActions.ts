import type { AdjustTab } from "./usePosAdjustTabs";
import type { DockEdge } from "./usePosDock";

// type DockEdge = "left" | "right" | "top" | "bottom";

export interface UsePosMenuActionsOptions {
  //   isDraft: () => boolean;
  //   isPosted: () => boolean;

  handleNewTransaction: () => void;
  handleHoldSale: () => void;
  //   handlePostSale: () => void;
  //   handleCreateDraft: () => void;
  handleRefreshHeld: () => void;

  //   setAdjustTab: (tab: "held") => void;
  handleProcessSale: () => void | Promise<void>;
  openAdjustTab: (tab: AdjustTab) => void | Promise<void>;

  focusBarcode: () => void;
  focusCustomer: () => void;
  focusCart: () => void;
  focusPaymentAmount: () => void;

  handleExactPayment: () => void;
  handleResetPayment: () => void;

  toggleSound: () => void;
  toggleDock: () => void;
  toggleDockVisibility: () => void;
  moveDock: (edge: DockEdge, offset: number) => void;

  toggleShortcutHelp: () => void;
  showAbout: () => void;

  openPrinterSettings: () => void;
  openPosSettings: () => void;
  openPaymentSettings: () => void;

  toggleFullscreen: () => void;
  toggleCompact: () => void;
  handleLogout: () => void;

  openPage: (id: string) => void;
  openUrl: (url: string) => void;
}

export function usePosMenuActions(options: UsePosMenuActionsOptions) {
  function runAction(id: string): void {
    switch (id) {
      case "new":
        options.handleNewTransaction();
        break;

      case "hold":
        void options.handleHoldSale();
        break;

      case "process":
        void options.handleProcessSale();
        break;

      case "refresh":
        options.handleRefreshHeld();
        break;

      case "held":
        void options.openAdjustTab("held");
        break;

      case "search":
        options.focusBarcode();
        break;

      case "customer":
        options.focusCustomer();
        break;

      case "cart":
        options.focusCart();
        break;

      case "discount":
        options.openAdjustTab("discount");
        break;

      case "charges":
        options.openAdjustTab("charges");
        break;

      case "payment-amount":
        options.focusPaymentAmount();
        break;

      case "exact":
        options.handleExactPayment();
        break;

      case "reset-payment":
        options.handleResetPayment();
        break;

      case "sound":
        options.toggleSound();
        break;

      case "dock":
        options.toggleDock();
        break;

      case "shortcuts":
        options.toggleShortcutHelp();
        break;

      case "about":
        options.showAbout();
        break;

      case "settings-printer":
        options.openPrinterSettings();
        break;

      case "settings-pos":
        options.openPosSettings();
        break;

      case "settings-payment":
        options.openPaymentSettings();
        break;

      case "fullscreen":
        options.toggleFullscreen();
        break;

      case "compact":
        options.toggleCompact();
        break;

      case "logout":
        options.handleLogout();
        break;

      case "dock-visible":
        options.toggleDockVisibility();
        break;

      default:
        if (id.startsWith("dock-edge-")) {
          const edge = id.slice("dock-edge-".length) as DockEdge;
          options.moveDock(edge, 50);
        } else if (id.startsWith("page-")) {
          options.openPage(id);
        } else if (id.startsWith("url:")) {
          options.openUrl(id.slice(4));
        }
    }
  }

  return { runAction };
}
