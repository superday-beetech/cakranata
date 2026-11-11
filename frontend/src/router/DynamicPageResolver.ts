import type { Component } from "vue";

export type DynamicPageLoader = () => Promise<Component>;

type PageStructure = {
  module: string;
  domain: string | null;
  filename: string;
  loader: DynamicPageLoader;
};

const pages = import.meta.glob<Component>(["../modules/*/views/**/*.vue", "../modules/*/*/views/**/*.vue"]);

/**
 * Normalisasi nama URL dan nama file.
 *
 * stock-movements -> stockmovements
 * StockMovementsView -> stockmovements
 */
function normalizePageName(value: string): string {
  return value
    .replace(/\.vue$/i, "")
    .replace(/view$/i, "")
    .replace(/[^a-zA-Z0-9]/g, "")
    .toLowerCase();
}
function normalizeSlug(value: string): string {
  return value.replace(/[^a-zA-Z0-9]/g, "").toLowerCase();
}

function normalizeFilename(value: string): string {
  return normalizeSlug(value.replace(/\.vue$/i, "").replace(/View$/i, ""));
}
/**
 * Alias URL berdasarkan module/domain.
 *
 * Format:
 * "module/domain": {
 *     "url-slug": ["NamaView"]
 * }
 */
const pageAliases: Record<string, Record<string, string[]>> = {
  "organization/master": {
    organizations: ["organization"],
    "organization-units": ["organizationunits"],
    "organization-memberships": ["organizationmemberships"],
    "organization-selection": ["organizationselection"],
  },

  "organization/unit": {
    "organization-units": ["organizationunits"],
  },

  "pharmacy/master": {
    categories: ["category"],
    units: ["unit"],
    products: ["product"],
    suppliers: ["supplier"],
    customers: ["customer"],
  },

  "pharmacy/inventory": {
    batches: ["productbatch"],
    "product-batches": ["productbatch"],
    movements: ["stockmovements"],
    "stock-movements": ["stockmovements"],
    "stock-overview": ["stockoverview"],
    "stock-opnames": ["stockopname"],
  },

  "pharmacy/procurement": {
    "purchase-orders": ["purchaseorder"],
    "goods-receipts": ["goodsreceipt"],
  },

  "pharmacy/returns": {
    "purchase-returns": ["purchase"],
    "sales-returns": ["sales"],
  },

  "pharmacy/distribution": {
    deliveries: ["delivery"],
    "distribution-orders": ["distributionorder"],
  },

  "pharmacy/finance": {
    "cash-transactions": ["cashtransaction"],
    "customer-payments": ["customerpayment"],
    "supplier-payments": ["supplierpayment"],
    "finance-dashboard": ["financedashboard"],
  },

  "plugins/sales": {
    "sales-list": ["saleslist"],
  },
};

/**
 * Membentuk indeks halaman dari hasil import.meta.glob.
 */
const pageIndex: PageStructure[] = Object.entries(pages)
  .map(([path, loader]): PageStructure | null => {
    const match = path.match(/^\.\.\/modules\/([^/]+)\/(?:([^/]+)\/)?views\/(.+)\.vue$/i);

    if (!match) {
      return null;
    }

    const [, module, domain, relativePath] = match;

    const filename = relativePath.split("/").at(-1) ?? "";

    return {
      module: module.toLowerCase(),
      domain: domain?.toLowerCase() ?? null,
      filename: normalizeFilename(filename),
      loader,
    };
  })
  .filter((page): page is PageStructure => page !== null);

/**
 * Resolver halaman dinamis.
 *
 * Modul lama:
 * /backend/navigation
 *
 * Modul berbasis domain:
 * /pharmacy/master/categories
 * /pharmacy/inventory/stock-movements
 * /organization/master/organizations
 */
export function resolveDynamicPage(url: string): DynamicPageLoader | null {
  const segments = url
    .split(/[?#]/, 1)[0]
    .replace(/^\/+|\/+$/g, "")
    .split("/")
    .filter(Boolean);

  // Hanya menerima:
  // /module/page
  // /module/domain/page
  if (segments.length !== 2 && segments.length !== 3) {
    return null;
  }

  const moduleName = segments[0].toLowerCase();

  const domainName = segments.length === 3 ? segments[1].toLowerCase() : null;

  const pageName = segments.at(-1)!.toLowerCase();

  // Normalisasi nama halaman URL.
  const normalizedPage = normalizeSlug(pageName);

  // Ambil alias berdasarkan module/domain.
  const aliasKey = domainName ? `${moduleName}/${domainName}` : "";

  const aliases = pageAliases[aliasKey]?.[pageName] ?? [];

  const acceptedNames = new Set([normalizedPage, ...aliases.map(normalizeSlug)]);

  // Cari halaman berdasarkan struktur URL.
  const matches = pageIndex.filter((page) => {
    // Modul harus sama.
    if (page.module !== moduleName) {
      return false;
    }

    // URL domain hanya boleh menemukan folder domain.
    if (domainName !== null) {
      if (page.domain !== domainName) {
        return false;
      }
    } else {
      // URL legacy hanya boleh menemukan views langsung.
      if (page.domain !== null) {
        return false;
      }
    }

    return acceptedNames.has(page.filename);
  });

  // Jangan memilih halaman secara acak jika ambigu.
  if (matches.length !== 1) {
    if (import.meta.env.DEV) {
      console.warn(
        `[DynamicPageResolver] ${matches.length === 0 ? "Page not found" : "Ambiguous page"}: ${url}`,
        matches.map((page) => ({
          module: page.module,
          domain: page.domain,
          filename: page.filename,
        })),
      );
    }

    return null;
  }

  return matches[0].loader;
}
