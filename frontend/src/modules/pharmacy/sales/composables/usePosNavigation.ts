import { useRouter } from "vue-router";

import type { SignOutResult } from "./usePosSession";
import { POS_ROUTES } from "../utils/posRoutes";

interface PageLink {
  to: string;
  /** true = buka di tab baru agar transaksi berjalan tidak hilang. */
  newTab: boolean;
}

const PAGE_LINKS: Record<string, PageLink> = {
  "page-dashboard": { to: POS_ROUTES.dashboard, newTab: false },
  "page-sales": { to: POS_ROUTES.salesReport, newTab: true },
};

// const LOGIN_ROUTE = "/login";

const DISCARD_CART_PAGE = "Keranjang belum disimpan dan akan hilang. Tetap keluar dari halaman kasir?";
const DISCARD_CART_LOGOUT = "Keranjang belum disimpan dan akan hilang. Tetap keluar?";

interface UsePosNavigationOptions {
  /** Keranjang berisi item yang belum menjadi draft di server. */
  hasUnsavedCart: () => boolean;
  signOut: () => Promise<SignOutResult>;
  notifyError: (message: string) => void;
}

export function usePosNavigation(options: UsePosNavigationOptions) {
  const router = useRouter();

  function confirmDiscardCart(message: string): boolean {
    return !options.hasUnsavedCart() || window.confirm(message);
  }

  function openInNewTab(href: string): void {
    window.open(href, "_blank", "noopener");
  }

  function openUrl(url: string): void {
    const isAbsolute = /^(https?:)?\/\//i.test(url);

    openInNewTab(isAbsolute ? url : router.resolve(url.startsWith("/") ? url : `/${url}`).href);
  }

  function openPage(id: string): void {
    const link = PAGE_LINKS[id];

    if (!link) return;

    if (link.newTab) {
      openInNewTab(router.resolve(link.to).href);
      return;
    }

    // Draft sudah tersimpan di server; hanya keranjang yang belum disimpan akan hilang.
    if (!confirmDiscardCart(DISCARD_CART_PAGE)) return;

    void router.push(link.to);
  }

  function goToDashboard(): void {
    openPage("page-dashboard");
  }

  async function handleLogout(): Promise<void> {
    if (!confirmDiscardCart(DISCARD_CART_LOGOUT)) return;

    const result = await options.signOut();

    if (result.success) {
      await router.replace(POS_ROUTES.login);
    } else if (result.error) {
      options.notifyError(result.error);
    }
  }

  return { goToDashboard, openPage, openUrl, handleLogout };
}
