<?php

namespace App\Core;

use App\Controllers\BaseController;
use App\Libraries\MenuBuilder;
use App\Libraries\Theme;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class BackendController extends BaseController
{
    use ResponseTrait;
    protected $response;
    protected $auth;
    protected ?Theme $theme = null;
    protected $logger;
    protected $data = [];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->theme = new Theme();
        $this->theme->setTheme('adminmart');
        $this->auth = auth('session');

        helper(['cakra', 'users', 'form', 'vite']);
        // $this->logger = service('logger');
        $this->theme->addClass('body', 'link-sidebar');
        // $this->theme->addClass('body', 'layout-fixed sidebar-expand-lg bg-body-tertiary pace-green');
        $this->theme->addCss([
            ['bootstrap-icons.min', 'plugins/bootstrap-icons'],
            ['css/all.min', 'plugins/fontawesome-free'],
            ['icheck-bootstrap.min.css', 'plugins/icheck-bootstrap'],
            ['sweetalert2.min.css', 'plugins/sweetalert2'],
            'styles',
        ]);

        $this->theme->addJs([
            ['js/bootstrap.bundle.min', 'plugins/bootstrap', true, 'footer'],
            ['js/simplebar.min', 'plugins/simplebar', true, 'footer'],
            ['theme', '', '', ''],
            'app.min',
            'api',
            'sidebarmenu',
            'superday',
            ['sweetalert2.min', 'plugins/sweetalert2', true, 'footer'],
            ['min/numeral.min', 'plugins/Numeral-js', true, 'footer'],
        ]);

        $settings = [
            'Layout'      => app_setting('admin_layout')  ?? 'horizontal',
            'SidebarType' => app_setting('admin_sidebar') ?? 'full',
            'BoxedLayout' => (bool) (app_setting('admin_boxed') ?? 'false'),
            'Direction'   => 'ltr',
            'Theme'       => app_setting('admin_theme')       ?? 'light',
            'ColorTheme'  => app_setting('admin_theme_color') ?? 'Blue_Theme',
            'cardBorder'  => (bool) (app_setting('admin_card') ?? false),
        ];
        $jsSettings = json_encode($settings);
        // $jsSettings = json_encode($settings, JSON_PRETTY_PRINT);
        $this->theme->addInlineJS(
            <<<JS
            var userSettings = $jsSettings;
            JS,
            'header'
        );

        $rendered = service('renderer');
        $rendered->setVar('theme', $this->theme);


        // print_r($this->auth->loggedIn());
    }

    public function render(string $view, array $data = [])
    {
        $this->theme->addJs([
            // 'script',
        ]);

        $csrf_hash   = csrf_hash();
        $csrf_header = config('Security')->headerName;
        $csrf_name = csrf_token();
        $current_url = current_url();

        $this->theme->addMeta([
            ['charset' => 'UTF-8'],
            ['name'     => 'csrf-name', 'content' => $csrf_name],
            ['name'     => 'csrf-token', 'content' => $csrf_hash],
            ['name'     => 'csrf-header', 'content' => $csrf_header],
            ['name'     => 'X-UA-Compatible', 'content' => 'IE=edge', 'http-equiv'],
            ['name'     => 'Content-Type', 'content' => 'text/html; charset=UTF-8', 'http-equiv'],
            ['name'     => 'viewport', 'content' => 'width=device-width, initial-scale=1.0'],
            ['name'     => 'description', 'content' => app_setting('meta_description')],
            ['property' => 'og:title', 'content' => app_setting('meta_title')],
            ['rel'      => 'icon', 'href' => get_upload_url('settings/' . app_setting('favicon')), 'tag' => 'link'],
            ['rel'      => 'canonical', 'href' => $current_url, 'tag' => 'link'],
        ]);
        // $this->theme->addMeta('preconnect', 'https://fonts.gstatic.com', 'link');
        if (!$this->theme->wasThemeOverridden()) {
            $this->theme->getTheme();
        }

        if (!$this->theme->wasLayoutOverridden()) {
            $this->theme->getLayout();
        }
        if (!$this->theme->wasPartialRemoved('navbar')) {
            $this->theme->addPartial('navbar');
        }
        if (!$this->theme->wasPartialRemoved('sidebar')) {
            $this->theme->addPartial('sidebar');
        }
        if (!$this->theme->wasPartialRemoved('sidebar-horizontal')) {
            $this->theme->addPartial('sidebar-horizontal');
        }
        if (!$this->theme->wasPartialRemoved('wrapper')) {
            $this->theme->addPartial('wrapper');
        }
        $rendered = service('renderer');
        $rendered->setVar('theme', $this->theme);
        $menuBuilder         = new MenuBuilder();
        $data['backendMenu'] = $menuBuilder->buildBackend([
            'table'    => 'menus',
            'section'  => 'backend',
            'position' => 'sidebar',
            'preset'   => 'adminmart',
        ]);
        $data['backendMenuHorizontal'] = $menuBuilder->buildBackend([
            'table'    => 'menus',
            'section'  => 'backend',
            'position' => 'sidebar',
            'preset'   => 'adminmart_horizontal',
        ]);
        $data['theme'] = $this->theme;

        return $this->theme->render($view, $data);
    }

    private function render_menu()
    {
        $menuModel = new \App\Models\MenuModel();
        $menu      = $menuModel->getNestedMenu();

        $menuHtml = (new \App\Libraries\MenuBuilder())
            ->setPreset('adminmart_horizontal')
            ->setMaxDepth(3)
            ->setDepthOverflowFallback('More')
            ->setMenu($menu);

        return $menuHtml;
    }

    public function plugins_editor()
    {
        $this->theme->addCss([
            ['icheck-bootstrap.min.css', 'plugins/icheck-bootstrap'],
            // ['css/tom-select', 'plugins/TomSelect'],
            ['css/choices.min', 'plugins/choices'],
            ['css/tempus-dominus.min', 'plugins/tempusdominus'],
            ['daterangepicker.css', 'plugins/daterangepicker'],
            ['css/quill.snow', 'plugins/quill'],
            ['css/cropper.min', 'plugins/cropperjs'],
        ]);

        $this->theme->addJs([
            // ['js/tom-select.complete.min', 'plugins/TomSelect'],
            ['js/choices.min', 'plugins/choices'],
            ['vanilla-maxlength', 'plugins/vanilla'],
            ['holder', 'plugins/holder'],
            ['js/tempus-dominus.min', 'plugins/tempusdominus'],
            ['js/quill.min', 'plugins/quill'],
            ['js/cropper.min', 'plugins/cropperjs'],

            // ['js/image-resize.min', 'plugins/quill'],
        ]);
    }
    public function plugins()
    {
        $this->theme->addCss(
            [
                ['css/tabulator_bootstrap5', 'plugins/tabulator'],
                ['icheck-bootstrap.min', 'plugins/icheck-bootstrap'],
                ['css/glightbox.min', 'plugins/glightbox'],
            ]
        );

        // $this->theme->removeJS('jquery.min', 'jquery');
        $this->theme->addJS(
            [
                // ['jquery.min', 'jquery', true, 'header'],
                ['js/tabulator.min', 'plugins/tabulator'],
                // ['js/jspdf.umd.min', 'plugins/tabulator'],
                // ['js/xlsx.full.min', 'plugins/tabulator'],
                ['js/glightbox.min', 'plugins/glightbox'],
                // ['holder', 'plugins/holder'],
            ]
        );

        $this->theme->addInlineJS(<<<JS
        document.addEventListener("DOMContentLoaded", function() {
            const lightbox = GLightbox({
                selector: '.glightbox',
                touchNavigation: true,
                loop: true
            });
        });
        JS);
    }

    private function stripHTMLtags($str)
    {
        $t = str_replace('<p><br></p>', '', $str);
        $t = str_replace('<p><br/></p>', '', $str);
        $t = str_replace('&lt;p&gt;&lt;br&gt;&lt;/p&gt;', '', $str);
        $t = str_replace('background-color: rgb(255, 255, 255)', '', $str);
        // $t = preg_replace('/<[^<|>]+?/>/', '', htmlspecialchars($t));
        /*$t = preg_replace('/<[^<|>]+?>/', '', htmlspecialchars_decode($t));*/
        $t = esc($t);

        return $t;
    }
}
