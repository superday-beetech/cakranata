import { modal } from "@/utils/modal";
import { useNotificationStore } from "@/composables/useNotification.js";
import ShieldDialog from "../components/ShieldDialog.vue";
import { useRouteRegistryStore } from "../stores/routeRegistryStore";

export interface ShieldDialogPayload {
  readonly items: ReadonlyArray<{ readonly permission: string; readonly description: string }>;
}

export function useShieldDialog() {
  const store = useRouteRegistryStore();
  const notification = useNotificationStore();

  async function open(): Promise<void> {
    let items;
    try {
      items = await store.previewShield();
    } catch {
      return;
    }

    if (items.length === 0) {
      notification.info("Tidak ada permission yang dapat disinkronkan.");
      return;
    }

    modal.open({
      title: "Preview Sinkronisasi Permission",
      size: "xl",
      scrollable: true,
      component: ShieldDialog,
      props: { items },
      onSelect: async (payload: unknown) => {
        const data = payload as ShieldDialogPayload | undefined;
        if (!data) return;
        try {
          await store.syncShield(data.items);
          modal.close();
        } catch {
          // handled in store
        }
      },
    });
  }

  return { open };
}
