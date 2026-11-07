<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Master;

use Modules\Pharmacy\Models\PharmacyModel;

class CategoryModel extends PharmacyModel
{
    protected $table            = 'categories';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;

    protected $allowedFields = [
        'name',
        'description',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'name' => 'required|min_length[2]|max_length[100]',
        'description' => 'permit_empty|max_length[1000]',
        'is_active' => 'permit_empty|in_list[0,1]',
    ];

    protected $validationMessages = [
        'name' => [
            'required' => 'Nama kategori wajib diisi.',
            'min_length' => 'Nama kategori minimal 2 karakter.',
            'max_length' => 'Nama kategori maksimal 100 karakter.',
        ],
        'description' => [
            'max_length' => 'Deskripsi maksimal 1000 karakter.',
        ],
        'is_active' => [
            'in_list' => 'Status aktif harus bernilai 0 atau 1.',
        ],
    ];

    protected $skipValidation = false;
}
