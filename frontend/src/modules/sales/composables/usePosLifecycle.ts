import { onBeforeUnmount, onMounted } from "vue";

interface UsePosLifecycleOptions {
  handleKeyboard: (event: KeyboardEvent) => void;
  handleDenominationShortcut: (event: KeyboardEvent) => void;

  loadHeldTransactions: () => Promise<unknown>;
  showError: (error: unknown) => void;
  focusBarcode: () => void;

  loadMenu: () => Promise<unknown>;
  loadPermission: () => Promise<unknown>;

  rememberOrganization: () => void;
  syncOrganization: () => Promise<void>;
  syncFullscreen: () => void;

  hasUnsavedCart: () => boolean;
}

export function usePosLifecycle(options: UsePosLifecycleOptions) {
  const onWindowFocus = (): void => {
    void options.syncOrganization();
  };

  // Cegah kasir menutup tab saat keranjang belum disimpan.
  const onBeforeUnload = (event: BeforeUnloadEvent): void => {
    if (!options.hasUnsavedCart()) return;

    event.preventDefault();
    event.returnValue = "";
  };

  async function loadHeld(): Promise<void> {
    try {
      await options.loadHeldTransactions();
    } catch (error) {
      options.showError(error);
    }
  }

  onMounted(() => {
    // Semua listener dipasang sinkron agar selalu terlepas lagi di onBeforeUnmount.
    window.addEventListener("keydown", options.handleKeyboard);
    window.addEventListener("keydown", options.handleDenominationShortcut);
    window.addEventListener("focus", onWindowFocus);
    window.addEventListener("beforeunload", onBeforeUnload);
    document.addEventListener("fullscreenchange", options.syncFullscreen);

    options.syncFullscreen();
    options.rememberOrganization();

    // Kasir bisa langsung scan tanpa menunggu data lain selesai dimuat.
    options.focusBarcode();

    void loadHeld();
    void options.loadMenu().catch(() => undefined);
    void options.loadPermission().catch(() => undefined);
  });

  onBeforeUnmount(() => {
    window.removeEventListener("keydown", options.handleKeyboard);
    window.removeEventListener("keydown", options.handleDenominationShortcut);
    window.removeEventListener("focus", onWindowFocus);
    window.removeEventListener("beforeunload", onBeforeUnload);
    document.removeEventListener("fullscreenchange", options.syncFullscreen);
  });
}
