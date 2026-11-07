<?php

declare(strict_types=1);

namespace Modules\Backend\Models;

use CodeIgniter\Model;

class EmailTemplateModel extends Model
{
    protected $table            = 'tbl_email_templates';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'code',
        'name',
        'category',
        'description',
        'channel',
        'subject',
        'body_html',
        'body_text',
        'variables',
        'from_name',
        'from_email',
        'reply_to',
        'version',
        'is_system',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'code'     => 'required|max_length[100]',
        'name'     => 'required|max_length[150]',
        'category' => 'required|max_length[50]',
        'channel'  => 'required|max_length[20]',
        'subject'  => 'permit_empty|max_length[255]',
        'version'  => 'required|is_natural_no_zero',
        'is_system' => 'required|in_list[0,1]',
        'is_active' => 'required|in_list[0,1]',
    ];

    protected $validationMessages = [
        'code' => [
            'required' => 'Kode template wajib diisi.',
            'max_length' => 'Kode template maksimal 100 karakter.',
        ],
        'name' => [
            'required' => 'Nama template wajib diisi.',
            'max_length' => 'Nama template maksimal 150 karakter.',
        ],
    ];
}
