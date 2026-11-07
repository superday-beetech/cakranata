<?php

declare(strict_types=1);

namespace Modules\Organization\Models;

use CodeIgniter\Model;

class OrganizationUnitModel extends Model
{
    protected $table            = 'core_organization_units';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';

    protected $allowedFields = [
        'organization_id',
        'parent_id',
        'code',
        'name',
        'unit_type',
        'status',
        'metadata',
    ];

    protected $validationRules = [
        'organization_id' => 'required|is_natural_no_zero',
        'code'            => 'required|max_length[50]',
        'name'            => 'required|max_length[150]',
        'unit_type'       => 'required|max_length[50]',
        'status'          => 'required|in_list[active,inactive]',
    ];
}
