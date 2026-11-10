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

/**
 * Respons sukses yang pasti memiliki `data`.
 * Dipakai service yang sudah mengetahui bentuk datanya.
 */
export type ApiDataResponse<T = unknown> = ApiResponse<T> & {
    data: T;
};

/**
 * Envelope tulisan tangan (setJSON) pada beberapa controller lama,
 * misalnya Organization dan sebagian Navigation: `status` berupa teks,
 * bukan kode HTTP. Idealnya controller tersebut dipindah ke
 * ApiController::successResponse() lalu tipe ini dihapus.
 */
export interface LegacyApiResponse<T = unknown> {
    status: "success" | "error";
    error?: boolean;
    message: string;
    data: T;
    code?: string;
    details?: Record<string, unknown>;
    csrf_token?: string;
}
