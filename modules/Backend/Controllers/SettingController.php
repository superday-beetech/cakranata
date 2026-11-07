<?php

namespace Modules\Backend\Controllers;

use App\Core\BackendController;
use Modules\Backend\Models\SettingAppModel;

class SettingController extends BackendController
{
    protected $settingModel;

    public function __construct()
    {
        $this->settingModel = new SettingAppModel();
        helper('Modules\Backend\Helpers\setting');
    }

    public function index()
    {
        $settings = $this->settingModel->where('deleted', 0)->orderBy('sort_order', 'ASC')->findAll();

        $settingsByType = [];
        foreach ($settings as $setting) {
            $settingsByType[$setting['type']][] = $setting;
        }
        $this->theme->addInlineJS(<<<JS

            JS);
        $this->plugins_editor();
        $settings = service('settings');

        $settings->set('App.siteName', 'Setting');
        $settings->set('Site.siteName', 'Cakra');
        return $this->render('settings/index', [
            'settingsByType' => $settingsByType,
            'label'           => setting('Site.siteName'),
        ]);
    }

    public function save()
    {
        $post       = $this->request->getPost('value'); // value[name] => value
        $sortOrders = $this->request->getPost('sort_order');
        $files      = $this->request->getFiles();      // Ambil semua file

        // foreach ($post as $name => $value) {
        //     $this->settingModel->setValue($name, $value);
        // }

        foreach ($post as $name => $value) {
            // Saring value dulu
            // $cleanValue = ($value);
            $cleanValue = $this->stripHTMLtags($value);
            $this->settingModel->setValue($name, $cleanValue);

            if (isset($sortOrders[$name]) && is_numeric($sortOrders[$name])) {
                $this->settingModel
                    ->where('name', $name)
                    ->set(['sort_order' => (int) $sortOrders[$name]])
                    ->update();
            }
        }
        return redirect()->to('/backend/Setting')->with('success', 'Pengaturan berhasil disimpan.');
    }

    public function create()
    {
        return view('SettingApp/create');
    }

    private function stripHTMLtags($str)
    {
        $t = str_replace('<p>', '', $str);
        $t = str_replace('</p>', '', $str);
        $t = str_replace('<p><br></p>', '', $str);
        $t = str_replace('<p><br/></p>', '', $str);
        $t = str_replace('&lt;p&gt;&lt;br&gt;&lt;/p&gt;', '', $str);
        $t = str_replace('background-color: rgb(255, 255, 255)', '', $str);
        // $t = preg_replace('/<[^<|>]+?/>/', '', htmlspecialchars_decode($t));
        /*$t = preg_replace('/<[^<|>]+?>/', '', htmlspecialchars_decode($t));*/
        // $t = esc($t);

        return $t;
    }

    public function store()
    {
        $data               = $this->request->getPost();
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['deleted']    = 0;

        $this->settingModel->insert($data);

        return redirect()->to('/backend/Setting')->with('success', 'Setting baru ditambahkan.');
    }

    public function upload()
    {
        $file        = $this->request->getFile('file');
        $settingName = $this->request->getPost('setting_name'); // ambil nama setting

        if ($file && $file->isValid() && !$file->hasMoved()) {
            $newName    = $file->getRandomName();
            $uploadPath = FCPATH . 'uploads/settings/';
            if (! is_dir($uploadPath) && ! mkdir($uploadPath, 0755, true)) {
                return $this->response->setStatusCode(500)->setJSON([
                    'status'  => false,
                    'message' => 'Folder upload tidak dapat dibuat.',
                ]);
            }

            if (! $file || ! $file->isValid() || $file->hasMoved()) {
                return $this->response->setStatusCode(400)->setJSON([
                    'status'  => false,
                    'message' => 'Upload gagal atau file tidak valid.',
                ]);
            }
            // ambil nama file lama dari database
            $oldValue = service('app_settings')->get($settingName);

            // upload file baru
            $file->move($uploadPath, $newName);

            // hapus file lama jika ada & file-nya memang ada di folder
            if (!empty($oldValue)) {
                $oldFile = $uploadPath . $oldValue;
                if (is_file($oldFile) && file_exists($oldFile)) {
                    @unlink($oldFile);
                }
            }

            // update DB dengan nama file baru
            service('app_settings')->set($settingName, $newName);

            return $this->response->setJSON([
                'status'   => true,
                'filename' => $newName,
                'url'      => base_url('uploads/settings/' . $newName),
            ]);
        }

        return $this->response->setJSON([
            'status'  => false,
            'message' => 'Upload gagal atau file tidak valid',
        ]);
    }
}
