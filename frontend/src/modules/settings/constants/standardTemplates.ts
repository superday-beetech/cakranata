import { uuidv4 } from "@/utils/uuid";
import type { GlobalLogo, PrintElement, StandardTemplatePreset, TableColumn } from "../types/printTemplate";

type Align = "left" | "center" | "right";

function defaultLogo(): GlobalLogo {
  return { url: "", x: 10, y: 8, width: 20, height: 20 };
}

function txt(
  x: number,
  y: number,
  w: number,
  h: number,
  label: string,
  opts: Partial<{
    fontSize: number;
    align: Align;
    color: string;
    binding: string;
  }> = {},
): PrintElement {
  return {
    id: uuidv4(),
    type: "text",
    x,
    y,
    width: w,
    height: h,
    props: { label, fontSize: 11, color: "#000", align: "left" as Align, ...opts },
  };
}

function line(x: number, y: number, w: number, color = "#333"): PrintElement {
  return { id: uuidv4(), type: "line", x, y, width: w, height: 0.3, props: { color } };
}

function rect(x: number, y: number, w: number, h: number): PrintElement {
  return { id: uuidv4(), type: "rect", x, y, width: w, height: h, props: { color: "#333" } };
}

function table(x: number, y: number, w: number, h: number, binding?: string): PrintElement {
  const columns: TableColumn[] = [
    { key: "no", label: "No", width: 12, align: "center" },
    { key: "name", label: "Nama Barang", width: 70, align: "left" },
    { key: "qty", label: "Qty", width: 18, align: "center" },
    { key: "price", label: "Harga", width: 30, align: "right" },
    { key: "subtotal", label: "Subtotal", width: 30, align: "right" },
  ];
  return {
    id: uuidv4(),
    type: "table",
    x,
    y,
    width: w,
    height: h,
    props: {
      fontSize: 10,
      color: "#000",
      align: "left",
      binding,
      columns,
      rows: [
        { no: "1", name: "Paracetamol 500mg", qty: "10", price: "Rp 500", subtotal: "Rp 5.000" },
        { no: "2", name: "Amoxicillin 250mg", qty: "5", price: "Rp 1.200", subtotal: "Rp 6.000" },
      ],
    },
  };
}

function qr(x: number, y: number, size: number, binding?: string): PrintElement {
  return {
    id: uuidv4(),
    type: "qr",
    x,
    y,
    width: size,
    height: size,
    props: { binding: binding ?? "invoice.number", label: "" },
  };
}

