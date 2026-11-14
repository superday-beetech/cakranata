export type PaperType = "a4" | "a5" | "80mm" | "custom";
export type Orientation = "portrait" | "landscape";
export type PrintElementType = "text" | "line" | "rect" | "table" | "image" | "logo" | "barcode" | "qr";

export interface PaperConfig {
  type: PaperType;
  width: number; // mm
  height: number; // mm
  unit: "mm";
  orientation: Orientation;
}

export interface TableColumn {
  key: string;
  label: string;
  width: number; // mm
  align?: "left" | "center" | "right";
}

export interface PrintElement {
  id: string;
  type: PrintElementType;
  x: number; // mm
  y: number; // mm
  width: number; // mm
  height: number; // mm
  props: {
    label?: string;
    fontSize?: number; // pt
    color?: string;
    align?: "left" | "center" | "right";
    binding?: string;
    columns?: TableColumn[];
    rows?: Record<string, string>[];
    locked?: boolean;
    visibleIf?: string;
    src?: string;
    barcodeFormat?: string;
    [key: string]: unknown;
  };
}

/** Logo global — sekarang pakai x/y absolut agar bisa di-drag */
export interface GlobalLogo {
  url: string;
  x: number; // mm
  y: number; // mm
  width: number; // mm
  height: number; // mm
}

export interface PrintTemplateDesign {
  elements: PrintElement[];
  logo?: GlobalLogo;
}

export interface PrintTemplateSummary {
  id: number;
  name: string;
  type: string;
  paper_type: PaperType;
  created_by: number | null;
  created_at: string;
  updated_at: string;
}

export interface PrintTemplate extends PrintTemplateSummary {
  paper_config: PaperConfig;
  design_json: PrintTemplateDesign;
}

export interface StandardTemplatePreset {
  id: string;
  name: string;
  description: string;
  icon: string;
  type: string;
  paper: PaperConfig;
  elements: PrintElement[];
  logo?: GlobalLogo;
}
