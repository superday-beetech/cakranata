import { useNotificationStore } from "@/composables/notification";
import pinia from "@/stores";

export const notify = {
    success(message: string, options = {}) {
        return useNotificationStore(pinia).success(message, options);
    },

    error(message: string, options = {}) {
        return useNotificationStore(pinia).error(message, options);
    },

    warning(message: string, options = {}) {
        return useNotificationStore(pinia).warning(message, options);
    },

    info(message: string, options = {}) {
        return useNotificationStore(pinia).info(message, options);
    },
};
