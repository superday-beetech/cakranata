import api from "@/api/client";
import type { PrintTemplate, PrintTemplateSummary, PaperConfig, PrintTemplateDesign } from "../types/printTemplate";

interface ApiEnvelope<T> {
  data: T;
  csrf_token?: string;
}
interface ApiErrorEnvelope {
  error?: number;
  messages?: Record<string, string> | string;
}

export interface PaginatedResult<T> {
  data: T[];
  total: number;
  page: number;
  per_page: number;
}

const BASE = "/pharmacy/print-templates";

export interface CreatePrintTemplatePayload {
  name: string;
  type: string;
  paper_type: PaperConfig["type"];
  paper_config: PaperConfig;
  design_json: PrintTemplateDesign;
}

export type UpdatePrintTemplatePayload = Partial<CreatePrintTemplatePayload>;

// export async function listPrintTemplates(): Promise<PrintTemplateSummary[]> {
//   const res = await api.get<ApiEnvelope<PrintTemplateSummary[]>>(BASE);
//   return res.data.data;
// }
export async function getPrintTemplate(id: number): Promise<PrintTemplate> {
  const res = await api.get<ApiEnvelope<PrintTemplate>>(`${BASE}/${id}`);
  return res.data.data;
}
export async function createPrintTemplate(p: CreatePrintTemplatePayload): Promise<PrintTemplate> {
  const res = await api.post<ApiEnvelope<PrintTemplate>>(BASE, p);
  return res.data.data;
}
export async function updatePrintTemplate(id: number, p: UpdatePrintTemplatePayload): Promise<PrintTemplate> {
  const res = await api.put<ApiEnvelope<PrintTemplate>>(`${BASE}/${id}`, p);
  return res.data.data;
}
export async function deletePrintTemplate(id: number): Promise<void> {
  await api.delete(`${BASE}/${id}`);
}

export async function listPrintTemplates(
  params: {
    page?: number;
    per_page?: number;
    search?: string;
    type?: string;
  } = {},
): Promise<PaginatedResult<PrintTemplateSummary>> {
  const q = new URLSearchParams();
  if (params.page) q.set("page", String(params.page));
  if (params.per_page) q.set("per_page", String(params.per_page));
  if (params.search) q.set("search", params.search);
  if (params.type) q.set("type", params.type);
  const res = await api.get(`${BASE}?${q.toString()}`);
  return res.data;
}

export async function duplicatePrintTemplate(id: number): Promise<PrintTemplate> {
  const src = await getPrintTemplate(id);
  const payload = {
    name: `${src.name} (Copy)`,
    type: src.type,
    paper_type: src.paper_type,
    paper_config: src.paper_config,
    design_json: src.design_json,
  };
  return await createPrintTemplate(payload);
}
export function extractErrorMessage(err: unknown): string {
  const e = err as { response?: { data?: ApiErrorEnvelope } };
  const m = e.response?.data?.messages;
  if (typeof m === "string") return m;
  if (m && typeof m === "object") {
    const first = Object.values(m)[0];
    if (typeof first === "string") return first;
  }
  return "Terjadi kesalahan saat menghubungi server.";
}
