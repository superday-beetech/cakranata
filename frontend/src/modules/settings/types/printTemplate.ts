export type PaperType = "a4" | "a5" | "80mm" | "custom";
export type Orientation = "portrait" | "landscape";
export type PrintElementType = "text" | "line" | "rect" | "table" | "image" | "barcode" | "qr";

export interface PaperConfig {
  type: PaperType;
  width: number;
  height: number;
  unit: "mm";
  orientation: Orientation;
}

export interface TableColumn {
  key: string;
  label: string;
  width: number;
  align?: "left" | "center" | "right";
}

export interface PrintElement {
  id: string;
  type: PrintElementType;
  x: number;
  y: number;
  width: number;
  height: number;
  props: {
    label?: string;
    fontSize?: number;
    color?: string;
    align?: "left" | "center" | "right";
    binding?: string;
    columns?: TableColumn[];
    rows?: Record<string, string>[];
    [key: string]: unknown;
  };
}

export interface PrintTemplateDesign {
  elements: PrintElement[];
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
