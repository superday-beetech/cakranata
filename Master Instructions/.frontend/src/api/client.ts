import axios, {
    type AxiosError,
    type InternalAxiosRequestConfig,
} from "axios";

import { env } from "@/config/env";
import type { ApiResponse } from "../types/types";

const api = axios.create({
    baseURL: env.apiBaseURL,
    withCredentials: true,
    headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
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

api.interceptors.request.use(
    (config: InternalAxiosRequestConfig) => {
        const method = config.method?.toLowerCase();

        if (method && unsafeMethods.includes(method) && csrfToken) {
            config.headers.set("X-CSRF-TOKEN", csrfToken);
        }

        return config;
    }
);

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

        // Forbidden: tidak memiliki izin akses.
        if (error.response?.status === 403) {
            window.dispatchEvent(
                new CustomEvent("auth:forbidden")
            );
        }

        // Jangan otomatis memicu unauthorized di sini.
        // HTTP 401 juga dapat berasal dari kegagalan login.

        return Promise.reject(error);
    }
);

export default api;
