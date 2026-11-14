interface HeldSale {
  id: number;
  sale_number: string;
}

interface UsePosTransactionActionsOptions {
  hasUnsupportedDraftValues: () => boolean;
  canPostSale: () => boolean;
  isPaymentValid: () => boolean;
  isPosted: () => boolean;

  createDraft: () => Promise<unknown>;
  postCurrentSale: () => Promise<unknown>;
  holdCurrentSale: () => Promise<unknown>;
  resumeHeldTransaction: (saleId: number) => Promise<unknown>;
  loadHeldTransactions: () => Promise<unknown>;
  newTransaction: () => void;

  clearMessages: () => void;
  setErrorMessage: (message: string) => void;
  showSuccess: (message: string) => void;
  showError: (error: unknown) => void;

  playTransactionSound: () => void;
  focusBarcode: () => void;
  isDraft: () => boolean;
  checkoutCurrentSale: () => Promise<unknown>;
  cancelCurrentSale: () => Promise<unknown>;
}

export function usePosTransactionActions(options: UsePosTransactionActionsOptions) {
  async function handleCreateDraft(): Promise<void> {
    options.clearMessages();

    if (options.hasUnsupportedDraftValues()) {
      options.setErrorMessage("Biaya tambahan belum didukung oleh payload backend penjualan saat ini.");
      return;
    }

    try {
      await options.createDraft();
      options.showSuccess("Draft penjualan berhasil dibuat.");
      options.focusBarcode();
    } catch (error) {
      options.showError(error);
    }
  }

  async function handlePostSale(): Promise<void> {
    options.clearMessages();

    if (options.hasUnsupportedDraftValues()) {
      options.setErrorMessage("Biaya tambahan belum didukung oleh backend penjualan.");
      return;
    }

    if (!options.canPostSale() || !options.isPaymentValid()) {
      options.setErrorMessage("Pembayaran belum valid. Periksa metode, nominal, jatuh tempo, dan konfirmasi pembayaran.");
      return;
    }

    try {
      await options.postCurrentSale();

      options.playTransactionSound();
      options.showSuccess("Penjualan berhasil diposting.");

      await options.loadHeldTransactions();
      options.focusBarcode();
    } catch (error) {
      options.showError(error);
    }
  }

  async function handleCheckout(): Promise<void> {
    options.clearMessages();

    if (options.hasUnsupportedDraftValues()) {
      options.setErrorMessage("Biaya tambahan belum didukung oleh backend penjualan.");
      return;
    }

    if (!options.isPaymentValid()) {
      options.setErrorMessage("Pembayaran belum valid. Periksa metode, nominal, jatuh tempo, dan konfirmasi pembayaran.");
      return;
    }

    try {
      await options.checkoutCurrentSale();

      options.playTransactionSound();
      options.showSuccess("Penjualan berhasil diposting.");

      await options.loadHeldTransactions();
      options.focusBarcode();
    } catch (error) {
      options.showError(error);
    }
  }
  // fungsi baru
  async function handleCancelDraft(): Promise<void> {
    if (!options.isDraft()) return;

    options.clearMessages();

    try {
      await options.cancelCurrentSale();
      options.showSuccess("Draft dibatalkan.");
      options.focusBarcode();
    } catch (error) {
      options.showError(error);
    }
  }
  async function handleHoldSale(): Promise<void> {
    if (options.isPosted()) return;

    options.clearMessages();

    try {
      await options.holdCurrentSale();
      await options.loadHeldTransactions();

      options.showSuccess("Transaksi berhasil ditahan.");
      options.focusBarcode();
    } catch (error) {
      options.showError(error);
    }
  }
  async function handleProcessSale(): Promise<void> {
    if (options.isDraft()) {
      await handlePostSale(); // draft hasil melanjutkan transaksi tertahan
    } else if (!options.isPosted()) {
      await handleCheckout();
    }
  }

  async function handleResumeSale(sale: HeldSale): Promise<void> {
    options.clearMessages();

    try {
      await options.resumeHeldTransaction(sale.id);

      options.showSuccess(`Transaksi ${sale.sale_number} berhasil dilanjutkan.`);
    } catch (error) {
      options.showError(error);
    }
  }

  async function handleNewTransaction(): Promise<void> {
    if (!options.isPosted()) return;

    try {
      options.newTransaction();

      options.clearMessages();
      options.focusBarcode();
    } catch (error) {
      options.showError(error);
    }
  }

  async function handleRefreshHeld(): Promise<void> {
    try {
      await options.loadHeldTransactions();
    } catch (error) {
      options.showError(error);
    }
  }

  return {
    handleCreateDraft,
    handlePostSale,
    handleHoldSale,
    handleResumeSale,
    handleNewTransaction,
    handleRefreshHeld,
    handleProcessSale,
    handleCheckout,
    handleCancelDraft,
  };
}
