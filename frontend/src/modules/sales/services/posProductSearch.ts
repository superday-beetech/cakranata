import type { ProductSearchResult } from "../types/sales";
import productService from "./productService";

const SEARCH_LIMIT = 20;

/** Mengambil produk aktif untuk pencarian POS dan memetakannya ke ProductSearchResult. */
export async function searchPosProducts(searchKeyword: string): Promise<ProductSearchResult[]> {
  const response = await productService.getAll({
    page: 1,
    per_page: SEARCH_LIMIT,
    search: searchKeyword,
    is_active: 1,
  });

  if (response.data.error) {
    const apiMessage = response.data.message;

    throw new Error(Array.isArray(apiMessage) ? apiMessage.join(", ") : apiMessage || "Gagal mengambil data produk.");
  }

  const products = response.data.data?.items ?? [];

  return products.map((product) => ({
    id: product.id,
    sku: product.sku ?? "",
    barcode: product.barcode ?? null,
    name: product.name,
    unit_name: product.unit_name ?? undefined,
    selling_price: Number(product.selling_price),
    available_stock: product.stock_available === undefined ? undefined : Number(product.stock_available),
    is_active: Number(product.is_active) === 1,
  }));
}
