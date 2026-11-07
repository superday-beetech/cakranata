<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Services;

use Config\Database;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class DeliveryService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect('pharmacy');
    }

    /**
     * Mengambil daftar dokumen pengiriman.
     */
    public function getDeliveries(array $filters = []): array
    {
        $builder = $this->db->table('deliveries d')
            ->select([
                'd.*',
                'o.order_number',
                'o.customer_id',
            ])
            ->join(
                'distribution_orders o',
                'o.id = d.distribution_order_id',
                'left'
            );

        if (!empty($filters['status'])) {
            $builder->where('d.status', $filters['status']);
        }

        if (!empty($filters['distribution_order_id'])) {
            $builder->where(
                'd.distribution_order_id',
                (int) $filters['distribution_order_id']
            );
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);

            $builder->groupStart()
                ->like('d.delivery_number', $search)
                ->orLike('o.order_number', $search)
                ->orLike('d.recipient_name', $search)
                ->groupEnd();
        }

        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));
        $page = max(1, (int) ($filters['page'] ?? 1));

        $countBuilder = $this->db->table('deliveries d')
            ->join(
                'distribution_orders o',
                'o.id = d.distribution_order_id',
                'left'
            );

        if (!empty($filters['status'])) {
            $countBuilder->where('d.status', $filters['status']);
        }

        if (!empty($filters['distribution_order_id'])) {
            $countBuilder->where(
                'd.distribution_order_id',
                (int) $filters['distribution_order_id']
            );
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);

            $countBuilder->groupStart()
                ->like('d.delivery_number', $search)
                ->orLike('o.order_number', $search)
                ->orLike('d.recipient_name', $search)
                ->groupEnd();
        }

        $total = (int) $countBuilder->countAllResults();

        $items = $builder
            ->orderBy('d.delivery_date', 'DESC')
            ->orderBy('d.id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
                'has_next' => ($page * $perPage) < $total,
                'has_prev' => $page > 1,
            ],
        ];
    }

    /**
     * Mengambil detail delivery beserta batch yang dialokasikan.
     */
    public function getDeliveryDetail(int $id): array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'ID pengiriman tidak valid.'
            );
        }

        $delivery = $this->db->table('deliveries d')
            ->select([
                'd.*',
                'o.order_number',
                'o.customer_id',
                'o.order_date',
                'o.status AS order_status',
            ])
            ->join(
                'distribution_orders o',
                'o.id = d.distribution_order_id',
                'left'
            )
            ->where('d.id', $id)
            ->get()
            ->getRowArray();

        if (!$delivery) {
            throw new RuntimeException(
                'Dokumen pengiriman tidak ditemukan.'
            );
        }

        // Produk dan kuantitas dari pesanan distribusi.
        $orderItems = $this->db->table('distribution_items dist')
            ->select([
                'dist.id',
                'dist.distribution_order_id',
                'dist.product_id',
                'dist.quantity',
                'dist.unit_price',
                'dist.discount_amount',
                'dist.subtotal',
                'dist.notes',
                'p.name AS product_name',
                'p.sku',
            ])
            ->join(
                'products p',
                'p.id = dist.product_id',
                'left'
            )
            ->where(
                'dist.distribution_order_id',
                $delivery['distribution_order_id']
            )
            ->orderBy('dist.id', 'ASC')
            ->get()
            ->getResultArray();

        // Alokasi batch yang sudah tersimpan pada pengiriman.
        $items = $this->db->table('delivery_items di')
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
            ->where('di.delivery_id', $id)
            ->orderBy('di.id', 'ASC')
            ->get()
            ->getResultArray();

        return [
            'delivery' => $delivery,
            'order_items' => $orderItems,
            'items' => $items,
        ];
    }

    /**
     * Membuat dokumen delivery.
     *
     * Implementasi transaksi dipindahkan dari DistributionService.
     */
    /*   public function createDelivery(array $data, int $userId): int
    {
        throw new RuntimeException(
            'Implementasi createDelivery perlu dipindahkan dari DistributionService.'
        );
    } */

    /**
     * Posting delivery dan alokasi batch.
     *
     * Implementasi transaksi stok dipindahkan dari DistributionService.
     */
    /*  public function postDelivery(
        int $id,
        int $userId,
        array $allocations = []
    ): array {
        throw new RuntimeException(
            'Implementasi postDelivery perlu dipindahkan dari DistributionService.'
        );
    } */

    /**
     * Membuat dokumen pengiriman berstatus draft.
     *
     * Nomor pengiriman dibuat oleh backend berdasarkan ID dokumen,
     * bukan menggunakan nomor pesanan dari frontend.
     */
    public function createDelivery(
        array $data,
        int $userId
    ): int {
        if (
            empty($data['distribution_order_id'])
            || empty($data['delivery_date'])
        ) {
            throw new InvalidArgumentException(
                'Pesanan dan tanggal pengiriman wajib diisi.'
            );
        }

        $this->validateDate(
            $data['delivery_date'],
            'Tanggal pengiriman'
        );

        $orderId = (int) $data['distribution_order_id'];

        if ($orderId <= 0) {
            throw new InvalidArgumentException(
                'Data pesanan tidak valid.'
            );
        }

        $now = date('Y-m-d H:i:s');

        $this->db->transBegin();

        try {
            // Kunci pesanan untuk mencegah proses pembuatan delivery
            // bersamaan pada pesanan yang sama.
            $order = $this->db->query(
                'SELECT *
             FROM distribution_orders
             WHERE id = ?
             FOR UPDATE',
                [$orderId]
            )->getRowArray();

            if (!$order) {
                throw new InvalidArgumentException(
                    'Pesanan distribusi tidak ditemukan.'
                );
            }

            if (
                in_array(
                    $order['status'],
                    ['cancelled', 'completed'],
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'Pesanan tidak dapat dibuatkan pengiriman.'
                );
            }

            $itemCount = $this->db->table('distribution_items')
                ->where('distribution_order_id', $orderId)
                ->countAllResults();

            if ($itemCount === 0) {
                throw new InvalidArgumentException(
                    'Pesanan belum memiliki detail produk.'
                );
            }

            // Nomor sementara unik untuk memenuhi kolom NOT NULL dan UNIQUE.
            $temporaryNumber = 'TMP-' . bin2hex(random_bytes(12));

            $this->db->table('deliveries')->insert([
                'delivery_number' => $temporaryNumber,
                'distribution_order_id' => $orderId,
                'delivery_date' => $data['delivery_date'],
                'status' => 'draft',
                'recipient_name' => $data['recipient_name'] ?? null,
                'recipient_phone' => $data['recipient_phone'] ?? null,
                'shipping_address' => $data['shipping_address'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $deliveryId = (int) $this->db->insertID();

            if ($deliveryId <= 0) {
                throw new RuntimeException(
                    'Gagal membuat dokumen pengiriman.'
                );
            }

            // Nomor final memakai ID delivery sehingga unik.
            $deliveryNumber = sprintf(
                'DLV-%s-%06d',
                date('Y', strtotime($data['delivery_date'])),
                $deliveryId
            );

            $this->db->table('deliveries')
                ->where('id', $deliveryId)
                ->update([
                    'delivery_number' => $deliveryNumber,
                    'updated_at' => $now,
                ]);

            $this->completeTransaction();

            return $deliveryId;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Posting pengiriman.
     *
     * Jika allocations kosong, sistem mengalokasikan seluruh sisa pesanan
     * secara otomatis menggunakan FEFO.
     *
     * Jika allocations diisi, sistem hanya mengirim item yang dialokasikan.
     * Ini memungkinkan pengiriman sebagian.
     */
    public function postDelivery(
        int $deliveryId,
        int $userId,
        array $allocations = []
    ): array {
        if ($deliveryId <= 0) {
            throw new InvalidArgumentException(
                'ID pengiriman tidak valid.'
            );
        }

        $now = date('Y-m-d H:i:s');

        $this->db->transBegin();

        try {
            $delivery = $this->db->query(
                'SELECT * FROM deliveries WHERE id = ? FOR UPDATE',
                [$deliveryId]
            )->getRowArray();

            if (!$delivery) {
                throw new InvalidArgumentException(
                    'Dokumen pengiriman tidak ditemukan.'
                );
            }

            if ($delivery['status'] !== 'draft') {
                throw new InvalidArgumentException(
                    'Pengiriman sudah diproses atau bukan berstatus draft.'
                );
            }

            $orderId = (int) $delivery['distribution_order_id'];

            $order = $this->db->query(
                'SELECT * FROM distribution_orders WHERE id = ? FOR UPDATE',
                [$orderId]
            )->getRowArray();

            if (!$order) {
                throw new InvalidArgumentException(
                    'Pesanan distribusi tidak ditemukan.'
                );
            }

            if (in_array($order['status'], ['cancelled', 'completed'], true)) {
                throw new InvalidArgumentException(
                    'Pesanan tidak dapat diproses.'
                );
            }

            $orderItems = $this->db->query(
                'SELECT * FROM distribution_items
                 WHERE distribution_order_id = ?
                 ORDER BY id ASC
                 FOR UPDATE',
                [$orderId]
            )->getResultArray();

            if (!$orderItems) {
                throw new InvalidArgumentException(
                    'Detail pesanan tidak ditemukan.'
                );
            }

            // Pastikan semua ID item pada alokasi memang milik pesanan ini.
            $validItemIds = array_map(
                static fn(array $item): int => (int) $item['id'],
                $orderItems
            );

            foreach (array_keys($allocations) as $allocationItemId) {
                if (!in_array((int) $allocationItemId, $validItemIds, true)) {
                    throw new InvalidArgumentException(
                        "Item distribusi ID {$allocationItemId} bukan bagian dari pesanan ini."
                    );
                }
            }

            $postedItems = 0;
            $totalDispatched = 0.0;

            foreach ($orderItems as $orderItem) {
                $itemId = (int) $orderItem['id'];
                $productId = (int) $orderItem['product_id'];

                $deliveredRow = $this->db->query(
                    'SELECT COALESCE(SUM(di.quantity_dispatched), 0) AS total
                     FROM delivery_items di
                     INNER JOIN deliveries d ON d.id = di.delivery_id
                     WHERE di.distribution_item_id = ?
                       AND d.status = ?',
                    [$itemId, 'posted']
                )->getRowArray();

                $alreadyDelivered = (float) ($deliveredRow['total'] ?? 0);
                $orderedQuantity = (float) $orderItem['quantity'];
                $remainingOrder = $orderedQuantity - $alreadyDelivered;

                if ($remainingOrder <= 0.0001) {
                    continue;
                }

                if ($allocations === []) {
                    $requestedAllocations = $this->allocateFefo(
                        $productId,
                        $remainingOrder
                    );
                } else {
                    // Alokasi manual: item yang tidak dikirim boleh tidak disertakan.
                    $requestedAllocations = $allocations[$itemId] ?? [];
                }

                if (!is_array($requestedAllocations)) {
                    throw new InvalidArgumentException(
                        "Format alokasi item ID {$itemId} tidak valid."
                    );
                }

                if ($requestedAllocations === []) {
                    continue;
                }

                $allocationTotal = 0.0;
                $usedBatchIds = [];

                foreach ($requestedAllocations as $allocation) {
                    if (!is_array($allocation)) {
                        throw new InvalidArgumentException(
                            'Format detail alokasi batch tidak valid.'
                        );
                    }

                    $batchId = (int) ($allocation['batch_id'] ?? 0);
                    $quantity = $this->positiveQuantity(
                        $allocation['quantity'] ?? null,
                        'Kuantitas alokasi'
                    );

                    if ($batchId <= 0) {
                        throw new InvalidArgumentException(
                            'ID batch tidak valid.'
                        );
                    }

                    if (isset($usedBatchIds[$batchId])) {
                        throw new InvalidArgumentException(
                            "Batch ID {$batchId} tercantum lebih dari satu kali pada item yang sama."
                        );
                    }

                    $usedBatchIds[$batchId] = true;
                    $allocationTotal += $quantity;
                }

                if ($allocationTotal > $remainingOrder + 0.0001) {
                    throw new InvalidArgumentException(
                        "Jumlah pengiriman melebihi sisa pesanan item ID {$itemId}."
                    );
                }

                foreach ($requestedAllocations as $allocation) {
                    $batchId = (int) $allocation['batch_id'];
                    $quantity = (float) $allocation['quantity'];

                    // Kunci batch agar saldo tidak digunakan oleh transaksi lain.
                    $batch = $this->db->query(
                        'SELECT * FROM product_batches
                         WHERE id = ?
                           AND product_id = ?
                         FOR UPDATE',
                        [$batchId, $productId]
                    )->getRowArray();

                    if (!$batch) {
                        throw new InvalidArgumentException(
                            "Batch ID {$batchId} tidak ditemukan untuk produk ini."
                        );
                    }

                    if ($batch['status'] !== 'available') {
                        throw new InvalidArgumentException(
                            "Batch ID {$batchId} tidak tersedia untuk dikirim."
                        );
                    }

                    if (
                        !empty($batch['expired_at'])
                        && $batch['expired_at'] < date('Y-m-d')
                    ) {
                        throw new InvalidArgumentException(
                            "Batch ID {$batchId} sudah kedaluwarsa."
                        );
                    }

                    $batchBalance = (float) $batch['quantity_remaining'];

                    if ($batchBalance + 0.0001 < $quantity) {
                        throw new InvalidArgumentException(
                            "Stok batch ID {$batchId} tidak mencukupi."
                        );
                    }

                    $newBalance = round($batchBalance - $quantity, 3);

                    $this->db->table('product_batches')
                        ->where('id', $batchId)
                        ->where('quantity_remaining >=', $quantity)
                        ->update([
                            'quantity_remaining' => $newBalance,
                            'updated_at' => $now,
                        ]);

                    if ($this->db->affectedRows() !== 1) {
                        throw new RuntimeException(
                            "Gagal memperbarui saldo batch ID {$batchId}."
                        );
                    }

                    $this->db->table('delivery_items')->insert([
                        'delivery_id' => $deliveryId,
                        'distribution_item_id' => $itemId,
                        'batch_id' => $batchId,
                        'quantity_dispatched' => $quantity,
                        'unit_price' => (float) $orderItem['unit_price'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $this->db->table('stock_movements')->insert([
                        'product_id' => $productId,
                        'batch_id' => $batchId,
                        'movement_date' => $delivery['delivery_date'],
                        'movement_type' => 'out',
                        'reference_type' => 'delivery',
                        'reference_id' => $deliveryId,
                        'quantity_in' => 0,
                        'quantity_out' => $quantity,
                        'unit_cost' => $batch['unit_cost'],
                        'notes' => 'Delivery ' . $delivery['delivery_number'],
                        'created_by' => $userId,
                        'created_at' => $now,
                    ]);

                    $postedItems++;
                    $totalDispatched += $quantity;
                }
            }

            if ($postedItems === 0) {
                throw new InvalidArgumentException(
                    'Tidak ada barang yang dapat dikirim.'
                );
            }

            $this->db->table('deliveries')
                ->where('id', $deliveryId)
                ->update([
                    'status' => 'posted',
                    'updated_by' => $userId,
                    'updated_at' => $now,
                ]);

            $status = $this->resolveOrderStatus($orderId);

            $this->db->table('distribution_orders')
                ->where('id', $orderId)
                ->update([
                    'status' => $status,
                    'updated_by' => $userId,
                    'updated_at' => $now,
                ]);

            $this->completeTransaction();

            return [
                'delivery_id' => $deliveryId,
                'status' => 'posted',
                'order_status' => $status,
                'total_dispatched' => round($totalDispatched, 3),
            ];
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }


    /**
     * Alokasi stok berdasarkan FEFO.
     */
    private function allocateFefo(
        int $productId,
        float $quantity
    ): array {
        $batches = $this->db->query(
            'SELECT * FROM product_batches
             WHERE product_id = ?
               AND status = ?
               AND quantity_remaining > 0
               AND (expired_at IS NULL OR expired_at >= ?)
             ORDER BY expired_at IS NULL ASC, expired_at ASC, id ASC',
            [$productId, 'available', date('Y-m-d')]
        )->getResultArray();

        $remaining = $quantity;
        $allocations = [];

        foreach ($batches as $batch) {
            if ($remaining <= 0.0001) {
                break;
            }

            $available = (float) $batch['quantity_remaining'];
            $take = min($available, $remaining);
            $take = round($take, 3);

            if ($take <= 0) {
                continue;
            }

            $allocations[] = [
                'batch_id' => (int) $batch['id'],
                'quantity' => $take,
            ];

            $remaining = round($remaining - $take, 3);
        }

        if ($remaining > 0.0001) {
            throw new InvalidArgumentException(
                "Stok tidak mencukupi untuk produk ID {$productId}."
            );
        }

        return $allocations;
    }

    /**
     * Menentukan status pesanan berdasarkan pemenuhan setiap item.
     */
    private function resolveOrderStatus(int $orderId): string
    {
        $items = $this->db->query(
            'SELECT
                oi.id,
                oi.quantity,
                COALESCE(SUM(
                    CASE WHEN d.status = ? THEN di.quantity_dispatched ELSE 0 END
                ), 0) AS delivered_quantity
             FROM distribution_items oi
             LEFT JOIN delivery_items di
                ON di.distribution_item_id = oi.id
             LEFT JOIN deliveries d
                ON d.id = di.delivery_id
             WHERE oi.distribution_order_id = ?
             GROUP BY oi.id, oi.quantity',
            ['posted', $orderId]
        )->getResultArray();

        if (!$items) {
            throw new RuntimeException(
                'Detail pesanan tidak ditemukan saat menghitung status.'
            );
        }

        $hasDelivery = false;
        $allCompleted = true;

        foreach ($items as $item) {
            $ordered = (float) $item['quantity'];
            $delivered = (float) $item['delivered_quantity'];

            if ($delivered > 0.0001) {
                $hasDelivery = true;
            }

            if ($delivered < $ordered - 0.0001) {
                $allCompleted = false;
            }
        }

        if ($allCompleted) {
            return 'completed';
        }

        if ($hasDelivery) {
            return 'partially_delivered';
        }

        return 'draft';
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
}
