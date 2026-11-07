<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Services;

use Config\Database;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class DistributionService
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect('pharmacy');
    }

    /**
     * Membuat pesanan distribusi.
     */
    public function createDistributionOrder(
        array $data,
        int $userId
    ): int {
        $this->validateOrderData($data);

        $now = date('Y-m-d H:i:s');

        $this->db->transBegin();

        try {
            $customerId = (int) $data['customer_id'];

            $customer = $this->db->table('customers')
                ->where('id', $customerId)
                ->where('is_active', 1)
                ->get()
                ->getRowArray();

            if (!$customer) {
                throw new InvalidArgumentException(
                    'Pelanggan tidak ditemukan atau tidak aktif.'
                );
            }

            $exists = $this->db->table('distribution_orders')
                ->where('order_number', trim($data['order_number']))
                ->countAllResults();

            if ($exists > 0) {
                throw new InvalidArgumentException(
                    'Nomor pesanan distribusi sudah digunakan.'
                );
            }

            $subtotal = 0.0;
            $itemDiscountTotal = 0.0;
            $items = [];

            foreach ($data['items'] as $item) {
                $productId = (int) $item['product_id'];

                $product = $this->db->table('products')
                    ->where('id', $productId)
                    ->where('is_active', 1)
                    ->get()
                    ->getRowArray();

                if (!$product) {
                    throw new InvalidArgumentException(
                        "Produk ID {$productId} tidak ditemukan atau tidak aktif."
                    );
                }

                $quantity = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];
                $discount = (float) ($item['discount_amount'] ?? 0);

                $lineGross = $quantity * $unitPrice;

                if ($discount > $lineGross) {
                    throw new InvalidArgumentException(
                        "Diskon produk ID {$productId} melebihi nilai item."
                    );
                }

                $lineSubtotal = $lineGross - $discount;

                $subtotal += $lineGross;
                $itemDiscountTotal += $discount;

                $items[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discount,
                    'subtotal' => $lineSubtotal,
                    'notes' => $item['notes'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $headerDiscount = (float) ($data['discount_amount'] ?? 0);
            $taxAmount = (float) ($data['tax_amount'] ?? 0);

            if ($headerDiscount < 0 || $taxAmount < 0) {
                throw new InvalidArgumentException(
                    'Diskon dan pajak tidak boleh bernilai negatif.'
                );
            }

            if ($itemDiscountTotal + $headerDiscount > $subtotal) {
                throw new InvalidArgumentException(
                    'Total diskon tidak boleh melebihi subtotal pesanan.'
                );
            }

            $totalDiscount = $itemDiscountTotal + $headerDiscount;
            $totalAmount = $subtotal - $totalDiscount + $taxAmount;

            $this->db->table('distribution_orders')->insert([
                'order_number' => trim($data['order_number']),
                'customer_id' => $customerId,
                'order_date' => $data['order_date'],
                'requested_delivery_date' => $data['requested_delivery_date'] ?? null,
                'status' => 'draft',
                'subtotal' => $subtotal,
                'discount_amount' => $totalDiscount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $orderId = (int) $this->db->insertID();

            foreach ($items as $item) {
                $item['distribution_order_id'] = $orderId;

                $this->db->table('distribution_items')->insert($item);
            }

            $this->completeTransaction();

            return $orderId;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Validasi data pesanan sebelum transaksi.
     */
    private function validateOrderData(array $data): void
    {
        foreach (['order_number', 'customer_id', 'order_date', 'items'] as $field) {
            if (
                !isset($data[$field])
                || $data[$field] === ''
                || $data[$field] === []
            ) {
                throw new InvalidArgumentException(
                    "Field {$field} wajib diisi."
                );
            }
        }

        if ((int) $data['customer_id'] <= 0) {
            throw new InvalidArgumentException(
                'Pelanggan tidak valid.'
            );
        }

        if (trim((string) $data['order_number']) === '') {
            throw new InvalidArgumentException(
                'Nomor pesanan wajib diisi.'
            );
        }

        $this->validateDate($data['order_date'], 'Tanggal pesanan');

        if (
            isset($data['requested_delivery_date'])
            && $data['requested_delivery_date'] !== null
            && $data['requested_delivery_date'] !== ''
        ) {
            $this->validateDate(
                $data['requested_delivery_date'],
                'Tanggal permintaan pengiriman'
            );
        }

        if (!is_array($data['items']) || count($data['items']) === 0) {
            throw new InvalidArgumentException(
                'Pesanan harus memiliki minimal satu detail produk.'
            );
        }

        foreach ($data['items'] as $index => $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException(
                    'Format detail pesanan tidak valid.'
                );
            }

            $productId = (int) ($item['product_id'] ?? 0);

            if ($productId <= 0) {
                throw new InvalidArgumentException(
                    'Produk pada detail pesanan tidak valid.'
                );
            }

            $quantity = $this->positiveQuantity(
                $item['quantity'] ?? null,
                'Kuantitas pesanan'
            );

            $unitPrice = $this->nonNegativeNumber(
                $item['unit_price'] ?? null,
                'Harga satuan'
            );

            $discount = $this->nonNegativeNumber(
                $item['discount_amount'] ?? 0,
                'Diskon item'
            );

            if ($discount > ($quantity * $unitPrice)) {
                throw new InvalidArgumentException(
                    'Diskon item tidak boleh melebihi nilai item.'
                );
            }
        }

        $this->nonNegativeNumber(
            $data['discount_amount'] ?? 0,
            'Diskon pesanan'
        );

        $this->nonNegativeNumber(
            $data['tax_amount'] ?? 0,
            'Pajak pesanan'
        );
    }

    /**
     * Mengambil detail pesanan distribusi beserta pelanggan,
     * produk, dan riwayat pengiriman.
     */
    public function getOrderDetail(int $id): array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'ID pesanan distribusi tidak valid.'
            );
        }

        $order = $this->db->table('distribution_orders o')
            ->select([
                'o.*',
                'c.name AS customer_name',
            ])
            ->join(
                'customers c',
                'c.id = o.customer_id',
                'left'
            )
            ->where('o.id', $id)
            ->get()
            ->getRowArray();

        if (!$order) {
            throw new RuntimeException(
                'Pesanan distribusi tidak ditemukan.'
            );
        }

        $items = $this->db->table('distribution_items di')
            ->select([
                'di.*',
                'p.name AS product_name',
                'p.sku',
            ])
            ->join(
                'products p',
                'p.id = di.product_id',
                'left'
            )
            ->where('di.distribution_order_id', $id)
            ->orderBy('di.id', 'ASC')
            ->get()
            ->getResultArray();

        $deliveries = $this->db->table('deliveries d')
            ->select([
                'd.*',
                'COALESCE(SUM(
                CASE
                    WHEN d.status = "posted"
                    THEN di.quantity_dispatched
                    ELSE 0
                END
            ), 0) AS total_dispatched',
            ])
            ->join(
                'delivery_items di',
                'di.delivery_id = d.id',
                'left'
            )
            ->where('d.distribution_order_id', $id)
            ->groupBy('d.id')
            ->orderBy('d.delivery_date', 'DESC')
            ->orderBy('d.id', 'DESC')
            ->get()
            ->getResultArray();

        // Ambil rincian batch untuk setiap dokumen pengiriman.
        foreach ($deliveries as &$delivery) {
            $delivery['items'] = $this->db->table('delivery_items di')
                ->select([
                    'di.id',
                    'di.delivery_id',
                    'di.distribution_item_id',
                    'di.batch_id',
                    'di.quantity_dispatched',
                    'di.unit_price',
                    'dist.product_id',
                    'p.name AS product_name',
                    'p.sku',
                    'pb.batch_number',
                    'pb.expired_at',
                ])
                ->join(
                    'distribution_items dist',
                    'dist.id = di.distribution_item_id',
                    'left'
                )
                ->join(
                    'products p',
                    'p.id = dist.product_id',
                    'left'
                )
                ->join(
                    'product_batches pb',
                    'pb.id = di.batch_id',
                    'left'
                )
                ->where('di.delivery_id', (int) $delivery['id'])
                ->orderBy('di.id', 'ASC')
                ->get()
                ->getResultArray();
        }

        unset($delivery);

        return [
            'order' => $order,
            'items' => $items,
            'deliveries' => $deliveries,
        ];
    }

    private function positiveQuantity(
        mixed $value,
        string $label
    ): float {
        if (
            !is_numeric($value)
            || !is_finite((float) $value)
            || (float) $value <= 0
        ) {
            throw new InvalidArgumentException(
                "{$label} harus lebih besar dari nol."
            );
        }

        $number = (float) $value;

        if (abs($number - round($number, 3)) > 0.000001) {
            throw new InvalidArgumentException(
                "{$label} maksimal memiliki tiga angka desimal."
            );
        }

        return round($number, 3);
    }

    private function nonNegativeNumber(
        mixed $value,
        string $label
    ): float {
        if (
            !is_numeric($value)
            || !is_finite((float) $value)
            || (float) $value < 0
        ) {
            throw new InvalidArgumentException(
                "{$label} tidak valid."
            );
        }

        return (float) $value;
    }

    private function completeTransaction(): void
    {
        if ($this->db->transStatus() === false) {
            throw new RuntimeException(
                'Transaksi database gagal.'
            );
        }

        $this->db->transCommit();
    }

    private function validateDate(
        mixed $value,
        string $label
    ): void {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                "{$label} tidak valid."
            );
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException(
                "{$label} harus menggunakan format YYYY-MM-DD."
            );
        }
    }
}
