// src/utils/uuid.ts

/**
 * UUID v4 generator yang aman di HTTP non-localhost.
 * - Pakai crypto.randomUUID jika tersedia
 * - Fallback ke crypto.getRandomValues
 * - Fallback terakhir ke Math.random (sangat jarang)
 */
export function uuidv4(): string {
  // 1. Native (butuh secure context)
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    return crypto.randomUUID();
  }

  // 2. getRandomValues (tersedia di semua context, termasuk HTTP)
  if (typeof crypto !== "undefined" && typeof crypto.getRandomValues === "function") {
    const bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);

    // Set version 4 & variant bits
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;

    const hex = [...bytes].map((b) => b.toString(16).padStart(2, "0")).join("");

    return hex.slice(0, 8) + "-" + hex.slice(8, 12) + "-" + hex.slice(12, 16) + "-" + hex.slice(16, 20) + "-" + hex.slice(20);
  }

  // 3. Fallback terakhir (tidak kriptografis, hanya untuk ID lokal)
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === "x" ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}
