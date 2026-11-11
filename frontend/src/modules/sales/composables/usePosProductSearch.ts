import { onBeforeUnmount, watch, type Ref } from "vue";
import type { ProductSearchResult } from "../types/sales";
import productService from "../services/productService";
import { searchPosProducts } from "../services/posProductSearch";
interface UsePosProductSearchOptions {
  keyword: Ref<string>;

  getCart: () => Array<{ product_id: number }>;
  getSearchResults: () => ProductSearchResult[];
  isTransactionLocked: () => boolean;

  searchProduct: () => Promise<void>;
  clearSearch: () => void;
  addProduct: (product: ProductSearchResult) => void;

  clearMessages: () => void;
  showError: (error: unknown) => void;
  setErrorMessage: (message: string) => void;

  playSound: (type: "duplicate" | "success" | "warning" | "error") => void | Promise<void>;

  focusBarcode: () => void;
}

export function usePosProductSearch(options: UsePosProductSearchOptions) {
  let searchTimer: ReturnType<typeof setTimeout> | undefined;

  function cancelSearchTimer(): void {
    if (searchTimer !== undefined) {
      clearTimeout(searchTimer);
      searchTimer = undefined;
    }
  }

  async function handleSearch(): Promise<void> {
    if (!options.keyword.value.trim() || options.isTransactionLocked()) {
      return;
    }

    try {
      await options.searchProduct();
    } catch (error) {
      options.showError(error);
    }
  }

  function handleAddProduct(product: ProductSearchResult): void {
    const duplicate = options.getCart().some((item) => item.product_id === product.id);

    try {
      options.clearMessages();
      options.addProduct(product);

      void options.playSound(duplicate ? "duplicate" : "success");

      options.clearSearch();
      options.focusBarcode();
    } catch (error) {
      void options.playSound("warning");
      options.showError(error);
    }
  }

  async function handleBarcodeEnter(): Promise<void> {
    const barcode = options.keyword.value.trim();

    if (!barcode || options.isTransactionLocked()) {
      return;
    }

    try {
      const products = await searchPosProducts(barcode);
      const needle = barcode.toLowerCase();

      const product = products.find((item) => item.barcode?.trim().toLowerCase() === needle) ?? products.find((item) => item.sku?.trim().toLowerCase() === needle);

      if (!product) {
        await options.searchProduct();

        options.setErrorMessage(options.getSearchResults().length ? "Barcode tidak dikenali. Pilih produk dari daftar (tekan ↓ lalu Enter)." : "Produk tidak ditemukan. Coba ketik nama atau SKU. Jika belum terdaftar, hubungi admin.");

        void options.playSound("warning");
        return;
      }

      handleAddProduct(product);
    } catch (error) {
      void options.playSound("error");
      options.showError(error);
    }
  }

  watch(options.keyword, (value) => {
    cancelSearchTimer();

    if (!value.trim()) {
      options.clearSearch();
      return;
    }

    searchTimer = setTimeout(() => {
      searchTimer = undefined;
      void handleSearch();
    }, 300);
  });

  onBeforeUnmount(() => {
    cancelSearchTimer();
  });

  return {
    handleAddProduct,
    handleSearch,
    handleBarcodeEnter,
    cancelSearchTimer,
  };
}
