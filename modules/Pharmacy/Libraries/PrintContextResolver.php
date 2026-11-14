<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Libraries;

use Modules\Pharmacy\Models\InvoiceModel;
use Modules\Pharmacy\Models\InvoiceItemModel;
use Modules\Pharmacy\Models\GoodsReceiptModel;
use Modules\Pharmacy\Models\GoodsReceiptItemModel;
use Modules\Pharmacy\Models\PurchaseOrderModel;
use Modules\Pharmacy\Models\PatientModel;
use Modules\Pharmacy\Models\SupplierModel;
use Modules\Organization\Models\UserModel;

class PrintContextResolver
{
    /**
     * Muat data dari DB berdasarkan query params.
     * Kalau tidak ada param yang cocok → return [] (binding kosong).
     *
     * @param array $query { invoice_id?, patient_id?, receipt_id?, supplier_id?, cashier_id?, purchase_order_id? }
     * @return array<string, mixed>
     */
    public function resolve(array $query): array
    {
        $data = [];

        // ---------- Invoice ----------
        if (! empty($query['invoice_id'])) {
            $invoiceId = (int) $query['invoice_id'];
            $invoice = model(InvoiceModel::class)->find($invoiceId);
            if ($invoice !== null) {
                $data['invoice'] = $this->normalizeInvoice($invoice);

                // Items (loop tabel)
                $data['items'] = model(InvoiceItemModel::class)
                    ->where('invoice_id', $invoiceId)
                    ->findAll();

                // Auto-load patient dari invoice
                if (empty($query['patient_id']) && ! empty($invoice['patient_id'])) {
                    $query['patient_id'] = (int) $invoice['patient_id'];
                }
            }
        }

        // ---------- Patient ----------
        if (! empty($query['patient_id'])) {
            $patient = model(PatientModel::class)->find((int) $query['patient_id']);
            if ($patient !== null) {
                $data['patient'] = $patient;
            }
        }

        // ---------- Cashier ----------
        if (! empty($query['cashier_id'])) {
            $cashier = model(UserModel::class)->find((int) $query['cashier_id']);
            if ($cashier !== null) {
                $data['cashier'] = [
                    'id'   => $cashier['id'] ?? null,
                    'name' => $cashier['name'] ?? $cashier['username'] ?? '',
                ];
            }
        }

        // ---------- Supplier ----------
        if (! empty($query['supplier_id'])) {
            $supplier = model(SupplierModel::class)->find((int) $query['supplier_id']);
            if ($supplier !== null) {
                $data['supplier'] = $supplier;
            }
        }

        // ---------- Penerimaan Barang ----------
        if (! empty($query['receipt_id'])) {
            $receiptId = (int) $query['receipt_id'];
            $receipt = model(GoodsReceiptModel::class)->find($receiptId);
            if ($receipt !== null) {
                $data['receipt'] = $receipt;
                $data['items'] = model(GoodsReceiptItemModel::class)
                    ->where('receipt_id', $receiptId)
                    ->findAll();

                if (empty($query['supplier_id']) && ! empty($receipt['supplier_id'])) {
                    $supplier = model(SupplierModel::class)->find((int) $receipt['supplier_id']);
                    if ($supplier !== null) $data['supplier'] = $supplier;
                }
            }
        }

        // ---------- Purchase Order ----------
        if (! empty($query['purchase_order_id'])) {
            $po = model(PurchaseOrderModel::class)->find((int) $query['purchase_order_id']);
            if ($po !== null) {
                $data['purchase_order'] = $po;
            }
        }

        return $data;
    }

    private function normalizeInvoice(array $invoice): array
    {
        $total  = (float) ($invoice['total']  ?? 0);
        $paid   = (float) ($invoice['paid']   ?? 0);
        $change = $paid - $total;

        $invoice['total_formatted']  = 'Rp ' . number_format($total, 0, ',', '.');
        $invoice['paid_formatted']   = 'Rp ' . number_format($paid, 0, ',', '.');
        $invoice['change_formatted'] = 'Rp ' . number_format($change, 0, ',', '.');
        $invoice['total_in_words']   = $this->numberToWords($total);

        return $invoice;
    }

    private function numberToWords(float $number): string
    {
        // Minimal: bisa diganti dengan library terbilang
        $n = (int) round($number);
        $satuan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
        if ($n < 12) return ucfirst($satuan[$n] ?? '') . ' rupiah';
        if ($n < 20) return ucfirst($satuan[$n - 10]) . ' belas rupiah';
        if ($n < 100) return ucfirst($satuan[intdiv($n, 10)]) . ' puluh ' . ($n % 10 ? $satuan[$n % 10] . ' ' : '') . 'rupiah';
        if ($n < 200) return 'seratus ' . ($n % 100 ? $this->numberToWords($n % 100) : 'rupiah');
        if ($n < 1000) return ucfirst($satuan[intdiv($n, 100)]) . ' ratus ' . ($n % 100 ? $this->numberToWords($n % 100) : 'rupiah');
        if ($n < 2000) return 'seribu ' . ($n % 1000 ? $this->numberToWords($n % 1000) : 'rupiah');
        if ($n < 1000000) return $this->numberToWords(intdiv($n, 1000)) . ' ribu ' . ($n % 1000 ? $this->numberToWords($n % 1000) : '');
        if ($n < 1000000000) return $this->numberToWords(intdiv($n, 1000000)) . ' juta ' . ($n % 1000000 ? $this->numberToWords($n % 1000000) : '');
        return 'Rp ' . number_format($n, 0, ',', '.');
    }
}
