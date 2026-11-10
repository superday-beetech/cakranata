<?php

namespace App\Models;

use CodeIgniter\Model;

class SettingModel extends Model
{
    protected $table         = 'tbl_settings';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'name',
        'input_type',
        'options',
        'is_type',
        'show_editor',
        'label',
        'value',
        'class',
        'help_text',
        'validation',
        'sort_order',
        'created_at',
        'updated_at',
        'updated_by',
        'type',
        'deleted'
    ];
    protected $useTimestamps = false;

    public function getActiveSettings($type = false)
    {
        $this->where('deleted', 0);

        if ($type) {
            $this->where('type', $type);
        }

        return $this->orderBy('sort_order', 'ASC')->findAll();
    }

    public function setValue($name, $value)
    {
        $row = $this->where('name', $name)->first();
        if ($row) {
            return $this->update($row['id'], ['value' => $value]);
        }

        return false;
    }
}
