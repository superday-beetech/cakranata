/* src/utils/api.ts */
import axios from "axios";

import type { ApiResponse } from "@/types/types";

/* -------------------------------------------------------------------------- */
/* Message helpers                                                            */
/* -------------------------------------------------------------------------- */

/**
 * Normalisasi `message` dari kontrak CI4 (`string | string[]`) menjadi string.
 * Jika kosong/invalid, kembalikan pesan default.
 */
export function getMessage(message: unknown): string {
  if (Array.isArray(message)) {
    return message.length ? message.join(", ") : "Terjadi kesalahan.";
  }

  if (typeof message === "string" && message.trim()) {
    return message;
  }

  return "Terjadi kesalahan.";
}

/**
 * Ambil pesan error dari `unknown` (axios, Error, atau nilai lain).
 * Fallback ke pesan default bila tidak dikenali.
 */
export function getErrorMessage(error: unknown): string {
  if (axios.isAxiosError<ApiResponse>(error)) {
    return getMessage(error.response?.data?.message);
  }

  if (error instanceof Error) {
    return getMessage(error.message);
  }

  return "Terjadi kesalahan.";
}

/* -------------------------------------------------------------------------- */
/* Response helpers                                                           */
/* -------------------------------------------------------------------------- */

/**
 * Ambil `payload.data` tanpa melempar error.
 * - Jika `payload` null/undefined → null.
 * - Jika `payload` punya properti `data` → kembalikan `payload.data`.
 * - Jika `payload` adalah nilai mentah → kembalikan `payload` sebagai T.
 */
export function extractData<T = unknown>(payload: unknown): T | null {
  if (payload == null) return null;

  if (typeof payload === "object" && "data" in (payload as object)) {
    return (payload as { data: T }).data;
  }

  return payload as T;
}

/**
 * Ambil `response.data.data` dari AxiosResponse<ApiResponse<T>>.
 * Melempar Error jika `error: true` atau response tidak valid.
 * Mengembalikan T (bukan T | undefined) karena akan throw di atas.
 */
export function getPayload<T>(response: unknown): T {
  const result = (response as { data?: ApiResponse<T> })?.data;

  if (!result || result.error) {
    const message = result?.message;
    throw new Error(Array.isArray(message) ? message.join(", ") : (message ?? "Request API gagal."));
  }

  return result.data as T;
}

/* -------------------------------------------------------------------------- */
/* Misc                                                                       */
/* -------------------------------------------------------------------------- */

/**
 * Konversi nilai dari backend (`true`, `1`, `"1"`) menjadi boolean.
 * Digunakan untuk field yang dikirim sebagai string/number oleh API.
 */
export function toBoolean(value: unknown): boolean {
  return value === true || value === 1 || value === "1";
}
