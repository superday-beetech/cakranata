/**
 * Helper format tunggal untuk seluruh modul POS.
 * Letakkan di `src/utils/format.ts` (sesuaikan path import bila struktur proyek berbeda).
 */

const rupiah = new Intl.NumberFormat("id-ID", {
  style: "currency",
  currency: "IDR",
  maximumFractionDigits: 0,
});

const plainNumber = new Intl.NumberFormat("id-ID");

const shortDateTime = new Intl.DateTimeFormat("id-ID", {
  dateStyle: "short",
  timeStyle: "short",
});

/** Ubah nilai apa pun menjadi angka hingga; NaN/Infinity/null menjadi 0. */
export function toNumber(value: unknown): number {
  const number = Number(value);
  return Number.isFinite(number) ? number : 0;
}

/** 1500000 -> "Rp 1.500.000" */
export function formatCurrency(value: number | string | null | undefined): string {
  return rupiah.format(toNumber(value));
}

/** 1500000 -> "1.500.000" */
export function formatNumber(value: number | string | null | undefined): string {
  return plainNumber.format(toNumber(value));
}

/** Tanggal-waktu singkat; nilai kosong menjadi "-", nilai tak valid dikembalikan apa adanya. */
export function formatDateTime(value?: string | null): string {
  if (!value) return "-";

  const date = new Date(value);

  return Number.isNaN(date.getTime()) ? value : shortDateTime.format(date);
}

/** Tanggal lokal hari ini dalam format YYYY-MM-DD (bukan UTC, agar tidak mundur sehari di WIB). */
export function getToday(): string {
  const date = new Date();

  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");

  return `${year}-${month}-${day}`;
}
