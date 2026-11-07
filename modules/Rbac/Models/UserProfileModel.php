<?php

declare(strict_types=1);

namespace Modules\Rbac\Models;

use CodeIgniter\Model;

class UserProfileModel extends Model
{
    protected $table            = 'user_profiles';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'user_id',
        'nama_lengkap',
        'nama_panggilan',
        'nomor_pegawai',
        'tanggal_lahir',
        'jenis_kelamin',
        'nomor_telepon_alternatif',
        'alamat',
        'foto_profil',
    ];

    public function findByUserId(int $userId): ?array
    {
        return $this->where('user_id', $userId)->first();
    }
}
