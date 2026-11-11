export interface ApiResponse<T = unknown> {
    status: number;
    error: boolean;
    message: string | string[];
    data?: T;
    errors?: Record<string, string>;
    csrf_token?: string;
}

export interface ApiErrorResponse {
    status: number;
    error: true;
    message: string | string[];
    errors?: Record<string, string>;
    csrf_token?: string;
}
