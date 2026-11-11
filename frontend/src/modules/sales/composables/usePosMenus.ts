import { computed } from "vue";

import { useMenuStore } from "@/stores/menu";
import { usePermissionStore } from "@/stores/permission";
import type { MenuItem } from "@/types/menu";

import type { SettlementMode } from "../types/sales";
import type { DockState } from "./usePosDock";
import { POS_ROUTES } from "../utils/posRoutes";

/** Isi kode permission bila pengaturan POS perlu dibatasi. Kosong = terbuka untuk semua kasir. */
export const POS_PERMISSIONS: { settings?: string; paymentIntegration?: string } = {};

// const POS_ROUTE_FRAGMENT = "/pharmacy/sales/pos";
const MAX_PAGE_MENU_ITEMS = 20;

interface MenuEntry {
  id?: string;
  label?: string;
  checked?: boolean;
  disabled?: boolean;
  separator?: boolean;
}

interface UsePosMenusOptions {
  dock: DockState;
  isSoundEnabled: () => boolean;
  isFullscreen: () => boolean;
  isCompact: () => boolean;
  cashierName: () => string;

  settlementMode: () => SettlementMode;
  isDraft: () => boolean;
  isPosted: () => boolean;
  isProcessing: () => boolean;
}

function flattenMenu(items: MenuItem[]): MenuItem[] {
  return items.flatMap((item) => {
    if (item.children?.length) return flattenMenu(item.children);
    if (!item.url || item.url === "#" || item.is_divider || !item.is_active) return [];

    return [item];
  });
}

/** Menu bar dan item dock. Murni presentasi: id aksi dieksekusi oleh usePosMenuActions. */
export function usePosMenus(options: UsePosMenusOptions) {
  const menuStore = useMenuStore();
  const permission = usePermissionStore();

  function can(code?: string): boolean {
    return !code || permission.hasPermission(code);
  }

  // Ganti sumbernya (quick_links / apps / sidebar) sesuai isi menu Anda.
  const pageMenuItems = computed<MenuEntry[]>(() => {
    const source = menuStore.menus.quick_links.length ? menuStore.menus.quick_links : menuStore.menus.apps;

    return flattenMenu(source)
      .filter((item) => !String(item.url).includes(POS_ROUTES.pos))
      .slice(0, MAX_PAGE_MENU_ITEMS)
      .map((item) => ({ id: `url:${item.url}`, label: item.title }));
  });

  const menus = computed(() => [
    {
      id: "sales",
      label: "Penjualan",
      items: [
        { id: "sound", label: "Suara", checked: options.isSoundEnabled() },
        { separator: true },
        { id: "dock-visible", label: "Toolbar melayang", checked: options.dock.visible },
        { id: "dock-edge-right", label: "Posisi toolbar: kanan", checked: options.dock.edge === "right" },
        { id: "dock-edge-left", label: "Posisi toolbar: kiri", checked: options.dock.edge === "left" },
        { id: "dock-edge-top", label: "Posisi toolbar: atas", checked: options.dock.edge === "top" },
        { id: "dock-edge-bottom", label: "Posisi toolbar: bawah", checked: options.dock.edge === "bottom" },
      ] as MenuEntry[],
    },
    {
      id: "pages",
      label: "Halaman",
      items: pageMenuItems.value.length ? pageMenuItems.value : ([{ label: menuStore.loading ? "Memuat…" : "Tidak ada halaman", disabled: true }] as MenuEntry[]),
    },
    {
      id: "settings",
      label: "Pengaturan",
      items: [
        { id: "settings-printer", label: "Printer & struk" },
        { id: "settings-pos", label: "Pengaturan POS", disabled: !can(POS_PERMISSIONS.settings) },
        { id: "settings-payment", label: "Integrasi pembayaran", disabled: !can(POS_PERMISSIONS.paymentIntegration) },
      ] as MenuEntry[],
    },
    {
      id: "view",
      label: "Tampilan",
      items: [
        { id: "fullscreen", label: "Layar penuh", checked: options.isFullscreen() },
        { id: "compact", label: "Mode ringkas", checked: options.isCompact() },
      ] as MenuEntry[],
    },
    {
      id: "account",
      label: "Akun",
      items: [{ label: `Kasir: ${options.cashierName() || "-"}`, disabled: true }, { separator: true }, { id: "logout", label: "Keluar (logout)" }] as MenuEntry[],
    },
    {
      id: "help",
      label: "Bantuan",
      items: [{ id: "shortcuts", label: "Daftar shortcut keyboard" }, { separator: true }, { id: "about", label: "Tentang" }] as MenuEntry[],
    },
  ]);

  const dockItems = computed(() => [
    { id: "search", icon: "ti ti-barcode", label: "Cari produk", shortcut: "F1" },
    { id: "customer", icon: "ti ti-user", label: "Pelanggan", shortcut: "F2" },
    { id: "discount", icon: "ti ti-discount-2", label: "Diskon", shortcut: "F4" },
    { id: "exact", icon: "ti ti-check", label: "Uang pas", shortcut: "F9", disabled: options.settlementMode() !== "paid" },
    { id: "hold", icon: "ti ti-player-pause", label: "Tahan", shortcut: "F10", disabled: !options.isDraft() },
    { id: "new", icon: "ti ti-plus", label: "Transaksi baru", shortcut: "F11" },
    { id: "process", icon: "ti ti-device-floppy", label: options.isDraft() ? "Proses transaksi" : "Buat draft", shortcut: "F12", disabled: options.isPosted() || options.isProcessing() },
    { id: "shortcuts", icon: "ti ti-keyboard", label: "Bantuan shortcut" },
  ]);

  // Dimuat saat halaman dibuka (lihat usePosLifecycle); kegagalan tidak boleh menghalangi kasir.
  const loadMenu = (): Promise<unknown> => menuStore.load();
  const loadPermission = (): Promise<unknown> => permission.load();

  return { menus, dockItems, loadMenu, loadPermission };
}
