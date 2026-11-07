<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Controllers\API\V1;

use App\Core\ApiController;
use Modules\Pharmacy\Models\Master\CustomerModel;
use Throwable;

class HomesController extends ApiController
{
    private CustomerModel $customerModel;

    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);
        $this->customerModel = new CustomerModel();
    }

    public function index()
    {
        try {
            $builder = $this->customerModel;

            $search = trim((string) $this->request->getGet('search'));

            if ($search !== '') {
                $builder->groupStart()
                    ->like('code', $search)
                    ->orLike('name', $search)
                    ->orLike('phone', $search)
                    ->orLike('email', $search)
                    ->groupEnd();
            }

            if ($this->request->getGet('customer_type')) {
                $builder->where(
                    'customer_type',
                    $this->request->getGet('customer_type')
                );
            }

            if ($this->request->getGet('is_active') !== null) {
                $builder->where(
                    'is_active',
                    (int) $this->request->getGet('is_active')
                );
            }

            $data = $builder
                ->orderBy('name', 'ASC')
                ->findAll();

            return $this->successResponse($data, 'Data pelanggan berhasil diambil.');
        } catch (Throwable $e) {
            log_message('error', '[CustomerApiController::index] ' . $e->getMessage());
            return $this->errorResponse('Gagal mengambil data pelanggan.', 500);
        }
    }

    public function show($id = null)
    {
        try {
            $id = (int) $id;

            if ($id <= 0) {
                return $this->errorResponse('ID pelanggan tidak valid.', 400);
            }

            $customer = $this->customerModel->find($id);

            if (!$customer) {
                return $this->errorResponse('Pelanggan tidak ditemukan.', 404);
            }

            return $this->successResponse($customer, 'Detail pelanggan berhasil diambil.');
        } catch (Throwable $e) {
            log_message('error', '[CustomerApiController::show] ' . $e->getMessage());
            return $this->errorResponse('Gagal mengambil detail pelanggan.', 500);
        }
    }

    public function create()
    {
        try {
            $data = $this->request->getJSON(true);

            if (!is_array($data)) {
                return $this->errorResponse('Format request tidak valid.', 400);
            }

            if (!$this->validateData($data, [
                'code' => 'required|min_length[1]|max_length[50]',
                'name' => 'required|min_length[2]|max_length[150]',
                'customer_type' => 'required|min_length[1]|max_length[50]',
                'phone' => 'permit_empty|max_length[30]',
                'email' => 'permit_empty|valid_email|max_length[150]',
                'address' => 'permit_empty|max_length[1000]',
                'is_active' => 'permit_empty|in_list[0,1]',
            ])) {
                return $this->errorResponse(
                    'Validasi pelanggan gagal.',
                    422,
                    $this->validator->getErrors()
                );
            }

            $code = trim($data['code']);

            if ($this->customerModel->where('code', $code)->first()) {
                return $this->errorResponse('Kode pelanggan sudah digunakan.', 409);
            }

            $userId = (int) auth()->id();

            $id = $this->customerModel->insert([
                'code' => $code,
                'name' => trim($data['name']),
                'customer_type' => trim($data['customer_type']),
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'is_active' => isset($data['is_active'])
                    ? (int) $data['is_active']
                    : 1,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            if (!$id) {
                return $this->errorResponse(
                    'Gagal menyimpan pelanggan.',
                    500,
                    $this->customerModel->errors()
                );
            }

            return $this->successResponse(
                $this->customerModel->find($id),
                'Pelanggan berhasil ditambahkan.',
                201
            );
        } catch (Throwable $e) {
            log_message('error', '[CustomerApiController::create] ' . $e->getMessage());
            return $this->errorResponse('Terjadi kesalahan saat menambahkan pelanggan.', 500);
        }
    }

    public function update($id = null)
    {
        try {
            $id = (int) $id;

            if ($id <= 0) {
                return $this->errorResponse('ID pelanggan tidak valid.', 400);
            }

            if (!$this->customerModel->find($id)) {
                return $this->errorResponse('Pelanggan tidak ditemukan.', 404);
            }

            $data = $this->request->getJSON(true);

            if (!is_array($data) || !$data) {
                return $this->errorResponse('Data perubahan wajib diisi.', 400);
            }

            if (!$this->validateData($data, [
                'code' => 'permit_empty|min_length[1]|max_length[50]',
                'name' => 'permit_empty|min_length[2]|max_length[150]',
                'customer_type' => 'permit_empty|min_length[1]|max_length[50]',
                'phone' => 'permit_empty|max_length[30]',
                'email' => 'permit_empty|valid_email|max_length[150]',
                'address' => 'permit_empty|max_length[1000]',
                'is_active' => 'permit_empty|in_list[0,1]',
            ])) {
                return $this->errorResponse(
                    'Validasi perubahan pelanggan gagal.',
                    422,
                    $this->validator->getErrors()
                );
            }

            $update = [];

            foreach (
                [
                    'code',
                    'name',
                    'customer_type',
                    'phone',
                    'email',
                    'address',
                    'is_active',
                ] as $field
            ) {
                if (array_key_exists($field, $data)) {
                    $update[$field] = $data[$field];
                }
            }

            if (isset($update['code'])) {
                $update['code'] = trim((string) $update['code']);

                $duplicate = $this->customerModel
                    ->where('code', $update['code'])
                    ->where('id !=', $id)
                    ->first();

                if ($duplicate) {
                    return $this->errorResponse('Kode pelanggan sudah digunakan.', 409);
                }
            }

            foreach (['name', 'customer_type'] as $field) {
                if (isset($update[$field])) {
                    $update[$field] = trim((string) $update[$field]);
                }
            }

            if (!$update) {
                return $this->errorResponse('Tidak ada data yang diperbarui.', 400);
            }

            $update['updated_by'] = (int) auth()->id();

            if (!$this->customerModel->update($id, $update)) {
                return $this->errorResponse(
                    'Gagal memperbarui pelanggan.',
                    500,
                    $this->customerModel->errors()
                );
            }

            return $this->successResponse(
                $this->customerModel->find($id),
                'Pelanggan berhasil diperbarui.'
            );
        } catch (Throwable $e) {
            log_message('error', '[CustomerApiController::update] ' . $e->getMessage());
            return $this->errorResponse('Terjadi kesalahan saat memperbarui pelanggan.', 500);
        }
    }

    public function delete($id = null)
    {
        try {
            $id = (int) $id;

            if ($id <= 0) {
                return $this->errorResponse('ID pelanggan tidak valid.', 400);
            }

            if (!$this->customerModel->find($id)) {
                return $this->errorResponse('Pelanggan tidak ditemukan.', 404);
            }

            if (!$this->customerModel->delete($id)) {
                return $this->errorResponse('Gagal menghapus pelanggan.', 500);
            }

            return $this->successResponse(['id' => $id], 'Pelanggan berhasil dihapus.');
        } catch (Throwable $e) {
            log_message('error', '[CustomerApiController::delete] ' . $e->getMessage());
            return $this->errorResponse('Terjadi kesalahan saat menghapus pelanggan.', 500);
        }
    }
}
