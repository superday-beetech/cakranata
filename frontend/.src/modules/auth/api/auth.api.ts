import api from "@/api/client";

export interface LoginPayload {
    identity: string;
    password: string;
    remember?: boolean;
}

export interface AuthUser {
    id: number;
    username?: string;
    email?: string;
    [key: string]: unknown;
}

export interface AuthResponse {
    user?: AuthUser;
    [key: string]: unknown;
}

export interface ApiResponse<T = unknown> {
    status: number;
    error: boolean;
    message: string | string[];
    data?: T;
    errors?: Record<string, string>;
    csrf_token?: string;
}

export const authApi = {
    login(payload: LoginPayload) {
        return api.post<ApiResponse<AuthResponse>>(
            "/auth/login",
            payload
        );
    },

    logout() {
        return api.post<ApiResponse>("/auth/logout");
    },

    me() {
        return api.get<ApiResponse<AuthUser>>("/auth/me");
    },
};
