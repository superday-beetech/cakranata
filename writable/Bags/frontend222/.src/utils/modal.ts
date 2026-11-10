
import {
    useModalStore,
    type ModalOptions,
} from "@/composables/modal";

import pinia from "@/stores";

export const modal = {
    open(options: ModalOptions) {
        return useModalStore(pinia).open(options);
    },

    close() {
        return useModalStore(pinia).close();
    },

    reset() {
        return useModalStore(pinia).reset();
    },
};
