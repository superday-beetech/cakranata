<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Controllers\API\V1;

use App\Core\ApiController;
use Modules\Pharmacy\Models\Master\SupplierModel;
use Throwable;

class SupplierApiController extends ApiController
{
    private SupplierModel $supplierModel;

    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);

        $this->supplierModel = new SupplierModel();
    }

    /**
     * GET /supplier-api
     */
    public function index()
    {
        try {
            $page = max(1, (int) ($this->request->getGet('page') ?? 1));

            $perPage = (int) ($this->request->getGet('per_page') ?? 10);
            $perPage = max(1, min($perPage, 100));

            $search = trim((string) ($this->request->getGet('search') ?? ''));

            $isActive = $this->request->getGet('is_active');

            if (
                $isActive !== null &&
                $isActive !== '' &&
                !in_array((string) $isActive, ['0', '1'], true)
            ) {
                return $this->errorResponse('Status supplier tidak valid.', 400);
            }

            $applyFilters = static function ($builder) use (
                $search,
                $isActive
            ): void {
                if ($search !== '') {
                    $builder->groupStart()
                        ->like('suppliers.code', $search)
                        ->orLike('suppliers.name', $search)
                        ->orLike('suppliers.phone', $search)
                        ->orLike('suppliers.email', $search)
                        ->groupEnd();
                }

                if ($isActive !== null && $isActive !== '') {
                    $builder->where('suppliers.is_active', (int) $isActive);
                }
            };

            // Hitung total data sesuai filter.
            $countBuilder = $this->supplierModel->builder();

            $applyFilters($countBuilder);

            $total = $countBuilder->countAllResults();

            // Ambil data supplier.
            $builder = $this->supplierModel->builder();

            $applyFilters($builder);

            $items = $builder
                ->select('suppliers.*')
                ->orderBy('suppliers.name', 'ASC')
                ->get($perPage, ($page - 1) * $perPage)
                ->getResultArray();

            return $this->successResponse([
                'items' => $items,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ], 'Data supplier berhasil diambil.');
        } catch (Throwable $e) {
            log_message(
                'error',
                '[SupplierApiController::index] ' . $e->getMessage()
            );

            return $this->errorResponse('Gagal mengambil data supplier.', 500);
        }
    }

    /**
     * GET /supplier-api/show/{id}
     */
    public function show($id = null)
    {
        try {
            $id = (int) $id;

            if ($id <= 0) {
                return $this->errorResponse('ID supplier tidak valid.', 400);
            }

            $supplier = $this->supplierModel->find($id);

            if (!$supplier) {
                return $this->errorResponse('Supplier tidak ditemukan.', 404);
            }

            return $this->successResponse(
                $supplier,
                'Detail supplier berhasil diambil.'
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[SupplierApiController::show] ' . $e->getMessage()
            );

            return $this->errorResponse('Gagal mengambil detail supplier.', 500);
        }
    }

    /**
     * POST /supplier-api/create
     */
    public function create()
    {
        try {
            $data = $this->getRequestData();

            $code = strtoupper(trim((string) ($data['code'] ?? '')));
            $name = trim((string) ($data['name'] ?? ''));

            $phone = trim((string) ($data['phone'] ?? ''));
            $email = trim((string) ($data['email'] ?? ''));
            $address = trim((string) ($data['address'] ?? ''));

            if ($code === '' || strlen($code) > 30) {
                return $this->errorResponse(
                    'Kode supplier wajib diisi dan maksimal 30 karakter.',
                    422
                );
            }

            if ($name === '' || mb_strlen($name) > 150) {
                return $this->errorResponse(
                    'Nama supplier wajib diisi dan maksimal 150 karakter.',
                    422
                );
            }

            if (strlen($phone) > 30) {
                return $this->errorResponse(
                    'Nomor telepon maksimal 30 karakter.',
                    422
                );
            }

            if (strlen($email) > 150 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
                return $this->errorResponse('Format email tidak valid.', 422);
            }

            // Cek duplikasi kode.
            if ($this->supplierModel->where('code', $code)->first()) {
                return $this->errorResponse(
                    'Kode supplier sudah digunakan.',
                    409
                );
            }

            $isActive = $data['is_active'] ?? 1;

            if (!in_array((string) $isActive, ['0', '1'], true)) {
                return $this->errorResponse('Status supplier tidak valid.', 422);
            }

            $insertData = [
                'code'       => $code,
                'name'       => $name,
                'phone'      => $phone !== '' ? $phone : null,
                'email'      => $email !== '' ? $email : null,
                'address'    => $address !== '' ? $address : null,
                'is_active'  => (int) $isActive,
                'created_by' => auth()->id(),
            ];

            if (!$this->supplierModel->insert($insertData)) {
                return $this->errorResponse(
                    $this->supplierModel->errors() ?: 'Gagal menyimpan supplier.',
                    422
                );
            }

            $id = (int) $this->supplierModel->getInsertID();

            return $this->successResponse(
                $this->supplierModel->find($id),
                'Supplier berhasil ditambahkan.',
                201
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[SupplierApiController::create] ' . $e->getMessage()
            );

            return $this->errorResponse('Gagal menambahkan supplier.', 500);
        }
    }

    /**
     * PUT /supplier-api/update/{id}
     */
    public function update($id = null)
    {
        try {
            $id = (int) $id;

            if ($id <= 0) {
                return $this->errorResponse('ID supplier tidak valid.', 400);
            }

            $supplier = $this->supplierModel->find($id);

            if (!$supplier) {
                return $this->errorResponse('Supplier tidak ditemukan.', 404);
            }

            $data = $this->getRequestData();

            if (empty($data)) {
                return $this->errorResponse('Tidak ada data yang diperbarui.', 400);
            }

            $allowedFields = [
                'code',
                'name',
                'phone',
                'email',
                'address',
                'is_active',
            ];

            $updateData = array_intersect_key(
                $data,
                array_flip($allowedFields)
            );

            if (empty($updateData)) {
                return $this->errorResponse('Tidak ada field yang valid.', 400);
            }

            if (array_key_exists('code', $updateData)) {
                $code = strtoupper(trim((string) $updateData['code']));

                if ($code === '' || strlen($code) > 30) {
                    return $this->errorResponse(
                        'Kode supplier wajib diisi dan maksimal 30 karakter.',
                        422
                    );
                }

                $duplicate = $this->supplierModel
                    ->where('code', $code)
                    ->where('id !=', $id)
                    ->first();

                if ($duplicate) {
                    return $this->errorResponse(
                        'Kode supplier sudah digunakan.',
                        409
                    );
                }

                $updateData['code'] = $code;
            }

            if (array_key_exists('name', $updateData)) {
                $name = trim((string) $updateData['name']);

                if ($name === '' || mb_strlen($name) > 150) {
                    return $this->errorResponse(
                        'Nama supplier wajib diisi dan maksimal 150 karakter.',
                        422
                    );
                }

                $updateData['name'] = $name;
            }

            if (array_key_exists('phone', $updateData)) {
                $phone = trim((string) ($updateData['phone'] ?? ''));

                if (strlen($phone) > 30) {
                    return $this->errorResponse(
                        'Nomor telepon maksimal 30 karakter.',
                        422
                    );
                }

                $updateData['phone'] = $phone !== '' ? $phone : null;
            }

            if (array_key_exists('email', $updateData)) {
                $email = trim((string) ($updateData['email'] ?? ''));

                if (
                    strlen($email) > 150 ||
                    ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
                ) {
                    return $this->errorResponse('Format email tidak valid.', 422);
                }

                $updateData['email'] = $email !== '' ? $email : null;
            }

            if (array_key_exists('address', $updateData)) {
                $address = trim((string) ($updateData['address'] ?? ''));
                $updateData['address'] = $address !== '' ? $address : null;
            }

            if (array_key_exists('is_active', $updateData)) {
                if (!in_array((string) $updateData['is_active'], ['0', '1'], true)) {
                    return $this->errorResponse('Status supplier tidak valid.', 422);
                }

                $updateData['is_active'] = (int) $updateData['is_active'];
            }

            $updateData['updated_by'] = auth()->id();

            if (!$this->supplierModel->update($id, $updateData)) {
                return $this->errorResponse(
                    $this->supplierModel->errors() ?: 'Gagal memperbarui supplier.',
                    422
                );
            }

            return $this->successResponse(
                $this->supplierModel->find($id),
                'Supplier berhasil diperbarui.'
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[SupplierApiController::update] ' . $e->getMessage()
            );

            return $this->errorResponse('Gagal memperbarui supplier.', 500);
        }
    }

    /**
     * DELETE /supplier-api/delete/{id}
     */
    public function delete($id = null)
    {
        try {
            $id = (int) $id;

            if ($id <= 0) {
                return $this->errorResponse('ID supplier tidak valid.', 400);
            }

            $supplier = $this->supplierModel->find($id);

            if (!$supplier) {
                return $this->errorResponse('Supplier tidak ditemukan.', 404);
            }

            if (!$this->supplierModel->delete($id)) {
                return $this->errorResponse('Gagal menghapus supplier.', 500);
            }

            return $this->successResponse(
                null,
                'Supplier berhasil dihapus.'
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[SupplierApiController::delete] ' . $e->getMessage()
            );

            return $this->errorResponse('Gagal menghapus supplier.', 500);
        }
    }

    private function getRequestData(): ?array
    {
        try {
            $data = $this->request->getJSON(true);
            return is_array($data) ? $data : null;
        } catch (Throwable) {
            return null;
        }
    }
}
