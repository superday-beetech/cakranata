<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Libraries\ThemeManager\ThemeAssetManager;
use App\Libraries\ThemeManager\ThemeBreadcrumbManager;
use App\Libraries\ThemeManager\ThemeMetadataManager;
use App\Libraries\ThemeManager\ThemeModalManager;
use App\Libraries\ThemeManager\ThemePartialManager;
use App\Libraries\ThemeManager\ThemeStateManager;
use App\Libraries\ThemeManager\ThemeViewManager;

class Theme
{
    protected ThemeAssetManager $assetManager;
    protected ThemeBreadcrumbManager $breadcrumbManager;
    protected ThemeMetadataManager $metadataManager;
    protected ThemeModalManager $modalManager;
    protected ThemePartialManager $partialManager;
    protected ThemeStateManager $stateManager;
    protected ThemeViewManager $viewManager;

    public string $defaultTheme = 'viho';
    public string $defaultFolder = '';

    protected array $defaultData = [];

    public function __construct()
    {
        $this->viewManager = new ThemeViewManager(
            $this->defaultTheme,
            'default',
            'baseView'
        );

        $this->assetManager = new ThemeAssetManager(
            $this->viewManager,
            $this->defaultFolder
        );

        $this->breadcrumbManager = new ThemeBreadcrumbManager();
        $this->metadataManager = new ThemeMetadataManager();
        $this->modalManager = new ThemeModalManager();
        $this->partialManager = new ThemePartialManager();
        $this->stateManager = new ThemeStateManager();
    }

    /*
    |--------------------------------------------------------------------------
    | Theme, layout, and view
    |--------------------------------------------------------------------------
    */

    public function setTheme(string $theme): self
    {
        $this->viewManager->setTheme($theme);

        return $this;
    }

    public function getTheme(): string
    {
        return $this->viewManager->getTheme();
    }

    public function setLayout(string $layout): self
    {
        $this->viewManager->setLayout($layout);

        return $this;
    }

    public function getLayout(): string
    {
        return $this->viewManager->getLayout();
    }

    public function setBaseView(string $baseView): self
    {
        $this->viewManager->setBaseView($baseView);

        return $this;
    }

    public function getBaseView(): string
    {
        return $this->viewManager->getBaseView();
    }

    public function setView(string $view): self
    {
        $this->stateManager->setVar('_content_view', $view);

        return $this;
    }

    public function setTitle(?string $title): self
    {
        $this->stateManager->setTitle($title);

        return $this;
    }

    public function wasThemeOverridden(): bool
    {
        return $this->viewManager->wasThemeOverridden();
    }

    public function wasLayoutOverridden(): bool
    {
        return $this->viewManager->wasLayoutOverridden();
    }

    public function wasBaseViewOverridden(): bool
    {
        return $this->viewManager->wasBaseViewOverridden();
    }

