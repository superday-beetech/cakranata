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
    public function getMenusWithGroupsByPositionAndSection(string $position, string $section)
    {
        // Pastikan .where('section', $section) ada di sini!
        $menus = $this->where('position', $position)
            ->where('section', $section)
            ->orderBy('sequence', 'ASC')
            ->findAll();

        if (empty($menus)) {
            return [];
        }

        $db = \Config\Database::connect();
        $menuIds = array_column($menus, 'id');

        $allRelations = $db->table('menu_groups')
            ->whereIn('menu_id', $menuIds)
            ->get()
            ->getResultArray();

        $groupedRelations = [];
        foreach ($allRelations as $rel) {
            $groupedRelations[$rel['menu_id']][] = $rel['group_name'] ?? $rel['group_id'];
        }

        foreach ($menus as &$menu) {
            $menu['selected_groups'] = $groupedRelations[$menu['id']] ?? [];
        }

        return $menus;
    }
    // Logika rekursif dipindah ke model agar controller bersih
    public function saveHierarchy(array $items, int $parentId = 0)
    {
        foreach ($items as $index => $item) {
            $id       = isset($item['id']) ? (int)$item['id'] : 0;
            $sequence = $index + 1;

            if ($id > 0) {
                // Update langsung melalui model
                $this->update($id, [
                    'parent_id' => $parentId,
                    'sequence'  => $sequence
                ]);

                // Proses children secara rekursif jika ada
                if (!empty($item['children']) && is_array($item['children'])) {
                    $this->saveHierarchy($item['children'], $id);
                }
            }
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
    public function syncGroups(int $menuId, array $groups)
    {
        $db = \Config\Database::connect();
        $db->table('menu_groups')->where('menu_id', $menuId)->delete();

        $batch = [];
        foreach ($groups as $group) {
            $batch[] = ['menu_id' => $menuId, 'group_name' => $group];
        }
        if (!empty($batch)) {
            $db->table('menu_groups')->insertBatch($batch);
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

        $groups = $db->table('auth_groups')
            ->select('id, name, description')
            ->orderBy('name')
            ->get()
            ->getResultArray();

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
     *     'groups'        => [1, 2],
     *   ],
     * ]
     */
    public function syncMenu(array $items, string $section = 'backend'): array
    {
        $db = \Config\Database::connect();

        if (! in_array($section, ['backend', 'frontend'], true)) {
            throw new \InvalidArgumentException('Section menu tidak valid.');
        }

        if (empty($items)) {
            throw new \InvalidArgumentException('Pilih minimal satu kandidat menu.');
        }

        $allowedPositions = ['sidebar', 'topbar', 'down'];

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

            /* $validGroupIds = array_map(
                'intval',
                array_column(
                    $db->table('auth_groups')->select('name')->get()->getResultArray(),
                    'name'
                )
            ); */

            $validGroupNames = array_column(
                $db->table('auth_groups')->select('name')->get()->getResultArray(),
                'name'
            );

            $sequences = [];

            foreach ($items as $item) {
                $routeMenuId = (int) ($item['route_menu_id'] ?? 0);
                $position    = (string) ($item['position'] ?? 'sidebar');
                $groupIds    = $item['groups'] ?? [];

                if ($routeMenuId < 1) {
                    throw new \InvalidArgumentException('ID kandidat menu tidak valid.');
                }

                if (! in_array($position, $allowedPositions, true)) {
                    throw new \InvalidArgumentException('Position menu tidak valid.');
                }

                /* if (! is_array($groupIds)) {
                    throw new \InvalidArgumentException('Format grup akses tidak valid.');
                } */

                /* $groupIds = array_values(array_unique(array_map('intval', $groupIds))); */
                $groupNames = array_values(array_unique(array_filter(
                    array_map(
                        static fn($name) => trim((string) $name),
                        $groupIds
                    ),
                    static fn($name) => $name !== ''
                )));
                /*  foreach ($groupIds as $groupId) {
                    if (! in_array($groupId, $validGroupIds, true)) {
                        throw new \InvalidArgumentException(
                            'Terdapat grup akses yang tidak valid.'
                        );
                    }
                }
 */
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

                /* if (! empty($groupIds)) {
                    $relations = [];

                    foreach ($groupIds as $groupId) {
                        $relations[] = [
                            'menu_id'  => (int) $menuId,
                            'group_id' => $groupId,
                        ];
                    }

                    $db->table('menu_groups')->insertBatch($relations);
                } */

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

            $db->transCommit();

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
