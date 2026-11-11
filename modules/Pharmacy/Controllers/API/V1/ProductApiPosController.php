<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Controllers\API\V1;

use App\Core\ApiController;
use Config\Database;
use DateTimeImmutable;
use Throwable;

class ProductApiPosController extends ApiController
{
    private $db;

    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);

        $this->db = Database::connect('pharmacy');
    }

    public function index()
    {
        try {
            $page = max(
                1,
                (int) ($this->request->getGet('page') ?? 1)
            );

            $perPage = (int) ($this->request->getGet('per_page') ?? 20);
            $perPage = max(1, min($perPage, 100));

            $search = trim(
                (string) ($this->request->getGet('search') ?? '')
            );

            $saleDate = date('Y-m-d');

            // Validasi tanggal transaksi.
            $parsedDate = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $saleDate
            );

            if (
                $parsedDate === false
                || $parsedDate->format('Y-m-d') !== $saleDate
            ) {
                return $this->errorResponse(
                    'Tanggal transaksi tidak valid.',
                    400
                );
            }

            if (mb_strlen($search) > 100) {
                return $this->errorResponse(
                    'Kata pencarian terlalu panjang.',
                    400
                );
            }

            /*
             * Subquery stok layak jual.
             *
             * Mengikuti aturan allocateStock():
             * - Batch aktif.
             * - Stok tersisa lebih dari nol.
             * - Belum kedaluwarsa pada tanggal transaksi.
             */
            $stockSubquery = '
                (
                    SELECT
                        product_id,
                        SUM(quantity_remaining) AS stock_available
                    FROM product_batches
                    WHERE status = ' . $this->db->escape('available') . '
                      AND quantity_remaining > 0
                      AND (
                          expired_at IS NULL
                          OR expired_at >= ' . $this->db->escape($saleDate) . '
                      )
                    GROUP BY product_id
                ) stock
            ';

            /*
             * Builder yang sama digunakan untuk menghitung
             * total data dan mengambil hasil pencarian.
             */
            $buildQuery = function () use (
                $stockSubquery,
                $search
            ) {
                $builder = $this->db->table('products');

                $builder->join(
                    $stockSubquery,
                    'stock.product_id = products.id',
                    'inner',
                    false
                );

                $builder->join(
                    'categories',
                    'categories.id = products.category_id',
                    'left'
                );

                $builder->join(
                    'units',
                    'units.id = products.unit_id',
                    'left'
                );

                $builder->where('products.is_active', 1);

                if ($search !== '') {
                    $builder->groupStart()
                        ->like('products.name', $search)
                        ->orLike('products.sku', $search)
                        ->orLike('products.barcode', $search)
                        ->groupEnd();
                }

                return $builder;
            };

            // Hitung total produk yang memenuhi ketentuan POS.
            $total = $buildQuery()->countAllResults();
            // print_r($total);
            // exit;
            // Ambil data produk.
            $builder = $buildQuery();

            $builder->select([
                'products.id',
                'products.sku',
                'products.barcode',
                'products.name',
                'products.category_id',
                'products.unit_id',
                'products.is_active',
                'products.selling_price',
                'categories.name AS category_name',
                'units.name AS unit_name',
                'units.abbreviation AS unit_abbreviation',
                'stock.stock_available',
            ]);

            $items = $builder
                ->orderBy('products.name', 'ASC')
                ->limit($perPage, ($page - 1) * $perPage)
                ->get()
                ->getResultArray();

            return $this->successResponse([
                'items' => $items,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
                'sale_date' => $saleDate,
            ], 'Produk POS berhasil diambil.');
        } catch (Throwable $e) {
            log_message(
                'error',
                '[ProductApiPosController::index] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal mengambil produk POS.',
                500
            );
        }
    }
}