    public function resetThemeOverrides(): self
    {
        $this->viewManager->resetThemeOverrides();

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Metadata
    |--------------------------------------------------------------------------
    */

    public function addMeta(
        string|array $name,
        string $content = '',
        string $type = 'name',
        array $extraAttributes = []
    ): self {
        $this->metadataManager->addMeta(
            $name,
            $content,
            $type,
            $extraAttributes
        );

        return $this;
    }

    public function setMeta(string $name, string $content): self
    {
        $this->metadataManager->setMeta($name, $content);

        return $this;
    }

    public function metaHTML(): string
    {
        return $this->metadataManager->metaHTML();
    }

    /*
    |--------------------------------------------------------------------------
    | CSS and JavaScript
    |--------------------------------------------------------------------------
    */

    public function addCss(string|array $css): self
    {
        $this->assetManager->addCss($css);

        return $this;
    }

    public function addInlineCSS(string $css): self
    {
        $this->assetManager->addInlineCSS($css);

        return $this;
    }

    public function removeCss(string $css): self
    {
        $this->assetManager->removeCss($css);

        return $this;
    }

    public function replaceCss(string $oldCss, string $newCss): self
    {
        $this->assetManager->replaceCss($oldCss, $newCss);

        return $this;
    }

    public function removeAllCss(): self
    {
        $this->assetManager->removeAllCss();

        return $this;
    }

    public function renderCss(): string
    {
        return $this->assetManager->renderCss();
    }

    public function addJs(
        string|array $js,
        string $folder = '',
        array|bool $options = [],
        string $position = 'footer'
    ): self {
        $this->assetManager->addJs($js, $folder, $options, $position);

        return $this;
    }


    public function replaceJs(
        string $oldJs,
        string $newJs,
        string $position = 'footer'
    ): self {
        $this->assetManager->replaceJs($oldJs, $newJs, $position);

        return $this;
    }

    public function removeJs(
        string $js,
        string $position = 'footer'
    ): self {
        $this->assetManager->removeJs($js, $position);

        return $this;
    }

    public function removeAllJs(): self
    {
        $this->assetManager->removeAllJs();

        return $this;
    }

    public function addInlineJS(
        string $js,
        string $position = 'footer'
    ): self {
        $this->assetManager->addInlineJS($js, $position);

        return $this;
    }

    public function addInlineJsOnce(
        string $key,
        string $js,
        string $position = 'footer'
    ): self {
        $this->assetManager->addInlineJsOnce($key, $js, $position);

        return $this;
    }

    public function renderInlineJS(string $position = 'footer'): string
    {
        return $this->assetManager->renderInlineJS($position);
    }

    public function renderJs(string $position = 'footer'): string
    {
        return $this->assetManager->renderJs($position);
    }

    public function getJsQueue(): array
    {
        return $this->assetManager->getJsQueue();
    }

    public function addPlugin(string|array $plugins): self
    {
        $this->assetManager->addPlugin($plugins);

        return $this;
    }

    public function enableTooltip(): self
    {
        $this->assetManager->enableTooltip();

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Data and CSS classes
    |--------------------------------------------------------------------------
    */

    public function setData(array $data): self
    {
        $this->stateManager->setData($data);

        return $this;
    }

    public function getData(?array $key = null): array
    {
        return $this->stateManager->getData($key);
    }

    public function setVar(string $key, mixed $value): self
    {
        $this->stateManager->setVar($key, $value);

        return $this;
    }

    public function getVar(string $key, mixed $default = null): mixed
    {
        return $this->stateManager->getVar($key, $default);
    }

    public function addClass(string $section, string|array $classes): self
    {
        $this->stateManager->addClass($section, $classes);

        return $this;
    }

    public function removeClass(string $section, string|array $classes): self
    {
        $this->stateManager->removeClass($section, $classes);

        return $this;
    }

    public function replaceClass(
        string $section,
        string|array $classes
    ): self {
        $this->stateManager->replaceClass($section, $classes);

        return $this;
    }

    public function clearClasses(?string $section = null): self
    {
        $this->stateManager->clearClasses($section);

        return $this;
    }

    public function getClass(string $section): string
    {
        return $this->stateManager->getClass($section);
    }

    public function assetWithUrl(string $asset, string $defaultFolder = ''): string
    {
        return $this->viewManager->assetUrl($asset, $defaultFolder);
    }

    /*
    |--------------------------------------------------------------------------
    | Breadcrumbs
    |--------------------------------------------------------------------------
    */

    public function breadcrumb(bool $enabled = true): self
    {
        $this->breadcrumbManager->breadcrumb($enabled);

        return $this;
    }

    public function isBreadcrumbEnabled(): bool
    {
        return $this->breadcrumbManager->isBreadcrumbEnabled();
    }

    public function addCrumb(string|array $label, ?string $url = null): self
    {
        $this->breadcrumbManager->addCrumb($label, $url);

        return $this;
    }

    public function addCrumbFromDynamic(): self
    {
        $this->breadcrumbManager->addCrumbFromDynamic();

        return $this;
    }

    public function getBreadcrumbs(): array
    {
        return $this->breadcrumbManager->getBreadcrumbs();
    }

    public function breadcrumbHTML(): string
    {
        return $this->breadcrumbManager->breadcrumbHTML();
    }

    /*
    |--------------------------------------------------------------------------
    | Partial registration
    |--------------------------------------------------------------------------
    */

    public function addPartial(
        string $name,
        array $data = [],
        bool $enabled = true,
        string $location = 'partials'
    ): self {
        $this->partialManager->addPartial(
            $name,
            $data,
            $enabled,
            $location,
            $this->getTheme()
        );

        return $this;
    }

    public function removePartial(string $name): self
    {
        $this->partialManager->removePartial($name);

        return $this;
    }

    public function wasPartialRemoved(string $name): bool
    {
        return $this->partialManager->wasPartialRemoved($name);
    }

    public function hasPartial(string $name): bool
    {
        return $this->partialManager->hasPartial($name);
    }

    public function getPartialData(string $name): array
    {
        return $this->partialManager->getPartialData($name);
    }

    public function getPartialView(string $name): ?string
    {
        return $this->partialManager->getPartialView($name);
    }

    public function renderPartial(string $name): ?string
    {
        return $this->partialManager->renderPartial($name);
    }

    public function getPartial(string $name): ?string
    {
        return $this->partialManager->getPartial($name);
    }

    public function protectPartials(array $names): self
    {
        $this->partialManager->protectPartials($names);

        return $this;
    }

    public function setPartial(string $section, bool $enabled = true): self
    {
        $this->partialManager->setPartial($section, $enabled);

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Modal
    |--------------------------------------------------------------------------
    */

    public function addModalShell(
        string $name,
        array $options = []
    ): void {
        $this->modalManager->addModalShell($name, $options);
    }

    public function renderModals(): string
    {
        return $this->modalManager->renderModals(
            [$this->viewManager, 'locateView']
        );
    }

    public function renderModalShells(): string
    {
        return $this->modalManager->renderModalShells(
            $this->viewManager->detectModule(),
            $this->getTheme(),
            fn(string $js) => $this->addJs($js)
        );
    }

    public function renderModalShellsInject(): string
    {
        return $this->modalManager->renderModalShellsInject();
    }

    /*
    |--------------------------------------------------------------------------
    | View resolution
    |--------------------------------------------------------------------------
    */

    public function locateView(string $view): string
    {
        return $this->viewManager->locateView($view);
    }

    public function detectModule(): string
    {
        return $this->viewManager->detectModule();
    }

    public function getModule(string $folder = ''): string
    {
        return $this->viewManager->getModule($folder);
    }

    /*
    |--------------------------------------------------------------------------
    | Rendering
    |--------------------------------------------------------------------------
    */


    public function render(string $view, array $params = []): string
    {
        if (empty($this->breadcrumbManager->getBreadcrumbs())) {
            $this->breadcrumbManager->addCrumbFromDynamic();
        }

        $defaultData = [
            'theme'   => $this->viewManager->getTheme(),
            'layout'  => $this->viewManager->getLayout(),
            'judul'   => ucwords(str_replace('-', ' ', segment(2, ''))) ?? '',
            'title'   => $this->stateManager->getTitle()
                ?? ucfirst(segment(2, ''))
                ?? '',
            'plugins' => $this->assetManager->getPlugins(),
        ];

        foreach ($this->stateManager->getClasses() as $section => $classList) {
            $defaultData[$section . 'Class'] = implode(' ', $classList);
        }

        $defaultData = array_filter(
            $defaultData,
            static function ($val): bool {
                if (is_array($val)) {
                    return ! empty($val);
                }

                return $val !== null && $val !== '';
            }
        );

        // Pertahankan perilaku lama: data hasil render disimpan.
        $data = array_merge(
            $this->stateManager->getData(),
            $defaultData,
            $params
        );

        $layoutPath = $this->viewManager->locateView(
            'layouts/' . $this->viewManager->getLayout()
        );

        $data['_layout_override'] = $layoutPath;
        $data['_content_view'] = $this->viewManager->locateView($view);

        $this->stateManager->replaceData($data);

        $baseView = $this->viewManager->locateView(
            $this->viewManager->getBaseView()
        );

        return view($baseView, $data);
    }
}
