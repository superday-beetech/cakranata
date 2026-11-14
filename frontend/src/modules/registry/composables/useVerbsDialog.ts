// =============================================================================
// Adaptor dialog HTTP verbs.
// =============================================================================
// Memakai ModalHost global via `modal.open()`. Dialog merender VerbsDialog.vue.

import { ref } from "vue";

import { modal } from "@/utils/modal";

import VerbsDialog from "../components/VerbsDialog.vue";
import { useRouteRegistryStore } from "../stores/routeRegistryStore";
import type { HttpVerb } from "../types/route-registry";

export interface VerbsDialogPayload {
  readonly endpoint: string;
  readonly verbs: readonly HttpVerb[];
}

export function useVerbsDialog() {
  const store = useRouteRegistryStore();
  const busy = ref(false);

  async function open(id: number, endpoint: string): Promise<void> {
    if (id < 1) return;

    let verbs: HttpVerb[] = [];
    try {
      const result = await store.fetchVerbs(id);
      verbs = result.verbs;
    } catch {
      return; // sudah dinotifikasi store
    }

    modal.open({
      title: "HTTP verbs",
      size: "lg",
      centered: true,
      component: VerbsDialog,
      props: { endpoint, verbs },
      onSelect: async (payload: unknown) => {
        const data = payload as VerbsDialogPayload | undefined;
        if (!data) return;

        busy.value = true;
        try {
          await store.saveVerbs(data.endpoint, [...data.verbs]);
          modal.close();
        } catch {
          // sudah dinotifikasi store — biarkan modal terbuka
        } finally {
          busy.value = false;
        }
      },
    });
  }

  return { open, busy };
}
