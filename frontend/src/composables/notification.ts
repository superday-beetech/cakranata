
import { defineStore } from "pinia";

export type NotificationType =
    | "success"
    | "error"
    | "warning"
    | "info";

export interface NotificationOptions {
    title?: string;
    duration?: number;
    autoClose?: boolean;
    closable?: boolean;
}

export interface NotificationItem {
    id: number;
    type: NotificationType;
    title: string;
    message: string;
    duration: number;
    autoClose: boolean;
    closable: boolean;
}

interface NotificationState {
    items: NotificationItem[];
}

const DEFAULT_DURATION = 5000;
const MAX_NOTIFICATIONS = 5;

let notificationId = 0;

const timers = new Map<number, ReturnType<typeof setTimeout>>();

export const useNotificationStore = defineStore("notification", {
    state: (): NotificationState => ({
        items: [],
    }),

    actions: {
        show(
            type: NotificationType,
            message: string,
            options: NotificationOptions = {}
        ): number {
            const id = ++notificationId;

            const item: NotificationItem = {
                id,
                type,
                title: options.title ?? this.getDefaultTitle(type),
                message,
                duration: Math.max(
                    0,
                    options.duration ?? DEFAULT_DURATION
                ),
                autoClose: options.autoClose ?? true,
                closable: options.closable ?? true,
            };

            // Batasi jumlah notifikasi aktif.
            while (this.items.length >= MAX_NOTIFICATIONS) {
                this.remove(this.items[0].id);
            }

            this.items.push(item);

            if (item.autoClose && item.duration > 0) {
                this.startTimer(id, item.duration);
            }

            return id;
        },

        success(message: string, options?: NotificationOptions) {
            return this.show("success", message, options);
        },

        error(message: string, options?: NotificationOptions) {
            return this.show("error", message, options);
        },

        warning(message: string, options?: NotificationOptions) {
            return this.show("warning", message, options);
        },

        info(message: string, options?: NotificationOptions) {
            return this.show("info", message, options);
        },

        startTimer(id: number, duration: number) {
            this.clearTimer(id);

            const timer = setTimeout(() => {
                this.remove(id);
            }, duration);

            timers.set(id, timer);
        },

        clearTimer(id: number) {
            const timer = timers.get(id);

            if (timer) {
                clearTimeout(timer);
                timers.delete(id);
            }
        },

        remove(id: number) {
            this.clearTimer(id);

            this.items = this.items.filter(
                item => item.id !== id
            );
        },

        clearAll() {
            for (const id of timers.keys()) {
                this.clearTimer(id);
            }

            this.items = [];
        },

        getDefaultTitle(type: NotificationType): string {
            const titles: Record<NotificationType, string> = {
                success: "Berhasil",
                error: "Terjadi Kesalahan",
                warning: "Peringatan",
                info: "Informasi",
            };

            return titles[type];
        },
    },
});
