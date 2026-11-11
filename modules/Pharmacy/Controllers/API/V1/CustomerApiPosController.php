<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Controllers\API\V1;

use App\Core\ApiController;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\HTTP\ResponseInterface;

class CustomerApiPosController extends ApiController
{
    protected $db;
    protected $table = 'customers';

    public function __construct()
    {
        $this->db = \Config\Database::connect('pharmacy');
    }

    /**
     * GET /sales/customer-api
     *
     * Pencarian manual:
     * ?search=andi&page=1&per_page=15
     *
     * Pencarian persis untuk scanner:
     * ?search=MEMBER001&exact=1
     */
    public function index(): ResponseInterface
    {
        $search = trim((string) $this->request->getGet('search'));
        $exact  = $this->request->getGet('exact') === '1';

        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->getGet('per_page') ?? 15)));

        $isActive = $this->request->getGet('is_active');
        $type     = $this->request->getGet('customer_type');

        $builder = $this->db->table($this->table);

        $this->applyFilters($builder, $search, $exact, $isActive, $type);

        $total = $builder->countAllResults();

        $builder = $this->db->table($this->table);

        $this->applyFilters($builder, $search, $exact, $isActive, $type);

        $items = $builder
            ->orderBy('name', 'ASC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return $this->respond([
            'error'   => false,
            'message' => 'Data pelanggan berhasil dimuat.',
            'data'    => [
                'items' => $items,
                'pagination' => [
                    'current_page' => $page,
                    'per_page'     => $perPage,
                    'total'        => $total,
                    'total_pages'  => (int) ceil($total / $perPage),
                ],
            ],
        ]);
    }

    /**
     * GET /sales/customer-api/show/{id}
     */
    public function show($id = null): ResponseInterface
    {
        $customer = $this->db
            ->table($this->table)
            ->where('id', $id)
            ->get()
            ->getRowArray();

        if (! $customer) {
            return $this->respond([
                'error'   => true,
                'message' => 'Pelanggan tidak ditemukan.',
                'data'    => null,
            ], 404);
        }

        return $this->respond([
            'error'   => false,
            'message' => 'Data pelanggan berhasil dimuat.',
            'data'    => $customer,
        ]);
    }

    /**
     * Terapkan filter pencarian.
     */
    private function applyFilters(
        BaseBuilder $builder,
        string $search,
        bool $exact,
        mixed $isActive,
        mixed $type
    ): void {
        if ($search !== '') {
            $builder->groupStart();

            if ($exact) {
                $builder
                    ->where('code', $search)
                    ->orWhere('member_number', $search)
                    ->orWhere('barcode', $search)
                    ->orWhere('phone', $search);
            } else {
                $builder
                    ->like('name', $search)
                    ->orLike('code', $search)
                    ->orLike('member_number', $search)
                    ->orLike('barcode', $search)
                    ->orLike('phone', $search);
            }

            $builder->groupEnd();
        }

        if ($isActive !== null && $isActive !== '') {
            $builder->where('is_active', (int) $isActive);
        }

        if ($type !== null && $type !== '') {
            $builder->where('customer_type', $type);
        }
    }

    /**
     * Ambil field yang diizinkan.
     */
    private function filterPayload(array $payload): array
    {
        $allowed = [
            'code',
            'member_number',
            'barcode',
            'name',
            'customer_type',
            'phone',
            'email',
            'address',
            'is_active',
        ];

        $data = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $payload)) {
                $value = $payload[$field];

                $data[$field] = $value === '' ? null : $value;
            }
        }

        return $data;
    }

    /**
     * Periksa duplikasi kolom unik.
     */
    private function findDuplicate(array $data, ?int $exceptId = null): ?string
    {
        foreach (['code', 'member_number', 'barcode'] as $field) {
            if (! isset($data[$field]) || $data[$field] === '') {
                continue;
            }

            $builder = $this->db
                ->table($this->table)
                ->where($field, $data[$field]);

            if ($exceptId !== null) {
                $builder->where('id !=', $exceptId);
            }

            if ($builder->countAllResults() > 0) {
                return $field;
            }
        }

        return null;
    }
}
