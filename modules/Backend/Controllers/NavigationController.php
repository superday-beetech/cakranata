<?php

namespace Modules\Backend\Controllers;

use App\Core\BackendController;
use Modules\Backend\Models\NavigationModel;

class NavigationController extends BackendController
{
    protected $menuModel;

    public function __construct()
    {
        $this->menuModel = new NavigationModel();
    }

    public function index(): string
    {
        $data['title']    = 'Navigation';
        $data['assetUrl'] = $this->theme->assetWithUrl('img/ajax-loader.gif');
        $this->theme->enableTooltip();

        $this->theme->addJs('Sortable.min');
        $this->theme->addInlineCSS(<<<CSS

            .menu-list, .menu-sublist { list-style: none; padding-left: 0; }
            .menu-item > .menu-card { background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); transition: all 0.2s; }
            .menu-item > .menu-card:hover { border-color: var(--bs-primary); }
            .menu-sublist { padding-left: 2rem; border-left: 2px dashed var(--bs-border-color); margin-top: 0.5rem; }
            .menu-item.dragging { opacity: 0.4; }

        CSS);

        $position = $this->request->getGet('position') ?? 'sidebar';
        $section  = $this->request->getGet('section') ?? 'backend';
        $rawGroups = $this->menuModel->getAllGroups();

        // Ubah hasil array database menjadi format array pipih untuk checkbox view
        $roles = array_column($rawGroups, 'name'); // Ganti 'name' dengan kolom primary key group jika menggunakan ID
        $data = [
            'title'    => 'Manajemen Menu (' . ucfirst($section) . ' - ' . ucfirst($position) . ')',
            'position' => $position,
            'section'  => $section,
            'menus'    => $this->menuModel->getMenusWithGroupsByPositionAndSection($position, $section),
            // 'menus'    => $this->menuModel->getMenusWithGroupsByPosition($position),
            // 'roles'    => array_keys(setting('AuthGroups.groups') ?? ['admin' => [], 'backend' => [], 'frontend' => []]),
            'roles'    => $roles,
        ];

        // $this->theme->addJs('custom_js');
        return $this->render("navigation/index", $data);
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
    // Simpan atau Update Menu (CRUD)
    public function save()
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

        return redirect()->to('/backend/navigation?position=' . $position . '&section=' . $section)->with('success', 'Menu berhasil disimpan.');
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

    public function edit($id)
    {
        $menu = $this->menuModel->getMenuForEdit($id);

        if (!$menu) {
            return redirect()->to('/backend/navigation')->with('error', 'Menu tidak ditemukan.');
        }

        $data = [
            'menu'         => $menu,
            'allGroups'    => $this->menuModel->getAllGroups(),
            'selectedGroups' => $menu['selected_groups'] ?? []
        ];

        // Tampilkan view form edit
        return view('modules\\navigation\\edit', $data);
    }

    // Proses Simpan / Update Form Edit Menu
    public function update($id)
    {
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

        return redirect()->to('/admin/menu')->with('success', 'Menu dan hak akses berhasil diperbarui!');
    }

    public function delete($id)
    {
        $this->menuModel->delete($id);
        return redirect()->back()->with('success', 'Menu berhasil dihapus.');
    }
}
