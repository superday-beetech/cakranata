import axios, { type AxiosError, type InternalAxiosRequestConfig } from "axios";

import { env } from "@/config/env";
import type { ApiResponse } from "@/types/types";

const api = axios.create({
  baseURL: env.apiBaseURL,
  withCredentials: true,
  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
    "X-Requested-With": "XMLHttpRequest",
    "X-ACN-Auth-Mode": "session",
  },
});

/*
|--------------------------------------------------------------------------
| CSRF State
|--------------------------------------------------------------------------
*/

let csrfToken: string | null = null;

export function setCsrfToken(token: string | null): void {
  csrfToken = token;
}

export function getCsrfToken(): string | null {
  return csrfToken;
}

/*
|--------------------------------------------------------------------------
| HTTP Methods
|--------------------------------------------------------------------------
*/

const unsafeMethods = ["post", "put", "patch", "delete"];

/*
|--------------------------------------------------------------------------
| Request Interceptor
|--------------------------------------------------------------------------
*/

api.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const method = config.method?.toLowerCase();

  if (method && unsafeMethods.includes(method) && csrfToken) {
    config.headers.set("X-CSRF-TOKEN", csrfToken);
  }

  return config;
});

/*
|--------------------------------------------------------------------------
| Response Interceptor
|--------------------------------------------------------------------------
*/

api.interceptors.response.use(
  (response) => {
    const data = response.data as ApiResponse;

    if (data?.csrf_token) {
      setCsrfToken(data.csrf_token);
    }

    return response;
  },
  (error: AxiosError<ApiResponse>) => {
    const data = error.response?.data;

    if (data?.csrf_token) {
      setCsrfToken(data.csrf_token);
    }

    // Network Error / Timeout
    if (!error.response) {
      window.dispatchEvent(
        new CustomEvent("app:network-error", {
          detail: {
            code: error.code,
            message: error.message,
          },
        }),
      );
    }

    // Forbidden
    if (error.response?.status === 403) {
      window.dispatchEvent(new CustomEvent("auth:forbidden"));
    }

    // Server Error
    if (error.response && error.response.status >= 500) {
      window.dispatchEvent(
        new CustomEvent("app:server-error", {
          detail: {
            status: error.response.status,
          },
        }),
      );
    }

    return Promise.reject(error);
  },
);

export default api;
