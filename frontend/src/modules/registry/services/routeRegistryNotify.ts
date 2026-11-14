// =============================================================================
// Adapter notifikasi — SATU-SATUNYA tempat modul ini berbicara ke NotificationHost.
// =============================================================================
// Saat ini memakai CustomEvent `app:notify`. Jika project Anda sudah punya
// `useNotify()`/`useToast()`, ganti implementasi di file ini saja — pemanggil
// (toast.success/error/warning/info) tidak perlu berubah.

export type NotifyType = "success" | "error" | "warning" | "info";

export interface NotifyDetail {
  readonly type: NotifyType;
  readonly message: string;
}

export function notify(type: NotifyType, message: string): void {
  window.dispatchEvent(new CustomEvent<NotifyDetail>("app:notify", { detail: { type, message } }));
}

export const toast = {
  success: (message: string) => notify("success", message),
  error: (message: string) => notify("error", message),
  warning: (message: string) => notify("warning", message),
  info: (message: string) => notify("info", message),
} as const;