export const standardTemplates: StandardTemplatePreset[] = [
  /* ---------- 1. Kwitansi A5 ---------- */
  {
    id: "kwitansi-a5",
    name: "Kwitansi A5",
    description: "Kwitansi penjualan dengan header, info pasien, tabel item, total, dan QR.",
    icon: "ti ti-receipt",
    type: "kwitansi",
    paper: { type: "a5", width: 148, height: 210, unit: "mm", orientation: "portrait" },
    logo: defaultLogo(),
    elements: [
      txt(35, 8, 103, 8, "APOTEK SEHAT", { fontSize: 18, align: "center" }),
      txt(35, 15, 103, 5, "Jl. Kesehatan No. 123, Jakarta — Telp. 021-1234567", { fontSize: 9, align: "center", color: "#666" }),
      line(10, 22, 128),
      txt(10, 25, 60, 5, "No. Kwitansi", { fontSize: 10 }),
      txt(70, 25, 68, 5, "", { fontSize: 10, binding: "invoice.number" }),
      txt(10, 30, 60, 5, "Tanggal", { fontSize: 10 }),
      txt(70, 30, 68, 5, "", { fontSize: 10, binding: "invoice.date" }),
      txt(10, 36, 60, 5, "Nama Pasien", { fontSize: 10 }),
      txt(70, 36, 68, 5, "", { fontSize: 10, binding: "patient.name" }),
      txt(10, 41, 60, 5, "Alamat", { fontSize: 10 }),
      txt(70, 41, 68, 8, "", { fontSize: 10, binding: "patient.address" }),
      table(10, 52, 128, 60, "items"),
      line(10, 120, 128),
      txt(10, 124, 60, 5, "Total", { fontSize: 12 }),
      txt(70, 124, 68, 5, "", { fontSize: 12, align: "right", binding: "invoice.total" }),
      txt(10, 133, 60, 5, "Metode Bayar", { fontSize: 10 }),
      txt(70, 133, 68, 5, "", { fontSize: 10, binding: "invoice.payment_method" }),
      txt(10, 150, 128, 5, "Terima kasih atas kunjungan Anda", { fontSize: 10, align: "center", color: "#666" }),
      qr(118, 160, 22, "invoice.number"),
      txt(10, 190, 60, 5, "Petugas", { fontSize: 9 }),
      txt(10, 195, 60, 5, "", { fontSize: 9, binding: "cashier.name" }),
    ],
  },

  /* ---------- 2. Struk 80mm ---------- */
  {
    id: "struk-80mm",
    name: "Struk 80mm",
    description: "Struk kasir thermal 80mm dengan header, tabel, total, dan footer.",
    icon: "ti ti-printer",
    type: "struk",
    paper: { type: "80mm", width: 80, height: 150, unit: "mm", orientation: "portrait" },
    logo: { url: "", x: 30, y: 3, width: 20, height: 12 },
    elements: [
      txt(5, 16, 70, 5, "APOTEK SEHAT", { fontSize: 12, align: "center" }),
      txt(5, 21, 70, 4, "Jl. Kesehatan No. 123", { fontSize: 8, align: "center", color: "#666" }),
      txt(5, 25, 70, 4, "Telp. 021-1234567", { fontSize: 8, align: "center", color: "#666" }),
      line(5, 30, 70),
      txt(5, 32, 35, 4, "", { fontSize: 8, binding: "invoice.number" }),
      txt(40, 32, 35, 4, "", { fontSize: 8, align: "right", binding: "invoice.date" }),
      line(5, 37, 70),
      txt(5, 39, 70, 4, "", { fontSize: 8, binding: "patient.name" }),
      line(5, 44, 70),
      table(5, 46, 70, 55, "items"),
      line(5, 103, 70),
      txt(5, 105, 35, 4, "TOTAL", { fontSize: 9 }),
      txt(40, 105, 35, 4, "", { fontSize: 9, align: "right", binding: "invoice.total" }),
      txt(5, 110, 35, 4, "BAYAR", { fontSize: 9 }),
      txt(40, 110, 35, 4, "", { fontSize: 9, align: "right", binding: "invoice.paid" }),
      txt(5, 115, 35, 4, "KEMBALI", { fontSize: 9 }),
      txt(40, 115, 35, 4, "", { fontSize: 9, align: "right", binding: "invoice.change" }),
      line(5, 121, 70),
      txt(5, 123, 70, 4, "Terima kasih", { fontSize: 9, align: "center" }),
      qr(30, 128, 20, "invoice.number"),
    ],
  },

  /* ---------- 3. Penerimaan Barang A4 ---------- */
  {
    id: "penerimaan-barang-a4",
    name: "Penerimaan Barang A4",
    description: "Form penerimaan barang dari supplier dengan tabel detail dan tanda tangan.",
    icon: "ti ti-truck-delivery",
    type: "penerimaan_barang",
    paper: { type: "a4", width: 210, height: 297, unit: "mm", orientation: "portrait" },
    logo: defaultLogo(),
    elements: [
      txt(35, 12, 160, 10, "FORM PENERIMAAN BARANG", { fontSize: 18, align: "center" }),
      line(15, 25, 180),
      txt(15, 30, 60, 6, "No. Penerimaan", { fontSize: 11 }),
      txt(75, 30, 60, 6, "", { fontSize: 11, binding: "receipt.number" }),
      txt(140, 30, 55, 6, "Tanggal", { fontSize: 11 }),
      txt(15, 38, 60, 6, "Supplier", { fontSize: 11 }),
      txt(75, 38, 120, 6, "", { fontSize: 11, binding: "supplier.name" }),
      txt(15, 46, 60, 6, "No. PO", { fontSize: 11 }),
      txt(75, 46, 120, 6, "", { fontSize: 11, binding: "purchase_order.number" }),
      table(15, 58, 180, 140, "items"),
      rect(15, 205, 55, 25),
      rect(80, 205, 55, 25),
      rect(145, 205, 50, 25),
      txt(15, 233, 55, 5, "Petugas Penerima", { fontSize: 10, align: "center" }),
      txt(80, 233, 55, 5, "Kepala Gudang", { fontSize: 10, align: "center" }),
      txt(145, 233, 50, 5, "Supplier", { fontSize: 10, align: "center" }),
      txt(15, 260, 180, 10, "Catatan: Barang telah diperiksa sesuai dengan PO.", { fontSize: 9, color: "#666" }),
    ],
  },

  /* ---------- 4. Kwitansi A4 ---------- */
  {
    id: "kwitansi-a4",
    name: "Kwitansi A4",
    description: "Kwitansi ukuran A4 dengan kop surat lengkap dan kolom tanda tangan.",
    icon: "ti ti-file-invoice",
    type: "kwitansi",
    paper: { type: "a4", width: 210, height: 297, unit: "mm", orientation: "portrait" },
    logo: { url: "", x: 15, y: 15, width: 25, height: 25 },
    elements: [
      txt(45, 15, 150, 12, "APOTEK SEHAT", { fontSize: 22, align: "center" }),
      txt(45, 28, 150, 6, "Jl. Kesehatan No. 123, Jakarta Pusat 10110", { fontSize: 10, align: "center", color: "#555" }),
      txt(45, 34, 150, 6, "Telp. 021-1234567 | Email: info@apoteksehat.id", { fontSize: 10, align: "center", color: "#555" }),
      line(15, 42, 180, "#222"),
      txt(15, 48, 180, 8, "KWITANSI", { fontSize: 16, align: "center" }),
      txt(15, 60, 45, 6, "No. Kwitansi", { fontSize: 11 }),
      txt(62, 60, 60, 6, "", { fontSize: 11, binding: "invoice.number" }),
      txt(130, 60, 35, 6, "Tanggal", { fontSize: 11 }),
      txt(15, 68, 45, 6, "Nama Pasien", { fontSize: 11 }),
      txt(62, 68, 133, 6, "", { fontSize: 11, binding: "patient.name" }),
      txt(15, 76, 45, 6, "Alamat", { fontSize: 11 }),
      txt(62, 76, 133, 12, "", { fontSize: 11, binding: "patient.address" }),
      table(15, 92, 180, 130, "items"),
      line(15, 228, 180),
      txt(15, 232, 100, 6, "Grand Total", { fontSize: 13 }),
      txt(115, 232, 80, 6, "", { fontSize: 13, align: "right", binding: "invoice.total" }),
      txt(15, 240, 100, 6, "Terbilang", { fontSize: 11 }),
      txt(115, 240, 80, 10, "", { fontSize: 11, align: "right", binding: "invoice.total_in_words" }),
      txt(15, 262, 80, 5, "Hormat kami,", { fontSize: 10 }),
      txt(15, 280, 80, 5, "", { fontSize: 10, binding: "cashier.name" }),
      qr(155, 260, 30, "invoice.number"),
    ],
  },
];
