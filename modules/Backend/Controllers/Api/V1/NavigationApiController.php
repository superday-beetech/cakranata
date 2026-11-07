<?php

namespace Modules\Backend\Controllers\API\V1;

use App\Core\ApiController;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Backend\Models\NavigationModel;

class NavigationApiController extends ApiController
{
    protected $menuModel;

    public function __construct()
    {
        $this->menuModel = new NavigationModel();
    }

    public function index(): ResponseInterface
    {

        $position = $this->request->getGet('position') ?? 'sidebar';
        $section  = $this->request->getGet('section') ?? 'backend';
        $rawGroups = $this->menuModel->getAllGroups();

        $roles = array_column($rawGroups, 'name'); // Ganti 'name' dengan kolom primary key group jika menggunakan ID
        $data = [
            'title'    => 'Manajemen Menu (' . ucfirst($section) . ' - ' . ucfirst($position) . ')',
            'position' => $position,
            'section'  => $section,
            'menus'    => $this->menuModel->getMenusWithGroupsByPositionAndSection($position, $section),
            'roles'    => $roles,
        ];
        return $this->successResponse([
            'data' => $data,
        ], 'Navigasi berhasil dimuat.');
    }


    // Simpan atau Update Menu (CRUD)
    public function create()
    {
        $id = $this->request->getPost('id');
        $allowedGroups = $this->request->getPost('groups') ?? [];
        $position = $this->request->getPost('position') ?? 'sidebar';
        $section  = $this->request->getPost('section') ?? 'backend';

        $dataSave = [
            'title'     => $this->request->getPost('title'),
            'url'       => $this->request->getPost('url'),
            'icon'      => $this->request->getPost('icon'),
            'parent_id' => $this->request->getPost('parent_id') ?: 0, // Adjacency List parent
            'position'  => $position,
            'section'   => $section,
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];

        if ($id) {
            $this->menuModel->update($id, $dataSave);
        } else {
            $dataSave['sequence'] = $this->menuModel->where('position', $position)->where('section', $section)->countAllResults() + 1;
            $id = $this->menuModel->insert($dataSave);
        }

        // Simpan relasi group agar hak akses terisolasi
        $this->menuModel->syncGroups($id, $allowedGroups);
        return $this->response->setJSON([
            'status'     => 'success',
            'message'    => 'Struktur Adjacency Menu berhasil diperbarui!',
            'csrf_token' => csrf_hash(),
            'position'   => $position,
            'section'    => $section
        ]);
    }

    // Endpoint AJAX untuk memperbarui Adjacency Tree (Parent & Sequence) via SortableJS
    public function updateOrder()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'Invalid AJAX request'
            ]);
        }

        try {
            $json = json_decode(file_get_contents('php://input'), true);

            if (empty($json) || !is_array($json)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'status'  => 'error',
                    'message' => 'Format JSON tidak valid.'
                ]);
            }

            // Controller bersih: cukup panggil fungsi dari Model
            $this->menuModel->saveHierarchy($json, 0);

            return $this->response->setJSON([
                'status'     => 'success',
                'message'    => 'Struktur Adjacency Menu berhasil diperbarui!',
                'csrf_token' => csrf_hash()
            ]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Database Error: ' . $e->getMessage()
            ]);
        }
    }

    public function show($id = null): ResponseInterface
    {
        if ($id === null) {
            return $this->errorResponse('ID menu wajib diisi.', 400);
        }

        $menu = $this->menuModel->getMenuForEdit($id);

        if (!$menu) {
            return $this->errorResponse('Menu tidak ditemukan.', 404);
        }

        $data = [
            'menu'         => $menu,
            'allGroups'    => $this->menuModel->getAllGroups(),
            'selectedGroups' => $menu['selected_groups'] ?? []
        ];

        return $this->successResponse(
            $data,
            'Detail menu berhasil dimuat.'
        );

        // Tampilkan view form edit
        // return view('modules\\navigation\\edit', $data);
    }

    // Proses Simpan / Update Form Edit Menu
    public function update($id = null): ResponseInterface
    {
        if ($id === null) {
            return $this->errorResponse('ID menu wajib diisi.', 400);
        }
        $data = [
            'title'     => $this->request->getPost('title'),
            'url'       => $this->request->getPost('url'),
            'icon'      => $this->request->getPost('icon'),
            'position'  => $this->request->getPost('position'),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0
        ];

        $groups = $this->request->getPost('groups'); // Array checkbox role

        // Panggil method dari Model
        $this->menuModel->saveMenuWithGroups($id, $data, $groups);

        // return redirect()->to('/admin/menu')->with('success', 'Menu dan hak akses berhasil diperbarui!');
        return $this->response->setJSON([
            'status'     => 'success',
            'message'    => 'Menu dan hak akses berhasil diperbarui!',
            'csrf_token' => csrf_hash()
        ]);
    }

    public function destroy($id): ResponseInterface
    {
        $this->menuModel->delete($id);
        return $this->response->setJSON([
            'status'     => 'success',
            'message'    => 'Menu berhasil dihapus.',
            'csrf_token' => csrf_hash()
        ]);
    }



    public function syncMenuPreview()
    {
        try {
            $model = new NavigationModel();

            return $this->response->setJSON([
                'status' => 'success',
                'data'   => $model->getSyncMenuPreview(),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Sync menu preview: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Gagal memuat data sinkronisasi menu.',
            ]);
        }
    }

    public function syncMenuSave()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        try {
            $items   = $payload['items'] ?? [];
            $section = $payload['section'] ?? 'backend';

            if (! is_array($items)) {
                throw new \InvalidArgumentException('Format data menu tidak valid.');
            }

            $model  = new NavigationModel();
            $result = $model->syncMenu($items, (string) $section);

            return $this->response->setJSON([
                'status'  => 'success',
                'message' => 'Sinkronisasi menu selesai.',
                'data'    => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Sync menu save: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Sinkronisasi gagal. Tidak ada perubahan yang disimpan.',
            ]);
        }
    }
}
