<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use CodeIgniter\Model;

final class StorageFileModel extends Model
{
    protected $table            = 'storage_files';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $allowedFields = [
        'disk',
        'storage_key',
        'original_name',
        'mime_type',
        'extension',
        'size',
        'checksum',
        'organization_id',
        'uploaded_by',
        'status',
    ];

    protected $useTimestamps = true;
    protected $dateFormat     = 'datetime';
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';

    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $protectFields = true;
}
