// src/utils/url.ts

/**
 * Mendapatkan protokol saat ini (http / https)
 */
export function getProtocol(): "http" | "https" {
  return window.location.protocol === "https:" ? "https" : "http";
}

/**
 * Mendapatkan hostname (domain + port jika ada)
 * Contoh: "namaprojek.test" atau "namaprojek.test:8080"
 */
export function getHost(): string {
  return window.location.host;
}

/**
 * Mendapatkan origin lengkap
 * Contoh: "https://namaprojek.test"
 */
export function getOrigin(): string {
  return window.location.origin;
}

/**
 * Base URL dinamis (origin + trailing slash)
 * Contoh: "https://namaprojek.test/"
 */
export function getBaseUrl(): string {
  return `${window.location.origin}/`;
}

/**
 * Base URL API (sesuaikan path API CI4 Anda)
 * Contoh: "https://namaprojek.test/api/"
 */
export function getApiBaseUrl(): string {
  return `${window.location.origin}/api/v1`;
}

/**
 * Cek apakah sedang di HTTPS
 */
export function isHttps(): boolean {
  return window.location.protocol === "https:";
}
