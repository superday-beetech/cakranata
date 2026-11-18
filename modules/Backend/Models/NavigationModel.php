<?php

namespace Modules\Backend\Models;

use CodeIgniter\Model;

class NavigationModel extends Model
{
    protected $table            = 'menus';
    protected $primaryKey       = 'id';
    protected $allowedFields    = ['title', 'slug', 'url', 'icon', 'parent_id', 'position', 'section', 'sequence', 'is_active'];
    protected $useTimestamps     = true;
    // protected $casts            = [];

    // Ambil menu berdasarkan posisi DAN section (frontend / backend)
    public function getMenusWithGroupsByPositionAndSection(string $position, string $section): array
    {
        $menus = $this->where('position', $position)
            ->where('section', $section)
            ->orderBy('sequence', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        if ($menus === []) {
            return [];
        }

        $db = $this->db;
        $menuIds = array_map('intval', array_column($menus, 'id'));

        $relations = $db->table('menu_groups')
            ->whereIn('menu_id', $menuIds)
            ->get()
            ->getResultArray();

        if ($relations === []) {
            foreach ($menus as &$menu) {
                $menu['selected_groups'] = [];
            }
            unset($menu);

            return $menus;
        }

        // Data lama mungkin menyimpan group_id, sedangkan data terbaru
        // menggunakan group_name. Normalisasi keduanya menjadi nama group.
        $legacyGroupIds = [];

        foreach ($relations as $relation) {
            $groupName = trim((string) ($relation['group_name'] ?? ''));

            if ($groupName === '' && isset($relation['group_id'])) {
                $groupId = (int) $relation['group_id'];

                if ($groupId > 0) {
                    $legacyGroupIds[$groupId] = $groupId;
                }
            }
        }

        $groupNamesById = [];

        if ($legacyGroupIds !== []) {
            $groupRows = $db->table('auth_groups')
                ->select('id, name')
                ->whereIn('id', array_values($legacyGroupIds))
                ->get()
                ->getResultArray();

            foreach ($groupRows as $group) {
                $groupNamesById[(int) $group['id']] = (string) $group['name'];
            }
        }

        $groupedRelations = [];

        foreach ($relations as $relation) {
            $menuId = (int) $relation['menu_id'];
            $groupName = trim((string) ($relation['group_name'] ?? ''));

            if ($groupName === '' && isset($relation['group_id'])) {
                $groupName = $groupNamesById[(int) $relation['group_id']] ?? '';
            }

            if ($groupName !== '') {
                $groupedRelations[$menuId][$groupName] = $groupName;
            }
        }

        foreach ($menus as &$menu) {
            $menu['selected_groups'] = array_values(
                $groupedRelations[(int) $menu['id']] ?? []
            );
        }
        unset($menu);

        return $menus;
    }
    // Logika rekursif dipindah ke model agar controller bersih
    /**
     * Simpan seluruh struktur menu dalam satu transaksi.
     * Parent dan sequence diturunkan dari struktur nested, bukan dari nilai
     * parent_id/sequence yang dikirim klien.
     */
    public function saveHierarchy(array $items): void
    {
        if ($items === [] || ! array_is_list($items)) {
            throw new \InvalidArgumentException('Struktur menu tidak valid atau kosong.');
        }

        $rows = [];
        $seen = [];

        $walk = function (array $nodes, int $parentId = 0, int $depth = 0) use (&$walk, &$rows, &$seen): void {
            if ($depth > 20 || ! array_is_list($nodes)) {
                throw new \InvalidArgumentException('Kedalaman atau format hierarki menu tidak valid.');
            }

            foreach ($nodes as $index => $node) {
                if (
                    ! is_array($node)
                    || ! isset($node['id'])
                    || filter_var($node['id'], FILTER_VALIDATE_INT) === false
                ) {
                    throw new \InvalidArgumentException('ID menu pada hierarki tidak valid.');
                }

                $id = (int) $node['id'];

                if ($id < 1 || isset($seen[$id])) {
                    throw new \InvalidArgumentException('Menu duplikat atau ID menu tidak valid.');
                }

                $children = $node['children'] ?? [];

                if (! is_array($children) || ! array_is_list($children)) {
                    throw new \InvalidArgumentException('Daftar submenu tidak valid.');
                }

                $seen[$id] = true;
                $rows[] = [
                    'id'        => $id,
                    'parent_id' => $parentId,
                    'sequence'  => $index + 1,
                ];

                $walk($children, $id, $depth + 1);
            }
        };

        $walk($items);
        $ids = array_column($rows, 'id');

        $db = $this->db;
        $db->transBegin();

        try {
            // Validasi dilakukan dalam transaksi agar scope yang diperiksa
            // konsisten dengan data yang akan diperbarui.
            $existing = $db->table($this->table)
                ->select('id, position, section')
                ->whereIn('id', $ids)
                ->get()
                ->getResultArray();

            if (count($existing) !== count($ids)) {
                throw new \InvalidArgumentException('Sebagian menu tidak ditemukan.');
            }

            $position = (string) $existing[0]['position'];
            $section  = (string) $existing[0]['section'];

            foreach ($existing as $menu) {
                if ($menu['position'] !== $position || $menu['section'] !== $section) {
                    throw new \InvalidArgumentException('Hierarki tidak boleh mencampur posisi atau section menu.');
                }
            }

            $scopeRows = $db->table($this->table)
                ->select('id')
                ->where('position', $position)
                ->where('section', $section)
                ->get()
                ->getResultArray();

            $scopeIds = array_map('intval', array_column($scopeRows, 'id'));
            $submittedIds = $ids;

            sort($scopeIds);
            sort($submittedIds);

            if ($scopeIds !== $submittedIds) {
                throw new \InvalidArgumentException('Struktur menu tidak lengkap. Muat ulang sebelum menyimpan.');
            }

            foreach ($rows as $row) {
                $updated = $db->table($this->table)
                    ->where('id', $row['id'])
                    ->where('position', $position)
                    ->where('section', $section)
                    ->update([
                        'parent_id' => $row['parent_id'],
                        'sequence'  => $row['sequence'],
                    ]);

                if ($updated === false) {
                    throw new \RuntimeException('Gagal memperbarui hierarki menu.');
                }
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Transaksi hierarki menu gagal.');
            }

            if ($db->transCommit() === false) {
                throw new \RuntimeException('Transaksi hierarki menu gagal di-commit.');
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }
    public function getMenuForEdit(int $id)
    {
        // Ambil data menu utama
        $menu = $this->find($id);
        if (!$menu) {
            return null;
        }

        // Ambil ID group role yang terhubung dari tabel perantara menu_groups
        $db = \Config\Database::connect();
        $assignedGroups = $db->table('menu_groups')
            ->where('menu_id', $id)
            ->get()
            ->getResultArray();

        // Ubah menjadi array pipih berisi ID group saja, misal: [1, 3]
        $menu['selected_groups'] = array_column($assignedGroups, 'group_id');

        return $menu;
    }

    // Mengambil semua menu berdasarkan posisi beserta array group roles-nya sekaligus
    public function getMenusWithGroupsByPosition(string $position)
    {
        // 1. Ambil semua menu berdasarkan posisi
        $menus = $this->where('position', $position)
            ->orderBy('sequence', 'ASC')
            ->findAll();

        if (empty($menus)) {
            return [];
        }

        $db = \Config\Database::connect();

        // 2. Ambil seluruh relasi menu_groups untuk posisi ini sekaligus agar efisien
        $menuIds = array_column($menus, 'id');
        $allRelations = $db->table('menu_groups')
            ->whereIn('menu_id', $menuIds)
            ->get()
            ->getResultArray();

        // 3. Petakan group_name / group_id ke masing-masing menu ID
        $groupedRelations = [];
        foreach ($allRelations as $rel) {
            // Sesuaikan apakah kolom di database Anda menyimpan 'group_name' atau 'group_id'
            $groupedRelations[$rel['menu_id']][] = $rel['group_name'] ?? $rel['group_id'];
        }

        // 4. Masukkan selected_groups ke dalam setiap item menu
        foreach ($menus as &$menu) {
            $menu['selected_groups'] = $groupedRelations[$menu['id']] ?? [];
        }

        return $menus;
    }

    // Ambil juga semua daftar group role yang tersedia di sistem untuk ditampilkan di checkbox
    public function getAllGroups()
    {
        $db = \Config\Database::connect();
        return $db->table('auth_groups')->get()->getResultArray();
    }

    public function getMenuWithGroups(int $id)
    {
        $menu = $this->find($id);
        if (!$menu) {
            return null;
        }

        // Ambil relasi group roles dari tabel perantara menu_groups
        $db = \Config\Database::connect();
        $assignedGroups = $db->table('menu_groups')
            ->where('menu_id', $id)
            ->get()
            ->getResultArray();

        $menu['selected_groups'] = array_column($assignedGroups, 'group_id');
        return $menu;
    }

    /**
     * Menyimpan data menu beserta sinkronisasi hak akses group roles
     */
    public function saveMenuWithGroups(int $id, array $data, ?array $groups)
    {
        $db = \Config\Database::connect();
        $db->transStart();

        try {
            if ($id > 0) {
                // Update Menu Utama
                $this->update($id, $data);
            } else {
                // Insert Menu Baru
                $id = $this->insert($data, true);
            }

            // Sinkronisasi Tabel Perantara menu_groups (Hapus lalu masukkan yang baru)
            $db->table('menu_groups')->where('menu_id', $id)->delete();

            if (!empty($groups) && is_array($groups)) {
                $batchData = [];
                foreach ($groups as $groupId) {
                    $batchData[] = [
                        'menu_id'  => $id,
                        'group_id' => (int)$groupId
                    ];
                }
                $db->table('menu_groups')->insertBatch($batchData);
            }

            $db->transComplete();
            return $db->transStatus();
        } catch (\Exception $e) {
            $db->transRollback();
            throw $e;
        }
    }
    // Sinkronisasi Group Akses (Mencegah akses antar group/role)
    public function syncGroups(int $menuId, array $groups): void
    {
        if ($menuId < 1) {
            throw new \InvalidArgumentException('ID menu tidak valid.');
        }

        $groupNames = [];

        foreach ($groups as $group) {
            if (! is_string($group)) {
                throw new \InvalidArgumentException('Nama group akses tidak valid.');
            }

            $groupName = trim($group);

            if ($groupName !== '') {
                $groupNames[$groupName] = $groupName;
            }
        }

        $db = $this->db;
        $builder = $db->table('menu_groups');

        if ($builder->where('menu_id', $menuId)->delete() === false) {
            throw new \RuntimeException('Gagal menghapus relasi group menu.');
        }

        if ($groupNames === []) {
            return;
        }

        $batch = [];

        foreach ($groupNames as $groupName) {
            $batch[] = [
                'menu_id'    => $menuId,
                'group_name' => $groupName,
            ];
        }

        if ($db->table('menu_groups')->insertBatch($batch) === false) {
            throw new \RuntimeException('Gagal menyimpan relasi group menu.');
        }
    }

    // Mengambil menu berdasarkan posisi (sidebar/topbar) dan hak akses group
    public function getMenusByGroupAndPosition(array $userGroups, string $position)
    {
        return $this->select('menus.*')
            ->join('menu_groups', 'menu_groups.menu_id = menus.id')
            ->where('menus.position', $position)
            ->where('menus.is_active', 1)
            ->whereIn('menu_groups.group_name', $userGroups)
            ->orderBy('menus.sequence', 'ASC')
            ->findAll();
    }

    /**
     * Data untuk modal sinkronisasi menu.
     * Kandidat hanya berasal dari route_registry_menus.
     */
    public function getSyncMenuPreview(): array
    {
        $db = \Config\Database::connect();

        $menus = $db->table('menus')
            ->select('id, title, slug, url, position, section')
            ->orderBy('position')
            ->orderBy('sequence')
            ->get()
            ->getResultArray();

        $candidates = $db->table('route_registry_menus')
            ->select('id, route_id, title, url')
            ->orderBy('title')
            ->get()
            ->getResultArray();

        // Frontend Navigation mengonsumsi daftar nama group (string[]).
        $groups = array_values(array_filter(
            array_map(
                static fn(array $group): string => (string) ($group['name'] ?? ''),
                $db->table('auth_groups')
                    ->select('name')
                    ->orderBy('name')
                    ->get()
                    ->getResultArray()
            ),
            static fn(string $name): bool => $name !== ''
        ));

        // Normalisasi URL untuk mendeteksi duplikasi.
        $existingUrls = [];

        foreach ($menus as $menu) {
            $url = trim((string) ($menu['url'] ?? ''), '/');

            if ($url !== '') {
                $existingUrls[strtolower($url)] = true;
            }
        }

        foreach ($candidates as &$candidate) {
            $url = trim((string) ($candidate['url'] ?? ''), '/');

            $candidate['exists'] = $url !== ''
                && isset($existingUrls[strtolower($url)]);
        }
        unset($candidate);

        return [
            'menus'      => $menus,
            'candidates' => $candidates,
            'groups'     => $groups,
        ];
    }

    /**
     * Menambahkan kandidat terpilih ke menus dan menu_groups.
     *
     * Format items:
     * [
     *   [
     *     'route_menu_id' => 10,
     *     'position'      => 'sidebar',
     *     'groups'        => ['administrator', 'operator'],
     *   ],
     * ]
     */
    public function syncMenu(array $items, string $section = 'backend'): array
    {
        $db = \Config\Database::connect();

        if (! array_is_list($items) || $items === []) {
            throw new \InvalidArgumentException('Pilih minimal satu kandidat menu dengan format yang valid.');
        }

        if (! in_array($section, ['backend', 'frontend'], true)) {
            throw new \InvalidArgumentException('Section menu tidak valid.');
        }

        $allowedPositions = ['sidebar', 'topbar', 'down', 'quick_links', 'apps'];

        $inserted = [];
        $skipped  = [];

        $db->transBegin();

        try {
            // Ambil URL yang sudah ada agar kandidat duplikat dilewati.
            $existingUrls = [];

            foreach (
                $db->table('menus')->select('url')->get()->getResultArray()
                as $row
            ) {
                $url = strtolower(trim((string) ($row['url'] ?? ''), '/'));

                if ($url !== '') {
                    $existingUrls[$url] = true;
                }
            }

            $validGroupNames = array_column(
                $db->table('auth_groups')->select('name')->get()->getResultArray(),
                'name'
            );

            $sequences = [];

            $seenCandidates = [];

            foreach ($items as $item) {
                if (! is_array($item)) {
                    throw new \InvalidArgumentException('Format kandidat menu tidak valid.');
                }

                $routeMenuId = filter_var($item['route_menu_id'] ?? null, FILTER_VALIDATE_INT);
                $position    = (string) ($item['position'] ?? 'sidebar');
                $groupNamesInput = $item['groups'] ?? [];

                if ($routeMenuId === false || (int) $routeMenuId < 1 || isset($seenCandidates[(int) $routeMenuId])) {
                    throw new \InvalidArgumentException('ID kandidat menu tidak valid atau duplikat.');
                }

                $routeMenuId = (int) $routeMenuId;
                $seenCandidates[$routeMenuId] = true;

                if (! in_array($position, $allowedPositions, true)) {
                    throw new \InvalidArgumentException('Position menu tidak valid.');
                }

                if (! is_array($groupNamesInput) || ! array_is_list($groupNamesInput)) {
                    throw new \InvalidArgumentException('Format grup akses tidak valid.');
                }

                $groupNames = array_values(array_unique(array_filter(
                    array_map(
                        static function ($name): string {
                            if (! is_string($name)) {
                                throw new \InvalidArgumentException('Nama grup akses tidak valid.');
                            }

                            return trim($name);
                        },
                        $groupNamesInput
                    ),
                    static fn(string $name): bool => $name !== ''
                )));
                foreach ($groupNames as $groupName) {
                    if (! in_array($groupName, $validGroupNames, true)) {
                        throw new \InvalidArgumentException(
                            "Grup akses '{$groupName}' tidak valid."
                        );
                    }
                }

                // Selalu ambil data asli dari route_registry_menus.
                $candidate = $db->table('route_registry_menus')
                    ->where('id', $routeMenuId)
                    ->get()
                    ->getRowArray();

                if (! $candidate) {
                    throw new \RuntimeException(
                        "Kandidat menu ID {$routeMenuId} tidak ditemukan."
                    );
                }

                $title = trim((string) $candidate['title']);
                $url   = trim((string) ($candidate['url'] ?? ''));

                if ($title === '' || $url === '') {
                    throw new \RuntimeException(
                        "Judul atau URL kandidat ID {$routeMenuId} kosong."
                    );
                }

                $urlKey = strtolower(trim($url, '/'));

                if (isset($existingUrls[$urlKey])) {
                    $skipped[] = [
                        'title'  => $title,
                        'url'    => $url,
                        'reason' => 'URL sudah terdaftar.',
                    ];
                    continue;
                }

                // Slug unik agar tidak bentrok dengan menu yang sudah ada.
                $baseSlug = url_title($title, '-', true);

                if ($baseSlug === '') {
                    $baseSlug = 'menu-' . $routeMenuId;
                }

                $slug = $baseSlug;
                $suffix = 2;

                while (
                    $db->table('menus')
                    ->where('slug', $slug)
                    ->countAllResults() > 0
                ) {
                    $slug = $baseSlug . '-' . $suffix++;
                }

                $sequenceKey = $section . ':' . $position;

                if (! isset($sequences[$sequenceKey])) {
                    $row = $db->table('menus')
                        ->selectMax('sequence', 'max_sequence')
                        ->where('section', $section)
                        ->where('position', $position)
                        ->get()
                        ->getRowArray();

                    $sequences[$sequenceKey] = (int) ($row['max_sequence'] ?? 0);
                }

                $sequences[$sequenceKey]++;

                $menuData = [
                    'title'     => $title,
                    'slug'      => $slug,
                    'url'       => $url,
                    'icon'      => 'bi bi-circle',
                    'parent_id' => 0,
                    'position'  => $position,
                    'section'   => $section,
                    'sequence'  => $sequences[$sequenceKey],
                    'is_active' => 1,
                ];

                // Menggunakan Model insert agar timestamps CI4 tetap ditangani.
                $menuId = $this->insert($menuData, true);

                if (! $menuId) {
                    throw new \RuntimeException(
                        'Gagal menyimpan menu: ' .
                            implode(', ', $this->errors())
                    );
                }

                if (! empty($groupNames)) {
                    $relations = [];

                    foreach ($groupNames as $groupName) {
                        $relations[] = [
                            'menu_id'    => (int) $menuId,
                            'group_name' => $groupName,
                        ];
                    }

                    $db->table('menu_groups')->insertBatch($relations);
                }

                $existingUrls[$urlKey] = true;

                $inserted[] = [
                    'id'    => (int) $menuId,
                    'title' => $title,
                    'url'   => $url,
                ];
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Transaksi sinkronisasi menu gagal.');
            }

            if ($db->transCommit() === false) {
                throw new \RuntimeException('Transaksi sinkronisasi menu gagal di-commit.');
            }

            return [
                'inserted' => $inserted,
                'skipped'  => $skipped,
            ];
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }
}
