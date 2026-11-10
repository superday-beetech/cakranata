/**
 * ApiController dapat mengirim `message` sebagai teks tunggal atau
 * daftar pesan (mis. error validasi). Fungsi ini selalu mengembalikan string.
 */
export function apiMessage(
    message: string | string[] | null | undefined,
    fallback = "Terjadi kesalahan.",
): string {
    if (Array.isArray(message)) {
        const text = message.filter(Boolean).join(", ");
        return text || fallback;
    }

    return message || fallback;
}
