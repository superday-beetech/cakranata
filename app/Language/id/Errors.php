<?php

return [
    // 400 Bad Request
    'BAD_REQUEST' => 'Permintaan tidak valid atau format data salah.',

    // 401 Unauthorized
    'AUTHENTICATION_REQUIRED' => 'Silakan login terlebih dahulu untuk mengakses sumber daya ini.',
    'INVALID_TOKEN' => 'Token akses tidak valid atau sudah kedaluwarsa.',

    // 403 Forbidden
    'ACCESS_DENIED' => 'Anda tidak memiliki izin untuk mengakses halaman atau sumber daya ini.',
    'CSRF_TOKEN_MISMATCH' => 'Sesi telah kedaluwarsa atau token CSRF tidak valid. Silakan muat ulang halaman.',
    'ACCOUNT_SUSPENDED' => 'Akun Anda telah ditangguhkan. Hubungi administrator.',

    // 404 Not Found
    'NOT_FOUND' => 'Data atau halaman yang Anda cari tidak ditemukan.',
    'ENDPOINT_NOT_FOUND' => 'Endpoint API yang diminta tidak tersedia.',

    // 405 Method Not Allowed
    'HTTP_VERB_NOT_ALLOWED' => 'Metode HTTP yang (%s) digunakan tidak diizinkan untuk endpoint ini.',

    // 422 Unprocessable Entity
    'VALIDATION_ERROR' => 'Terdapat kesalahan validasi pada data yang Anda kirimkan.',

    // 429 Too Many Requests
    'TOO_MANY_REQUESTS' => 'Terlalu banyak permintaan. Silakan coba beberapa saat lagi.',

    // 500 Internal Server Error
    'INTERNAL_SERVER_ERROR' => 'Terjadi kesalahan pada server. Silakan coba beberapa saat lagi.',
];
