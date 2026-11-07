<?php

namespace Modules\Backend\Models;

use CodeIgniter\Model;

class SettingAppModel extends Model
{
    protected $table         = 'tbl_settings';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'name', 'input_type', 'options', 'is_type', 'show_editor',
        'label', 'value', 'class', 'help_text', 'validation',
        'sort_order', 'created_at', 'updated_at', 'updated_by',
        'type', 'deleted'
    ];
    protected $useTimestamps = false;

    public function getActiveSettings($type = 'general')
    {
        return $this->where('deleted', 0)
                    ->where('type', $type)
                    ->orderBy('sort_order', 'ASC')
                    ->findAll();
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
