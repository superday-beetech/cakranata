import { computed, type Ref } from "vue";

type SettlementMode = "paid" | "partial" | "receivable";

interface UsePosTransactionStateOptions {
  cart: Ref<unknown[]>;
  isDraft: Ref<boolean>;
  isPosted: Ref<boolean>;
  additionalChargesTotal: Ref<number>;
  settlementMode: Ref<SettlementMode>;
}

export function usePosTransactionState(options: UsePosTransactionStateOptions) {
  const hasUnsavedCart = computed(() => options.cart.value.length > 0 && !options.isDraft.value && !options.isPosted.value);

  const hasUnsupportedDraftValues = computed(() => options.additionalChargesTotal.value > 0);

  const saleStatus = computed(() => {
    if (options.isPosted.value) return "Selesai";
    if (options.isDraft.value) return "Draft";
    return "Baru";
  });

  const settlementLabel = computed(() => {
    switch (options.settlementMode.value) {
      case "partial":
        return "Tempo - Bayar Sebagian";
      case "receivable":
        return "Tempo - Seluruhnya Piutang";
      default:
        return "Lunas";
    }
  });

  return {
    hasUnsavedCart,
    hasUnsupportedDraftValues,
    saleStatus,
    settlementLabel,
  };
}
