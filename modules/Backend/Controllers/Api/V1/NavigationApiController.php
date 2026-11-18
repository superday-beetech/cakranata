<?php

declare(strict_types=1);

namespace Modules\Backend\Controllers\API\V1;

use App\Core\ApiController;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;
use InvalidArgumentException;
use Modules\Backend\Models\NavigationModel;
use RuntimeException;
use Throwable;

class NavigationApiController extends ApiController
{
    private NavigationModel $menuModel;

    private const POSITIONS = [
        'sidebar',
        'topbar',
        'down',
        'quick_links',
        'apps',
    ];

    private const SECTIONS = [
        'backend',
        'frontend',
    ];

    public function __construct()
    {
        $this->menuModel = new NavigationModel();
    }

    /**
     * GET /api/v1/backend/navigation-api?position=sidebar&section=backend
     *
     * Envelope sengaja mempertahankan data.data karena navigation.api.ts
     * menggunakan apiGet() yang membuka satu lapisan envelope.
     */
    public function index(): ResponseInterface
    {
        $position = (string) ($this->request->getGet('position') ?? 'sidebar');
        $section  = (string) ($this->request->getGet('section') ?? 'backend');

        if (! in_array($position, self::POSITIONS, true) || ! in_array($section, self::SECTIONS, true)) {
            return $this->errorResponse('Posisi atau section navigasi tidak valid.', 422);
        }

        try {
            $roles = array_values(array_filter(
                array_map(
                    static fn(array $group): string => (string) ($group['name'] ?? ''),
                    $this->menuModel->getAllGroups()
                ),
                static fn(string $name): bool => $name !== ''
            ));

            $data = [
                'title'    => 'Manajemen Menu (' . ucfirst($section) . ' - ' . ucfirst($position) . ')',
                'position' => $position,
                'section'  => $section,
                'menus'    => $this->menuModel->getMenusWithGroupsByPositionAndSection($position, $section),
                'roles'    => $roles,
            ];

            return $this->successResponse(
                ['data' => $data],
                'Navigasi berhasil dimuat.'
            );
        } catch (Throwable $e) {
            log_message('error', '[Navigation API][index] ' . $e->getMessage());

            return $this->errorResponse('Gagal memuat data navigasi.', 500);
        }
    }

