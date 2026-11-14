// =============================================================================
// ApiError — normalisasi error dari axios / backend / jaringan.
// =============================================================================

import { AxiosError } from "axios";
import type { ApiResponse } from "@/types/types";

export type ApiErrorCode = "network" | "timeout" | "unauthorized" | "forbidden" | "not_found" | "validation" | "server" | "unknown";

export class ApiError extends Error {
  readonly code: ApiErrorCode;
  readonly httpStatus: number | null;
  readonly fieldErrors: Readonly<Record<string, string>>;

  constructor(params: { message: string; code: ApiErrorCode; httpStatus?: number | null; fieldErrors?: Record<string, string> }) {
    super(params.message);
    this.name = "ApiError";
    this.code = params.code;
    this.httpStatus = params.httpStatus ?? null;
    this.fieldErrors = params.fieldErrors ?? {};
  }
}

function messageOf(payload: ApiResponse<unknown> | undefined): string {
  if (!payload) return "";
  const raw = payload.message;
  const text = Array.isArray(raw) ? raw.join(" ") : (raw ?? "").trim();
  if (text) return text;
  const errors = payload.errors;
  if (errors) {
    const first = Object.values(errors)[0];
    if (first) return String(first);
  }
  return "";
}

function codeFromStatus(status: number | undefined): ApiErrorCode {
  if (!status) return "unknown";
  if (status === 401) return "unauthorized";
  if (status === 403) return "forbidden";
  if (status === 404) return "not_found";
  if (status === 422) return "validation";
  if (status >= 500) return "server";
  return "unknown";
}

export function toApiError(error: unknown, fallbackMessage: string): ApiError {
  if (error instanceof ApiError) return error;

  if (error instanceof AxiosError) {
    const response = error.response;

    if (!response) {
      const isTimeout = error.code === "ECONNABORTED" || error.code === "ETIMEDOUT";
      return new ApiError({
        message: isTimeout ? "Permintaan melebihi batas waktu." : "Tidak dapat terhubung ke server.",
        code: isTimeout ? "timeout" : "network",
      });
    }

    const data = response.data as ApiResponse<unknown> | undefined;
    return new ApiError({
      message: messageOf(data) || fallbackMessage,
      code: codeFromStatus(response.status),
      httpStatus: response.status,
      fieldErrors: data?.errors ?? {},
    });
  }

  if (error instanceof Error) {
    return new ApiError({ message: error.message || fallbackMessage, code: "unknown" });
  }

  return new ApiError({ message: fallbackMessage, code: "unknown" });
}
