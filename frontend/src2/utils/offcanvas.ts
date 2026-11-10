
import { useOffcanvasStore, type OffcanvasOptions } from "@/composables/offcanvas";
import pinia from "@/stores";

export const offcanvas = {
    open(options: OffcanvasOptions) {
        return useOffcanvasStore(pinia).open(options);
    },

    close() {
        return useOffcanvasStore(pinia).close();
    },

    reset() {
        return useOffcanvasStore(pinia).reset();
    },
};
