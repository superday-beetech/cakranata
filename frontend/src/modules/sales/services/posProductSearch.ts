import type { ProductSearchResult } from "../types/sales";
import productService from "./productService";

import { cachePosProducts, searchCachedPosProducts } from "@/offline/repositories/offlineRepository";

const SEARCH_LIMIT = 20;

/**
 * Pencarian produk POS.
 *
 * Online:
 * - Ambil data terbaru dari server.
 * - Simpan hasil pencarian ke IndexedDB.
 *
 * Offline / API gagal:
 * - Gunakan hasil pencarian dari IndexedDB.
 *
 * Stok dan harga dari cache bukan validasi final server.
 */
export async function searchPosProducts(searchKeyword: string): Promise<ProductSearchResult[]> {
  const keyword = searchKeyword.trim();

  if (!keyword) {
    return [];
  }

  // Hindari menunggu timeout API ketika browser sudah mengetahui bahwa jaringan putus.
  if (typeof navigator !== "undefined" && !navigator.onLine) {
    return searchCachedPosProducts(keyword);
  }

  try {
    const response = await productService.getAll({
      page: 1,
      per_page: SEARCH_LIMIT,
      search: keyword,
      is_active: 1,
    });

    if (response.data.error) {
      const apiMessage = response.data.message;

      throw new Error(Array.isArray(apiMessage) ? apiMessage.join(", ") : apiMessage || "Gagal mengambil data produk.");
    }

    const products = response.data.data?.items ?? [];

    const results: ProductSearchResult[] = products.map((product) => ({
      id: product.id,
      sku: product.sku ?? "",
      barcode: product.barcode ?? null,
      name: product.name,
      unit_name: product.unit_name ?? undefined,
      selling_price: Number(product.selling_price),
      available_stock: product.stock_available === undefined ? undefined : Number(product.stock_available),
      is_active: Number(product.is_active) === 1,
    }));

    // Cache hanya hasil dari server yang berhasil.
    await cachePosProducts(results);

    return results;
  } catch (serverError) {
    const cachedResults = await searchCachedPosProducts(keyword);

    if (cachedResults.length > 0) {
      return cachedResults;
    }

    // Jangan menutupi error server jika cache tidak punya hasil.
    throw serverError;
  }
}

/* import type { ProductSearchResult } from "../types/sales";
import productService from "./productService";

const SEARCH_LIMIT = 20;

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
 */