    /**
     * POST /navigation-api/create
     * Menerima FormData dari MenuForm.
     */
    public function create(): ResponseInterface
    {
        $db = Database::connect();
        $transactionStarted = false;

        try {
            $idInput = trim((string) ($this->request->getPost('id') ?? ''));
            $id = $idInput === '' ? null : filter_var($idInput, FILTER_VALIDATE_INT);

            if ($idInput !== '' && ($id === false || (int) $id < 1)) {
                throw new InvalidArgumentException('ID menu tidak valid.');
            }

            $title = trim((string) ($this->request->getPost('title') ?? ''));
            $url = trim((string) ($this->request->getPost('url') ?? ''));
            $icon = trim((string) ($this->request->getPost('icon') ?? ''));
            $parentInput = $this->request->getPost('parent_id');
            $parentId = ($parentInput === null || $parentInput === '') ? 0 : filter_var($parentInput, FILTER_VALIDATE_INT);
            $position = (string) ($this->request->getPost('position') ?? 'sidebar');
            $section = (string) ($this->request->getPost('section') ?? 'backend');
            $activeInput = $this->request->getPost('is_active');
            $groups = $this->request->getPost('groups') ?? [];

            if ($title === '' || mb_strlen($title) > 150) {
                throw new InvalidArgumentException('Judul menu wajib diisi dan maksimal 150 karakter.');
            }

            if (mb_strlen($url) > 2048 || mb_strlen($icon) > 150) {
                throw new InvalidArgumentException('URL atau ikon menu terlalu panjang.');
            }

            if ($parentId === false || (int) $parentId < 0) {
                throw new InvalidArgumentException('Parent menu tidak valid.');
            }

            if (! in_array($position, self::POSITIONS, true) || ! in_array($section, self::SECTIONS, true)) {
                throw new InvalidArgumentException('Posisi atau section menu tidak valid.');
            }

            if (! is_array($groups) || ! array_is_list($groups)) {
                throw new InvalidArgumentException('Format hak akses group tidak valid.');
            }

            $validGroups = array_column($this->menuModel->getAllGroups(), 'name');
            $groups = array_values(array_unique(array_filter(
                array_map(static function ($group): string {
                    if (! is_string($group)) {
                        throw new InvalidArgumentException('Nama group akses tidak valid.');
                    }

                    return trim($group);
                }, $groups),
                static fn(string $group): bool => $group !== ''
            )));

            foreach ($groups as $group) {
                if (! in_array($group, $validGroups, true)) {
                    throw new InvalidArgumentException('Terdapat group akses yang tidak terdaftar.');
                }
            }

            $this->validateParent((int) $parentId, $id === null ? null : (int) $id, $position, $section);

            $data = [
                'title'     => $title,
                'url'       => $url,
                'icon'      => $icon,
                'parent_id' => (int) $parentId,
                'position'  => $position,
                'section'   => $section,
                'is_active' => in_array((string) $activeInput, ['1', 'true', 'on'], true) ? 1 : 0,
            ];

            $db->transBegin();
            $transactionStarted = true;

            if ($id !== null) {
                if (! $this->menuModel->find((int) $id)) {
                    $db->transRollback();
                    return $this->errorResponse('Menu tidak ditemukan.', 404);
                }

                if (! $this->menuModel->update((int) $id, $data)) {
                    throw new RuntimeException('Menu gagal diperbarui: ' . implode(', ', $this->menuModel->errors()));
                }

                $menuId = (int) $id;
            } else {
                $maxRow = $db->table('menus')
                    ->selectMax('sequence', 'max_sequence')
                    ->where('position', $position)
                    ->where('section', $section)
                    ->get()
                    ->getRowArray();

                $data['sequence'] = (int) ($maxRow['max_sequence'] ?? 0) + 1;
                $insertedId = $this->menuModel->insert($data, true);

                if (! $insertedId) {
                    throw new RuntimeException('Menu gagal ditambahkan: ' . implode(', ', $this->menuModel->errors()));
                }

                $menuId = (int) $insertedId;
            }

            $this->menuModel->syncGroups($menuId, $groups);

            if ($db->transStatus() === false || $db->transCommit() === false) {
                throw new RuntimeException('Transaksi penyimpanan menu gagal.');
            }

            $transactionStarted = false;

            return $this->response->setJSON([
                'status'     => 'success',
                'message'    => $id === null ? 'Menu berhasil ditambahkan.' : 'Menu berhasil diperbarui.',
                'csrf_token' => csrf_hash(),
                'position'   => $position,
                'section'    => $section,
                'id'         => $menuId,
            ]);
        } catch (InvalidArgumentException $e) {
            if ($transactionStarted) {
                $db->transRollback();
            }

            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            if ($transactionStarted) {
                $db->transRollback();
            }

            log_message('error', '[Navigation API][create] ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Menu gagal disimpan. Periksa log aplikasi.',
            ]);
        }
    }

    /**
     * POST /navigation-api/update-order
     * Payload berupa array hierarki mentah dari NavigationView.
     */
    public function updateOrder(): ResponseInterface
    {
        try {
            $tree = $this->request->getJSON(true);

            if (! is_array($tree) || $tree === [] || ! array_is_list($tree)) {
                throw new InvalidArgumentException('Format struktur menu tidak valid.');
            }

            $this->menuModel->saveHierarchy($tree);

            return $this->response->setJSON([
                'status'     => 'success',
                'message'    => 'Struktur menu berhasil diperbarui.',
                'csrf_token' => csrf_hash(),
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            log_message('error', '[Navigation API][updateOrder] ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Struktur menu gagal diperbarui.',
            ]);
        }
    }

    /**
     * DELETE /navigation-api/destroy/{id}
     */
    public function destroy($id = null): ResponseInterface
    {
        $menuId = filter_var($id, FILTER_VALIDATE_INT);

        if ($menuId === false || (int) $menuId < 1) {
            return $this->errorResponse('ID menu tidak valid.', 422);
        }

        $menu = $this->menuModel->find((int) $menuId);
        if (! $menu) {
            return $this->errorResponse('Menu tidak ditemukan.', 404);
        }

        $db = Database::connect();
        $hasChildren = $db->table('menus')
            ->where('parent_id', (int) $menuId)
            ->countAllResults();

        if ($hasChildren > 0) {
            return $this->errorResponse('Menu masih memiliki submenu. Pindahkan atau hapus submenu terlebih dahulu.', 409);
        }

        $db->transBegin();

        try {
            $db->table('menu_groups')->where('menu_id', (int) $menuId)->delete();

            if (! $this->menuModel->delete((int) $menuId) || $db->transStatus() === false) {
                throw new RuntimeException('Menu gagal dihapus.');
            }

            if ($db->transCommit() === false) {
                throw new RuntimeException('Transaksi penghapusan menu gagal.');
            }

            return $this->response->setJSON([
                'status'     => 'success',
                'message'    => 'Menu berhasil dihapus.',
                'csrf_token' => csrf_hash(),
            ]);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', '[Navigation API][destroy] ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Menu gagal dihapus.',
            ]);
        }
    }

    /**
     * POST /navigation-api/sync-menu-preview
     * Bentuk respons mentah dipertahankan agar apiPost() membuka data preview.
     */
    public function syncMenuPreview(): ResponseInterface
    {
        try {
            return $this->response->setJSON([
                'status' => 'success',
                'data'   => $this->menuModel->getSyncMenuPreview(),
            ]);
        } catch (Throwable $e) {
            log_message('error', '[Navigation API][syncMenuPreview] ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Gagal memuat data sinkronisasi menu.',
            ]);
        }
    }

    /**
     * POST /navigation-api/sync-menu-save
     */
    public function syncMenuSave(): ResponseInterface
    {
        try {
            $payload = $this->request->getJSON(true);

            if (! is_array($payload)) {
                throw new InvalidArgumentException('Body permintaan tidak valid.');
            }

            $items = $payload['items'] ?? [];
            $section = (string) ($payload['section'] ?? 'backend');

            if (! is_array($items) || ! array_is_list($items)) {
                throw new InvalidArgumentException('Format data menu tidak valid.');
            }

            $result = $this->menuModel->syncMenu($items, $section);

            return $this->response->setJSON([
                'status'  => 'success',
                'message' => 'Sinkronisasi menu selesai.',
                'data'    => $result,
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            log_message('error', '[Navigation API][syncMenuSave] ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Sinkronisasi gagal. Tidak ada perubahan yang disimpan.',
            ]);
        }
    }

    private function validateParent(int $parentId, ?int $menuId, string $position, string $section): void
    {
        if ($parentId === 0) {
            return;
        }

        $visited = [];
        $currentId = $parentId;

        while ($currentId > 0) {
            if ($menuId !== null && $currentId === $menuId) {
                throw new InvalidArgumentException('Menu tidak dapat menjadi parent bagi dirinya sendiri atau turunannya.');
            }

            if (isset($visited[$currentId])) {
                throw new InvalidArgumentException('Struktur parent menu terdeteksi memiliki siklus.');
            }

            $visited[$currentId] = true;
            $parent = $this->menuModel->find($currentId);

            if (! $parent) {
                throw new InvalidArgumentException('Parent menu tidak ditemukan.');
            }

            if ($parent['position'] !== $position || $parent['section'] !== $section) {
                throw new InvalidArgumentException('Parent harus berada pada posisi dan section yang sama.');
            }

            $currentId = (int) ($parent['parent_id'] ?? 0);
        }
    }
}
