import { env } from "@/config/env";
import axios from "axios";
import type { AxiosInstance, AxiosResponse } from "axios";

const api: AxiosInstance = axios.create({
     baseURL: env.apiBaseURL || "api/v1",
  withCredentials: true,
  headers: {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  },
});

// Ambil CSRF token dari meta tag halaman.
function getCsrfToken(): string | null {
  const meta = document.querySelector<HTMLMetaElement>(
    'meta[name="csrf-token"]',
  );

  return meta?.content || null;
}

// Pasang CSRF token pada request yang dikirim.
api.interceptors.request.use((config) => {
  const csrfToken = getCsrfToken();

  if (csrfToken) {
    config.headers.set("X-CSRF-TOKEN", csrfToken);
  }

  return config;
});

// Perbarui CSRF token jika backend mengirim token baru.
api.interceptors.response.use(
  (response: AxiosResponse) => {
    const csrfToken = response.data?.csrf_token;

    if (typeof csrfToken === "string" && csrfToken) {
      const meta = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
      );

      if (meta) {
        meta.content = csrfToken;
      }
    }

    return response;
  },
);

export default api;
