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

const BASE = "/pharmacy/print-templates";

export interface CreatePrintTemplatePayload {
  name: string;
  type: string;
  paper_type: PaperConfig["type"];
  paper_config: PaperConfig;
  design_json: PrintTemplateDesign;
}

export type UpdatePrintTemplatePayload = Partial<CreatePrintTemplatePayload>;

export async function listPrintTemplates(): Promise<PrintTemplateSummary[]> {
  const res = await api.get<ApiEnvelope<PrintTemplateSummary[]>>(BASE);
  return res.data.data;
}
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
