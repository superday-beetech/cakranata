// Membuka modal HTTP verbs via `modal.open()` (utils/modal) yang
// menggunakan ModalHost global. Modal akan merender VerbsModalContent.
//
// Alur:
//   1. open(id, endpoint) → muat verbs dari API
//   2. modal.open({ component: VerbsModalContent, props: { endpoint, verbs } })
//   3. User pilih verbs → emit 'select' → onSelect(payload)
//   4. onSelect → store.saveVerbs(...) → modal.close()

import { ref } from "vue";

import { modal } from "@/utils/modal";

import VerbsDialog from "../components/VerbsDialog.vue";
import type { HttpVerb } from "../types/route-registry.js";
import { useRouteRegistryStore } from "./useRouteRegistryStore.js";

export interface VerbsModalPayload {
  endpoint: string;
  verbs: HttpVerb[];
}

export function useVerbsModal() {
  const store = useRouteRegistryStore();

  /** True saat menyimpan verbs ke server. */
  const busy = ref(false);

  /**
   * Buka modal HTTP verbs untuk satu endpoint.
   * Jika endpoint belum punya ID registry, pemanggil sebaiknya menonaktifkan tombolnya.
   */
  async function open(id: number, endpoint: string): Promise<void> {
    if (id < 1) {
      // Tidak ada ID → tidak mungkin simpan. Diamkan saja.
      return;
    }

    // Muat verbs terbaru dari server.
    let verbs: HttpVerb[] = [];
    try {
      const payload = await store.getVerbs(id);
      verbs = payload.verbs;
    } catch {
      // getVerbs sudah menotifikasi error lewat store → cukup berhenti.
      return;
    }

    modal.open({
      title: "HTTP verbs",
      size: "lg",
      centered: true,
      component: VerbsDialog,
      props: { endpoint, verbs },
      onSelect: async (payload: unknown) => {
        const data = payload as VerbsModalPayload | undefined;
        if (!data) return;

        busy.value = true;
        try {
          await store.saveVerbs(data.endpoint, data.verbs);
          modal.close();
        } catch {
          // saveVerbs sudah menotifikasi error lewat store → biarkan modal terbuka.
        } finally {
          busy.value = false;
        }
      },
    });
  }

  return { open, busy };
}
