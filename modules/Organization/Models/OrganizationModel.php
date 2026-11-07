<?php

declare(strict_types=1);

namespace Modules\Organization\Models;

use CodeIgniter\Model;

class OrganizationModel extends Model
{
    protected $table            = 'core_organizations';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';

    protected $allowedFields = [
        'code',
        'slug',
        'name',
        'legal_name',
        'organization_type',
        'status',
        'timezone',
        'currency',
        'settings',
    ];

    protected $validationRules = [
        'code'              => 'required|max_length[50]',
        'slug'              => 'required|max_length[100]',
        'name'              => 'required|max_length[150]',
        'organization_type' => 'required|max_length[50]',
        'status'            => 'required|in_list[active,inactive,suspended]',
        'timezone'          => 'required|max_length[64]',
        'currency'          => 'required|exact_length[3]',
    ];
}
