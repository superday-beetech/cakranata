import { useNotificationStore } from "@/stores/notification";
import { env } from "@/config/env";
import { useAuthStore } from "@/stores/auth";
import { usePermissionStore } from "@/stores/permission";
import { useOrganizationContextStore } from "@/stores/organization";

interface NetworkErrorDetail {
  code?: string;
  message?: string;
}

interface ResourceErrorDetail {
  url?: string;
  resource?: string;
}

let installed = false;

const recentErrors = new Map<string, number>();

function shouldNotify(key: string, interval = 10000): boolean {
  const now = Date.now();
  const last = recentErrors.get(key) ?? 0;

  if (now - last < interval) {
    return false;
  }

  recentErrors.set(key, now);
  return true;
}

export function installGlobalErrorHandler(): void {
  if (installed) return;

  installed = true;

  const notification = useNotificationStore();

  // API / Network Error
  window.addEventListener("app:network-error", (event) => {
    const detail = (event as CustomEvent<NetworkErrorDetail>).detail;

    const key = "network-error";

    if (!shouldNotify(key)) return;

    const message = detail?.code === "ECONNABORTED" ? "Permintaan membutuhkan waktu terlalu lama. Silakan coba kembali." : "Tidak dapat terhubung ke server. Periksa koneksi Anda dan coba kembali.";

    notification.error(message, {
      title: "Koneksi Bermasalah",
      autoClose: false,
      closable: true,
    });
  });

  // Resource Error (JS / CSS)
  window.addEventListener("app:resource-error", (event) => {
    const detail = (event as CustomEvent<ResourceErrorDetail>).detail;

    const resource = detail?.resource ?? "aplikasi";

    if (!shouldNotify(`resource-${resource}`)) return;

    notification.warning(`Sebagian komponen ${resource} gagal dimuat. Silakan muat ulang halaman.`, {
      title: "Komponen Gagal Dimuat",
      autoClose: false,
      closable: true,
    });
  });

  // Browser Offline
  window.addEventListener("offline", () => {
    notification.warning("Perangkat Anda sedang offline. Beberapa fitur mungkin tidak tersedia.", {
      title: "Koneksi Internet Terputus",
      autoClose: false,
      closable: true,
    });
  });

  // Browser Online
  window.addEventListener("online", () => {
    recentErrors.delete("network-error");

    notification.success("Koneksi internet kembali tersedia.", {
      title: "Koneksi Pulih",
      duration: 3000,
    });
  });
  window.addEventListener("app:server-error", (event) => {
    const detail = (event as CustomEvent<{ status?: number }>).detail;

    const status = detail?.status ?? 500;

    if (!shouldNotify(`server-${status}`)) return;

    notification.error("Server sedang mengalami gangguan. Silakan coba beberapa saat lagi.", {
      title: "Layanan Tidak Tersedia",
      autoClose: false,
      closable: true,
    });
  });

  // ============ AUTH: 401 — Sesi berakhir ============
  // Hanya satu redirect per siklus; guard mencegah loop di halaman login.
  let handlingUnauthenticated = false;

  window.addEventListener("auth:unauthenticated", () => {
    if (handlingUnauthenticated) return;

    // Jangan redirect jika sudah di halaman login
    // (mis. login gagal → backend bisa mengembalikan 401).
    if (window.location.pathname.includes("/login")) return;

    handlingUnauthenticated = true;

    // Bersihkan state supaya tidak "stuck login palsu".
    try {
      useAuthStore().resetAuth();
      usePermissionStore().$reset();
      useOrganizationContextStore().$reset();
    } catch {
      // abaikan; reset bersifat best-effort
    }

    notification.warning("Sesi Anda telah berakhir. Silakan login kembali.", {
      title: "Sesi Berakhir",
      duration: 4000,
      closable: true,
    });

    // Hard redirect: state SPA dianggap tidak lagi valid setelah 401.
    // Pakai setTimeout agar notifikasi sempat tampil sebentar.
    window.setTimeout(() => {
      window.location.assign(`${env.appBaseURL}login`);
    }, 800);
  });

  // ============ AUTH: 403 — Akses ditolak ============
  window.addEventListener("auth:forbidden", () => {
    if (!shouldNotify("auth-forbidden", 5000)) return;

    notification.warning("Anda tidak memiliki akses ke sumber daya ini.", {
      title: "Akses Ditolak",
      duration: 4000,
      closable: true,
    });
  });
}
