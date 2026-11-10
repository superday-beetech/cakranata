/**
 * ACN ERP — Global API Client
 *
 * Default  : Shield Session + CSRF
 * Optional : Bearer authentication per request
 *
 * Response contract:
 * { ok: boolean, status: number, data: any }
 * Superday : ADICAKRANATA
 */
(() => {
  "use strict";

  const SAFE_METHODS = new Set(["GET", "HEAD", "OPTIONS"]);
  const DEFAULT_TIMEOUT = 30000;

  // Konfigurasi global dapat disiapkan sebelum api.js dimuat.
  const config = (window.ACN_API_CONFIG ??= {});

  config.bearer ??= {};
  config.bearer.enabled ??= false;
  config.bearer.getToken ??= () => null;

  config.timeout ??= DEFAULT_TIMEOUT;
  config.allowedOrigins ??= [];

  /**
   * Mendapatkan URL dan membatasi request ke origin yang diizinkan.
   */
  function resolveUrl(input) {
    const url = new URL(input, window.location.origin);

    const allowedOrigins = [window.location.origin, ...config.allowedOrigins];

    if (!allowedOrigins.includes(url.origin)) {
      throw new Error(`API request diblokir: origin tidak diizinkan (${url.origin}).`);
    }

    if (!["http:", "https:"].includes(url.protocol)) {
      throw new Error("Protokol URL API tidak diizinkan.");
    }

    return url;
  }

  /**
   * Membaca token CSRF terbaru dari meta tag.
   */
  function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";
  }

  /**
   * Memperbarui token CSRF setelah menerima respons server.
   */
  function updateCsrfToken(data) {
    if (!data || typeof data.csrf_token !== "string") {
      return;
    }

    const meta = document.querySelector('meta[name="csrf-token"]');

    if (meta) {
      meta.setAttribute("content", data.csrf_token);
    }

    const input = document.getElementById("csrf-input");

    if (input) {
      input.value = data.csrf_token;
    }
  }

  /**
   * Mengambil pesan error yang aman untuk ditampilkan.
   */
  function getErrorMessage(data, status) {
    const message = data?.messages?.error ?? data?.message ?? data?.error;

    if (typeof message === "string") {
      return message;
    }

    return `Permintaan API gagal (HTTP ${status}).`;
  }
  /**
   * Global API request.
   *
   *
   * options.auth:
   *   "session" — autentikasi berbasis sesi browser
   *   "cookie"  — autentikasi berbasis cookie HttpOnly
   *   "bearer"  — autentikasi menggunakan Bearer token
   */
  window.apiFetch = async (input, options = {}) => {
    const { auth = "session", timeout = config.timeout, headers: suppliedHeaders = {}, body: suppliedBody, signal: suppliedSignal, ...fetchOptions } = options;

    const headers = new Headers(suppliedHeaders);
    // PASANG DI SINI
    headers.set("X-ACN-Auth-Mode", auth);
    const url = resolveUrl(input);
    const method = String(fetchOptions.method ?? "GET").toUpperCase();

    if (!["GET", "HEAD", "OPTIONS", "POST", "PUT", "PATCH", "DELETE"].includes(method)) {
      throw new Error(`HTTP method tidak diizinkan: ${method}`);
    }

    if (!["session", "cookie", "bearer"].includes(auth)) {
      throw new Error(`Mode autentikasi tidak valid: ${auth}`);
    }

    // Request AJAX.
    headers.set("X-Requested-With", "XMLHttpRequest");

    // Jangan mengirim cookie session ke origin lain.
    const credentials = "same-origin";
    // const credentials = auth === "bearer" ? "omit" : "include";

    // Bearer hanya dikirim jika diminta secara eksplisit.
    if (auth === "bearer") {
      if (!config.bearer.enabled) {
        throw new Error("Autentikasi Bearer belum diaktifkan pada konfigurasi.");
      }

      const token = await config.bearer.getToken();

      if (typeof token !== "string" || !token.trim()) {
        throw new Error("Bearer token tidak tersedia.");
      }

      headers.set("Authorization", `Bearer ${token.trim()}`);
    } else {
      // Jangan sampai Authorization lama terkirim tanpa sengaja.
      headers.delete("Authorization");
    }

    let body = suppliedBody;

    if (!SAFE_METHODS.has(method)) {
      const csrfToken = getCsrfToken();

      if (!csrfToken) {
        throw new Error("CSRF token not found. Reload the page and try again [Line:141:Api.js].");
      }

      headers.set("X-CSRF-TOKEN", csrfToken);

      if (body !== undefined && body !== null && typeof body === "object" && !(body instanceof FormData) && !(body instanceof Blob) && !(body instanceof URLSearchParams) && !(body instanceof ArrayBuffer)) {
        body = JSON.stringify(body);

        if (!headers.has("Content-Type")) {
          headers.set("Content-Type", "application/json");
        }
      }
    }

    if (method === "GET" || method === "HEAD") {
      body = undefined;
    }

    const controller = new AbortController();
    let timeoutId;

    const abortFromCaller = () => controller.abort();

    if (suppliedSignal) {
      if (suppliedSignal.aborted) {
        controller.abort();
      } else {
        suppliedSignal.addEventListener("abort", abortFromCaller, { once: true });
      }
    }

    if (Number.isFinite(timeout) && timeout > 0) {
      timeoutId = setTimeout(() => controller.abort(), timeout);
    }

    try {
      const response = await fetch(url.href, {
        ...fetchOptions,
        method,
        headers,
        body,
        credentials,
        redirect: "error",
        signal: controller.signal,
      });

      const text = await response.text();
      let data = null;

      if (text.trim() !== "") {
        try {
          data = JSON.parse(text);
        } catch {
          // Jangan mencatat body HTML atau data sensitif ke console.
          throw new Error(`HTTP ${response.status}: server response is not valid JSON [Line:195:Api.js].`);
        }
      }

      updateCsrfToken(data);

      return {
        ok: response.ok,
        status: response.status,
        data,
      };
    } catch (error) {
      if (error.name === "AbortError") {
        throw new Error(suppliedSignal?.aborted ? "API request cancelled[Line:208:Api.js]." : "API request timed out.");
      }

      // Tidak mencatat URL lengkap, token, atau body request.
      console.error("[ACN API]", error.message);

      throw error;
    } finally {
      if (timeoutId) {
        clearTimeout(timeoutId);
      }

      suppliedSignal?.removeEventListener("abort", abortFromCaller);
    }
  };
})();
