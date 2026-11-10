import { defineAsyncComponent } from "vue";

import type { usePosHost } from "./usePosHost";

type PosHost = ReturnType<typeof usePosHost>;

const PosPrinterSettings = defineAsyncComponent(() => import("../components/settings/PosPrinterSettings.vue"));
const PosGeneralSettings = defineAsyncComponent(() => import("../components/settings/PosGeneralSettings.vue"));
const PosPaymentIntegration = defineAsyncComponent(() => import("../components/settings/PosPaymentIntegration.vue"));

/** Dialog pengaturan POS (offcanvas/modal global). Komponennya dimuat lazy. */
export function usePosSettingsDialogs(host: PosHost) {
  function openPrinterSettings(): void {
    host.openOffCanvas(PosPrinterSettings, { title: "Printer & struk" });
  }

  function openPosSettings(): void {
    host.openOffCanvas(PosGeneralSettings, { title: "Pengaturan POS" });
  }

  function openPaymentSettings(): void {
    host.openModal(PosPaymentIntegration, { title: "Integrasi pembayaran", size: "lg" });
  }

  return { openPrinterSettings, openPosSettings, openPaymentSettings };
}
