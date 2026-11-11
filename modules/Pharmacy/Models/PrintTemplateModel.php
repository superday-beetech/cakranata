<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models;

use CodeIgniter\Model;

class PrintTemplateModel extends Model
{
    protected $DBGroup = 'pharmacy';

    protected $table            = 'print_templates';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'name',
        'type',
        'paper_type',
        'paper_config',
        'design_json',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validasi dasar; JSON divalidasi di controller.
    protected $validationRules = [
        'name'       => 'required|max_length[100]',
        'type'       => 'required|max_length[50]',
        'paper_type' => 'required|in_list[a4,a5,80mm,custom]',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    protected $beforeInsert = ['encodeJsonFields'];
    protected $beforeUpdate = ['encodeJsonFields'];

    /**
     * Pastikan paper_config & design_json tersimpan sebagai string JSON.
     * Jika sudah string, biarkan (dari DB / sudah di-encode controller).
     */
    protected function encodeJsonFields(array $data): array
    {
        foreach (['paper_config', 'design_json'] as $field) {
            if (! array_key_exists($field, $data['data'])) {
                continue;
            }

            $value = $data['data'][$field];

            if (is_array($value) || is_object($value)) {
                $data['data'][$field] = json_encode(
                    $value,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            }
        }

        return $data;
    }
}
