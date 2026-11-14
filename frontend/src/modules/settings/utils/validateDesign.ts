import type { GlobalLogo, PaperConfig, PrintElement } from "../types/printTemplate";

export interface ValidationIssue {
  code: string;
  message: string;
  elementId?: string;
}

export interface ValidationResult {
  errors: ValidationIssue[];
  warnings: ValidationIssue[];
}

/** Batas ukuran JSON desain (byte, dihitung dari string UTF-8) */
export const MAX_DESIGN_JSON_BYTES = 500_000; // 500 KB

/** Batas ukuran base64 tunggal (logo/image) */
export const MAX_IMAGE_BASE64_BYTES = 100_000; // 100 KB

function base64SizeBytes(dataUrl: string): number {
  // data:image/png;base64,XXXX — hitung panjang base64 saja
  const idx = dataUrl.indexOf(",");
  const b64 = idx >= 0 ? dataUrl.slice(idx + 1) : dataUrl;
  return Math.ceil((b64.length * 3) / 4);
}

export function validateDesign(paper: PaperConfig, elements: PrintElement[], logo?: GlobalLogo): ValidationResult {
  const errors: ValidationIssue[] = [];
  const warnings: ValidationIssue[] = [];

  // --- 1. Elemen kosong (warning) ---
  const hasLogo = !!logo?.url;
  if (elements.length === 0 && !hasLogo) {
    warnings.push({
      code: "EMPTY_DESIGN",
      message: "Desain belum memiliki elemen apa pun.",
    });
  }

  // --- 2. Batas kertas ---
  const paperW = paper.orientation === "landscape" ? paper.height : paper.width;
  const paperH = paper.orientation === "landscape" ? paper.width : paper.height;

  for (const el of elements) {
    if (el.x < 0 || el.y < 0) {
      warnings.push({
        code: "OUT_OF_BOUNDS",
        message: `Elemen "${el.props.label || el.type}" berada di luar kertas (koordinat negatif).`,
        elementId: el.id,
      });
    }
    if (el.x + el.width > paperW + 0.5) {
      warnings.push({
        code: "OUT_OF_BOUNDS",
        message: `Elemen "${el.props.label || el.type}" melebihi lebar kertas (${(el.x + el.width).toFixed(1)} > ${paperW} mm).`,
        elementId: el.id,
      });
    }
    if (el.y + el.height > paperH + 0.5) {
      warnings.push({
        code: "OUT_OF_BOUNDS",
        message: `Elemen "${el.props.label || el.type}" melebihi tinggi kertas (${(el.y + el.height).toFixed(1)} > ${paperH} mm).`,
        elementId: el.id,
      });
    }

    // --- 3. Tabel tanpa kolom (error) ---
    if (el.type === "table") {
      const cols = el.props.columns ?? [];
      if (cols.length === 0) {
        errors.push({
          code: "TABLE_NO_COLUMNS",
          message: `Tabel "${el.props.label || "tanpa nama"}" tidak memiliki kolom.`,
          elementId: el.id,
        });
      }

      // Kolom dengan key duplikat
      const keys = cols.map((c) => c.key);
      const dupKeys = keys.filter((k, i) => keys.indexOf(k) !== i);
      if (dupKeys.length > 0) {
        errors.push({
          code: "TABLE_DUPLICATE_COLUMN_KEY",
          message: `Tabel "${el.props.label || "tanpa nama"}" memiliki kolom dengan key duplikat: ${[...new Set(dupKeys)].join(", ")}.`,
          elementId: el.id,
        });
      }

      // Baris kosong (warning, bukan error)
      if (cols.length > 0 && (el.props.rows ?? []).length === 0) {
        warnings.push({
          code: "TABLE_NO_ROWS",
          message: `Tabel "${el.props.label || "tanpa nama"}" belum punya baris data contoh.`,
          elementId: el.id,
        });
      }

      // Total lebar kolom > lebar elemen (warning)
      const totalColW = cols.reduce((sum, c) => sum + (c.width || 0), 0);
      if (totalColW > el.width + 0.5) {
        warnings.push({
          code: "TABLE_COLUMNS_OVERFLOW",
          message: `Total lebar kolom tabel "${el.props.label || "tanpa nama"}" (${totalColW} mm) melebihi lebar elemen (${el.width} mm).`,
          elementId: el.id,
        });
      }
    }

    // --- 4. Binding diaktifkan tapi kosong (warning) ---
    if (el.props.binding !== undefined && el.props.binding !== "" && el.props.binding.trim() === "") {
      warnings.push({
        code: "BINDING_EMPTY",
        message: `Elemen "${el.props.label || el.type}" mengaktifkan binding tapi path kosong.`,
        elementId: el.id,
      });
    }

    // --- 5. Ukuran image/logo base64 ---
    if (el.type === "image" || el.type === "logo") {
      const src = el.props.src as string | undefined;
      if (src && src.startsWith("data:") && base64SizeBytes(src) > MAX_IMAGE_BASE64_BYTES) {
        errors.push({
          code: "IMAGE_TOO_LARGE",
          message: `Gambar pada elemen "${el.props.label || el.type}" melebihi ${Math.round(MAX_IMAGE_BASE64_BYTES / 1000)} KB.`,
          elementId: el.id,
        });
      }
    }

    // --- 6. VisibleIf kosong tapi ada (info) ---
    const visibleIf = el.props.visibleIf as string | undefined;
    if (visibleIf !== undefined && visibleIf.trim() === "") {
      // abaikan (tidak mengganggu)
    }
  }

  // --- 7. Logo ---
  if (logo?.url) {
    if (logo.x < 0 || logo.y < 0) {
      warnings.push({
        code: "LOGO_OUT_OF_BOUNDS",
        message: "Logo berada di luar kertas (koordinat negatif).",
      });
    }
    if (logo.x + logo.width > paperW + 0.5) {
      warnings.push({
        code: "LOGO_OUT_OF_BOUNDS",
        message: `Logo melebihi lebar kertas (${(logo.x + logo.width).toFixed(1)} > ${paperW} mm).`,
      });
    }
    if (logo.y + logo.height > paperH + 0.5) {
      warnings.push({
        code: "LOGO_OUT_OF_BOUNDS",
        message: `Logo melebihi tinggi kertas (${(logo.y + logo.height).toFixed(1)} > ${paperH} mm).`,
      });
    }
    if (logo.url.startsWith("data:") && base64SizeBytes(logo.url) > MAX_IMAGE_BASE64_BYTES) {
      errors.push({
        code: "LOGO_TOO_LARGE",
        message: `Ukuran logo melebihi ${Math.round(MAX_IMAGE_BASE64_BYTES / 1000)} KB.`,
      });
    }
  }

  return { errors, warnings };
}

/** Cek ukuran JSON total (dipakai sebelum save) */
export function designJsonSizeBytes(obj: unknown): number {
  try {
    return new TextEncoder().encode(JSON.stringify(obj)).length;
  } catch {
    return 0;
  }
}
