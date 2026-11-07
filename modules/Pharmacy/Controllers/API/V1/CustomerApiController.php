<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Controllers\API\V1;

use App\Core\ApiController;
use Modules\Pharmacy\Models\Master\CustomerModel;
use Throwable;

class CustomerApiController extends ApiController
{
    private CustomerModel $customerModel;

    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);

        $this->customerModel = new CustomerModel();
    }

    /**
     * GET /customer-api
     */
    public function index()
    {
        try {
            $page = max(1, (int) ($this->request->getGet('page') ?? 1));

            $perPage = (int) ($this->request->getGet('per_page') ?? 10);
            $perPage = max(1, min($perPage, 100));

            $search = trim((string) ($this->request->getGet('search') ?? ''));

            $customerType = trim(
                (string) ($this->request->getGet('customer_type') ?? '')
            );

            $isActive = $this->request->getGet('is_active');

            if (
                $isActive !== null &&
                $isActive !== '' &&
                !in_array((string) $isActive, ['0', '1'], true)
            ) {
                return $this->errorResponse(
                    'Status customer tidak valid.',
                    400
                );
            }

            $applyFilters = static function ($builder) use (
                $search,
                $customerType,
                $isActive
            ): void {
                if ($search !== '') {
                    $builder->groupStart()
                        ->like('customers.code', $search)
                        ->orLike('customers.name', $search)
                        ->orLike('customers.phone', $search)
                        ->orLike('customers.member_number', $search)
                        ->orLike('customers.barcode', $search)
                        ->orLike('customers.email', $search)
                        ->groupEnd();
                }

                if ($customerType !== '') {
                    $builder->where(
                        'customers.customer_type',
                        $customerType
                    );
                }

                if ($isActive !== null && $isActive !== '') {
                    $builder->where(
                        'customers.is_active',
                        (int) $isActive
                    );
                }
            };

            // Hitung total data.
            $countBuilder = $this->customerModel->builder();

            $applyFilters($countBuilder);

            $total = $countBuilder->countAllResults();

            // Ambil data customer.
            $builder = $this->customerModel->builder();

            $applyFilters($builder);

            $items = $builder
                ->select('customers.*')
                ->orderBy('customers.name', 'ASC')
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
            ], 'Data customer berhasil diambil.');
        } catch (Throwable $e) {
            log_message(
                'error',
                '[CustomerApiController::index] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal mengambil data customer.',
                500
            );
        }
    }

    /**
     * GET /customer-api/show/{id}
     */
    public function show($id = null)
    {
        try {
            $id = (int) $id;

            if ($id <= 0) {
                return $this->errorResponse(
                    'ID customer tidak valid.',
                    400
                );
            }

            $customer = $this->customerModel->find($id);

            if (!$customer) {
                return $this->errorResponse(
                    'Customer tidak ditemukan.',
                    404
                );
            }

            return $this->successResponse(
                $customer,
                'Detail customer berhasil diambil.'
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[CustomerApiController::show] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal mengambil detail customer.',
                500
            );
        }
    }

    /**
     * POST /customer-api/create
     */
    public function create()
    {
        try {
            $data = $this->getRequestData();

            $code = strtoupper(trim((string) ($data['code'] ?? '')));
            $name = trim((string) ($data['name'] ?? ''));

            $customerType = trim(
                (string) ($data['customer_type'] ?? '')
            );

            $phone = trim((string) ($data['phone'] ?? ''));
            $email = trim((string) ($data['email'] ?? ''));
            $address = trim((string) ($data['address'] ?? ''));

            if ($code === '' || strlen($code) > 50) {
                return $this->errorResponse(
                    'Kode customer wajib diisi dan maksimal 50 karakter.',
                    422
                );
            }

            if ($name === '' || mb_strlen($name) > 150) {
                return $this->errorResponse(
                    'Nama customer wajib diisi dan maksimal 150 karakter.',
                    422
                );
            }

            if (strlen($customerType) > 30) {
                return $this->errorResponse(
                    'Jenis customer maksimal 30 karakter.',
                    422
                );
            }

            if (strlen($phone) > 30) {
                return $this->errorResponse(
                    'Nomor telepon maksimal 30 karakter.',
                    422
                );
            }

            if (
                strlen($email) > 150 ||
                ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
            ) {
                return $this->errorResponse(
                    'Format email tidak valid.',
                    422
                );
            }

            if ($this->customerModel->where('code', $code)->first()) {
                return $this->errorResponse(
                    'Kode customer sudah digunakan.',
                    409
                );
            }

            $isActive = $data['is_active'] ?? 1;

            if (!in_array((string) $isActive, ['0', '1'], true)) {
                return $this->errorResponse(
                    'Status customer tidak valid.',
                    422
                );
            }

            $insertData = [
                'code'          => $code,
                'name'          => $name,
                'customer_type' => $customerType !== '' ? $customerType : null,
                'phone'         => $phone !== '' ? $phone : null,
                'email'         => $email !== '' ? $email : null,
                'address'       => $address !== '' ? $address : null,
                'is_active'     => (int) $isActive,
                'created_by'    => auth()->id(),
            ];

            if (!$this->customerModel->insert($insertData)) {
                return $this->errorResponse(
                    $this->customerModel->errors()
                        ?: 'Gagal menyimpan customer.',
                    422
                );
            }

            $id = (int) $this->customerModel->getInsertID();

            return $this->successResponse(
                $this->customerModel->find($id),
                'Customer berhasil ditambahkan.',
                201
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[CustomerApiController::create] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal menambahkan customer.',
                500
            );
        }
    }

    /**
     * PUT /customer-api/update/{id}
     */
    public function update($id = null)
    {
        try {
            $id = (int) $id;

            if ($id <= 0) {
                return $this->errorResponse(
                    'ID customer tidak valid.',
                    400
                );
            }

            $customer = $this->customerModel->find($id);

            if (!$customer) {
                return $this->errorResponse(
                    'Customer tidak ditemukan.',
                    404
                );
            }

            $data = $this->getRequestData();

            if (empty($data)) {
                return $this->errorResponse(
                    'Tidak ada data yang diperbarui.',
                    400
                );
            }

            $allowedFields = [
                'code',
                'name',
                'customer_type',
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
                return $this->errorResponse(
                    'Tidak ada field yang valid.',
                    400
                );
            }

            if (array_key_exists('code', $updateData)) {
                $code = strtoupper(trim((string) $updateData['code']));

                if ($code === '' || strlen($code) > 50) {
                    return $this->errorResponse(
                        'Kode customer wajib diisi dan maksimal 50 karakter.',
                        422
                    );
                }

                $duplicate = $this->customerModel
                    ->where('code', $code)
                    ->where('id !=', $id)
                    ->first();

                if ($duplicate) {
                    return $this->errorResponse(
                        'Kode customer sudah digunakan.',
                        409
                    );
                }

                $updateData['code'] = $code;
            }

            if (array_key_exists('name', $updateData)) {
                $name = trim((string) $updateData['name']);

                if ($name === '' || mb_strlen($name) > 150) {
                    return $this->errorResponse(
                        'Nama customer wajib diisi dan maksimal 150 karakter.',
                        422
                    );
                }

                $updateData['name'] = $name;
            }

            if (array_key_exists('customer_type', $updateData)) {
                $type = trim((string) ($updateData['customer_type'] ?? ''));

                if (strlen($type) > 30) {
                    return $this->errorResponse(
                        'Jenis customer maksimal 30 karakter.',
                        422
                    );
                }

                $updateData['customer_type'] = $type !== '' ? $type : null;
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
                    return $this->errorResponse(
                        'Format email tidak valid.',
                        422
                    );
                }

                $updateData['email'] = $email !== '' ? $email : null;
            }

            if (array_key_exists('address', $updateData)) {
                $address = trim((string) ($updateData['address'] ?? ''));

                $updateData['address'] = $address !== '' ? $address : null;
            }

            if (array_key_exists('is_active', $updateData)) {
                if (!in_array((string) $updateData['is_active'], ['0', '1'], true)) {
                    return $this->errorResponse(
                        'Status customer tidak valid.',
                        422
                    );
                }

                $updateData['is_active'] = (int) $updateData['is_active'];
            }

            $updateData['updated_by'] = auth()->id();

            if (!$this->customerModel->update($id, $updateData)) {
                return $this->errorResponse(
                    $this->customerModel->errors()
                        ?: 'Gagal memperbarui customer.',
                    422
                );
            }

            return $this->successResponse(
                $this->customerModel->find($id),
                'Customer berhasil diperbarui.'
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[CustomerApiController::update] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal memperbarui customer.',
                500
            );
        }
    }

    /**
     * DELETE /customer-api/delete/{id}
     */
    public function delete($id = null)
    {
        try {
            $id = (int) $id;

            if ($id <= 0) {
                return $this->errorResponse(
                    'ID customer tidak valid.',
                    400
                );
            }

            $customer = $this->customerModel->find($id);

            if (!$customer) {
                return $this->errorResponse(
                    'Customer tidak ditemukan.',
                    404
                );
            }

            if (!$this->customerModel->delete($id)) {
                return $this->errorResponse(
                    'Gagal menghapus customer.',
                    500
                );
            }

            return $this->successResponse(
                null,
                'Customer berhasil dihapus.'
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[CustomerApiController::delete] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal menghapus customer.',
                500
            );
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
