<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Controllers\API\V1;

use App\Core\ApiController;
use Modules\Pharmacy\Models\Master\ProductModel;
use Modules\Pharmacy\Models\Master\CategoryModel;
use Modules\Pharmacy\Models\Master\UnitModel;
use Throwable;

class ProductApiController extends ApiController
{
    private ProductModel $productModel;
    private CategoryModel $categoryModel;
    private UnitModel $unitModel;

    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);

        $this->productModel = new ProductModel();
        $this->categoryModel = new CategoryModel();
        $this->unitModel = new UnitModel();
    }

    /* public function index()
    {
        try {
            $builder = $this->productModel
                ->select('products.*, categories.name AS category_name, units.name AS unit_name, units.abbreviation AS unit_abbreviation')
                ->join('categories', 'categories.id = products.category_id', 'left')
                ->join('units', 'units.id = products.unit_id', 'left');

            if ($this->request->getGet('search')) {
                $search = trim((string) $this->request->getGet('search'));

                $builder->groupStart()
                    ->like('products.name', $search)
                    ->orLike('products.sku', $search)
                    ->orLike('products.barcode', $search)
                    ->groupEnd();
            }

            if ($this->request->getGet('category_id')) {
                $builder->where(
                    'products.category_id',
                    (int) $this->request->getGet('category_id')
                );
            }

            if ($this->request->getGet('is_active') !== null) {
                $builder->where(
                    'products.is_active',
                    (int) $this->request->getGet('is_active')
                );
            }

            $data = $builder
                ->orderBy('products.name', 'ASC')
                ->findAll();

            return $this->successResponse($data, 'Data produk berhasil diambil.');
        } catch (Throwable $e) {
            log_message('error', '[ProductApiController::index] ' . $e->getMessage());
            return $this->errorResponse('Gagal mengambil data produk.', 500);
        }
    } */

    public function index()
    {
        try {
            $page = max(1, (int) ($this->request->getGet('page') ?? 1));

            $perPage = (int) ($this->request->getGet('per_page') ?? 10);
            $perPage = max(1, min($perPage, 100));

            $search = trim((string) ($this->request->getGet('search') ?? ''));

            $categoryId = (int) ($this->request->getGet('category_id') ?? 0);

            $isActive = $this->request->getGet('is_active');

            $dateFrom = trim((string) ($this->request->getGet('date_from') ?? ''));
            $dateTo = trim((string) ($this->request->getGet('date_to') ?? ''));

            // Validasi format tanggal YYYY-MM-DD.
            $validateDate = static function (string $date): bool {
                $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

                return $parsed !== false && $parsed->format('Y-m-d') === $date;
            };

            if (
                ($dateFrom !== '' && !$validateDate($dateFrom)) ||
                ($dateTo !== '' && !$validateDate($dateTo))
            ) {
                return $this->errorResponse('Format tanggal tidak valid.', 400);
            }

            if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
                return $this->errorResponse(
                    'Tanggal awal tidak boleh melebihi tanggal akhir.',
                    400
                );
            }

            // Validasi status: hanya 0 atau 1.
            if (
                $isActive !== null &&
                $isActive !== '' &&
                !in_array((string) $isActive, ['0', '1'], true)
            ) {
                return $this->errorResponse('Status produk tidak valid.', 400);
            }

            // Terapkan filter yang sama pada query total dan query data.
            $applyFilters = static function ($builder) use (
                $search,
                $categoryId,
                $isActive,
                $dateFrom,
                $dateTo
            ): void {
                if ($search !== '') {
                    $builder->groupStart()
                        ->like('products.name', $search)
                        ->orLike('products.sku', $search)
                        ->orLike('products.barcode', $search)
                        ->groupEnd();
                }

                if ($categoryId > 0) {
                    $builder->where('products.category_id', $categoryId);
                }

                if ($isActive !== null && $isActive !== '') {
                    $builder->where('products.is_active', (int) $isActive);
                }

                // Filter berdasarkan tanggal produk dibuat.
                if ($dateFrom !== '') {
                    $builder->where(
                        'products.created_at >=',
                        $dateFrom . ' 00:00:00'
                    );
                }

                if ($dateTo !== '') {
                    $builder->where(
                        'products.created_at <=',
                        $dateTo . ' 23:59:59'
                    );
                }
            };

            // Hitung total data setelah seluruh filter diterapkan.
            $countBuilder = $this->productModel->builder();

            $applyFilters($countBuilder);

            $total = $countBuilder->countAllResults();

            // Ambil data beserta relasi kategori dan satuan.
            $builder = $this->productModel
                ->select(
                    'products.*,
                categories.name AS category_name,
                units.name AS unit_name,
                units.abbreviation AS unit_abbreviation'
                )
                ->join(
                    'categories',
                    'categories.id = products.category_id',
                    'left'
                )
                ->join(
                    'units',
                    'units.id = products.unit_id',
                    'left'
                );

            $applyFilters($builder);

            $items = $builder
                ->orderBy('products.name', 'ASC')
                ->findAll($perPage, ($page - 1) * $perPage);

            $totalPages = (int) ceil($total / $perPage);

            return $this->successResponse([
                'items' => $items,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => $totalPages,
                ],
            ], 'Data produk berhasil diambil.');
        } catch (Throwable $e) {
            log_message(
                'error',
                '[ProductApiController::index] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal mengambil data produk.',
                500
            );
        }
    }

    public function show($id = null)
    {
        try {
            $id = (int) $id;

            if ($id <= 0) {
                return $this->errorResponse('ID produk tidak valid.', 400);
            }

            $product = $this->productModel
                ->select('products.*, categories.name AS category_name, units.name AS unit_name, units.abbreviation AS unit_abbreviation')
                ->join('categories', 'categories.id = products.category_id', 'left')
                ->join('units', 'units.id = products.unit_id', 'left')
                ->where('products.id', $id)
                ->first();

            if (!$product) {
                return $this->errorResponse('Produk tidak ditemukan.', 404);
            }

            return $this->successResponse($product, 'Detail produk berhasil diambil.');
        } catch (Throwable $e) {
            log_message('error', '[ProductApiController::show] ' . $e->getMessage());
            return $this->errorResponse('Gagal mengambil detail produk.', 500);
        }
    }

    public function create()
    {
        try {
            $data = $this->request->getJSON(true);

            if (!is_array($data)) {
                return $this->errorResponse('Format request tidak valid.', 400);
            }

            $rules = [
                'sku' => 'required|min_length[1]|max_length[50]',
                'barcode' => 'permit_empty|max_length[100]',
                'name' => 'required|min_length[2]|max_length[150]',
                'category_id' => 'required|is_natural_no_zero',
                'unit_id' => 'required|is_natural_no_zero',
                'purchase_price' => 'required|numeric|greater_than_equal_to[0]',
                'selling_price' => 'required|numeric|greater_than_equal_to[0]',
                'description' => 'permit_empty|max_length[2000]',
                'is_active' => 'permit_empty|in_list[0,1]',
            ];

            if (!$this->validateData($data, $rules)) {
                return $this->errorResponse(
                    'Validasi produk gagal.',
                    422,
                    $this->validator->getErrors()
                );
            }

            if (!$this->categoryModel->find((int) $data['category_id'])) {
                return $this->errorResponse('Kategori produk tidak ditemukan.', 422);
            }

            if (!$this->unitModel->find((int) $data['unit_id'])) {
                return $this->errorResponse('Satuan produk tidak ditemukan.', 422);
            }

            $sku = trim($data['sku']);
            $barcode = trim((string) ($data['barcode'] ?? ''));

            if ($this->productModel->where('sku', $sku)->first()) {
                return $this->errorResponse('SKU produk sudah digunakan.', 409);
            }

            if (
                $barcode !== '' &&
                $this->productModel->where('barcode', $barcode)->first()
            ) {
                return $this->errorResponse('Barcode produk sudah digunakan.', 409);
            }

            $userId = (int) auth()->id();

            $id = $this->productModel->insert([
                'sku' => $sku,
                'barcode' => $barcode !== '' ? $barcode : null,
                'name' => trim($data['name']),
                'category_id' => (int) $data['category_id'],
                'unit_id' => (int) $data['unit_id'],
                'purchase_price' => $data['purchase_price'],
                'selling_price' => $data['selling_price'],
                'description' => $data['description'] ?? null,
                'is_active' => isset($data['is_active'])
                    ? (int) $data['is_active']
                    : 1,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            if (!$id) {
                return $this->errorResponse(
                    'Gagal menyimpan produk.',
                    500,
                    $this->productModel->errors()
                );
            }

            return $this->successResponse(
                $this->productModel->find($id),
                'Produk berhasil ditambahkan.',
                201
            );
        } catch (Throwable $e) {
            log_message('error', '[ProductApiController::create] ' . $e->getMessage());
            return $this->errorResponse('Terjadi kesalahan saat menambahkan produk.', 500);
        }
    }

    public function update($id = null)
    {
        try {
            $id = (int) $id;

            if ($id <= 0) {
                return $this->errorResponse('ID produk tidak valid.', 400);
            }

            if (!$this->productModel->find($id)) {
                return $this->errorResponse('Produk tidak ditemukan.', 404);
            }

            $data = $this->request->getJSON(true);

            if (!is_array($data) || !$data) {
                return $this->errorResponse('Data perubahan produk wajib diisi.', 400);
            }

            $rules = [
                'sku' => 'permit_empty|min_length[1]|max_length[50]',
                'barcode' => 'permit_empty|max_length[100]',
                'name' => 'permit_empty|min_length[2]|max_length[150]',
                'category_id' => 'permit_empty|is_natural_no_zero',
                'unit_id' => 'permit_empty|is_natural_no_zero',
                'purchase_price' => 'permit_empty|numeric|greater_than_equal_to[0]',
                'selling_price' => 'permit_empty|numeric|greater_than_equal_to[0]',
                'description' => 'permit_empty|max_length[2000]',
                'is_active' => 'permit_empty|in_list[0,1]',
            ];

            if (!$this->validateData($data, $rules)) {
                return $this->errorResponse(
                    'Validasi perubahan produk gagal.',
                    422,
                    $this->validator->getErrors()
                );
            }

            $current = $this->productModel->find($id);
            $update = [];

            foreach (
                [
                    'sku',
                    'barcode',
                    'name',
                    'category_id',
                    'unit_id',
                    'purchase_price',
                    'selling_price',
                    'description',
                    'is_active',
                ] as $field
            ) {
                if (array_key_exists($field, $data)) {
                    $update[$field] = $data[$field];
                }
            }

            if (isset($update['sku'])) {
                $update['sku'] = trim((string) $update['sku']);

                $duplicate = $this->productModel
                    ->where('sku', $update['sku'])
                    ->where('id !=', $id)
                    ->first();

                if ($duplicate) {
                    return $this->errorResponse('SKU produk sudah digunakan.', 409);
                }
            }

            if (array_key_exists('barcode', $update)) {
                $update['barcode'] = trim((string) $update['barcode']);

                if ($update['barcode'] === '') {
                    $update['barcode'] = null;
                } else {
                    $duplicate = $this->productModel
                        ->where('barcode', $update['barcode'])
                        ->where('id !=', $id)
                        ->first();

                    if ($duplicate) {
                        return $this->errorResponse('Barcode produk sudah digunakan.', 409);
                    }
                }
            }

            if (
                isset($update['category_id']) &&
                !$this->categoryModel->find((int) $update['category_id'])
            ) {
                return $this->errorResponse('Kategori produk tidak ditemukan.', 422);
            }

            if (
                isset($update['unit_id']) &&
                !$this->unitModel->find((int) $update['unit_id'])
            ) {
                return $this->errorResponse('Satuan produk tidak ditemukan.', 422);
            }

            if (!$update) {
                return $this->errorResponse('Tidak ada data yang diperbarui.', 400);
            }

            $update['updated_by'] = (int) auth()->id();

            if (!$this->productModel->update($id, $update)) {
                return $this->errorResponse(
                    'Gagal memperbarui produk.',
                    500,
                    $this->productModel->errors()
                );
            }

            return $this->successResponse(
                $this->productModel->find($id),
                'Produk berhasil diperbarui.'
            );
        } catch (Throwable $e) {
            log_message('error', '[ProductApiController::update] ' . $e->getMessage());
            return $this->errorResponse('Terjadi kesalahan saat memperbarui produk.', 500);
        }
    }

    public function delete($id = null)
    {
        try {
            $id = (int) $id;

            if ($id <= 0) {
                return $this->errorResponse('ID produk tidak valid.', 400);
            }

            if (!$this->productModel->find($id)) {
                return $this->errorResponse('Produk tidak ditemukan.', 404);
            }

            if (!$this->productModel->delete($id)) {
                return $this->errorResponse('Gagal menghapus produk.', 500);
            }

            return $this->successResponse(['id' => $id], 'Produk berhasil dihapus.');
        } catch (Throwable $e) {
            log_message('error', '[ProductApiController::delete] ' . $e->getMessage());
            return $this->errorResponse('Terjadi kesalahan saat menghapus produk.', 500);
        }
    }
}
