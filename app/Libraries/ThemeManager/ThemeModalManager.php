<?php

declare(strict_types=1);

namespace App\Libraries\ThemeManager;

class ThemeModalManager
{
    protected array $modals = [];

    protected array $modalShells = [];

    /**
     * Mendaftarkan modal yang menggunakan file view.
     */
    public function addModal(string $name, string $view, array $data = []): self
    {
        $this->modals[$name] = [
            'view' => $view,
            'data' => $data,
        ];

        return $this;
    }

    /**
     * Menghapus modal berdasarkan nama.
     */
    public function removeModal(string $name): self
    {
        unset($this->modals[$name]);

        return $this;
    }

    public function getModals(): array
    {
        return $this->modals;
    }

    /**
     * Mendaftarkan konfigurasi modal shell.
     */
    public function addModalShell(string $id, array $options = []): self
    {
        $this->modalShells[] = array_merge(
            [
                'id'         => $id,
                'header'     => 'Default',
                'size'       => 'lg',
                'form'       => false,
                'action'     => '',
                'method'     => 'post',
                'form_id'    => 'form_' . $id,
                'submit'     => false,
                'submitText' => 'Simpan',
                'keyboard'   => false,
                'backdrop'   => 'static',
                'dynamic'    => true,
            ],
            $options
        );

        return $this;
    }

    public function getModalShells(): array
    {
        return $this->modalShells;
    }

    public function removeModalShell(string $id): self
    {
        $this->modalShells = array_values(
            array_filter(
                $this->modalShells,
                static fn(array $shell): bool => ($shell['id'] ?? null) !== $id
            )
        );

        return $this;
    }

    public function clearModals(): self
    {
        $this->modals = [];

        return $this;
    }

    public function clearModalShells(): self
    {
        $this->modalShells = [];

        return $this;
    }

    /**
     * Merender modal yang didaftarkan menggunakan view.
     *
     * Callback locateView mempertahankan aturan pencarian
     * view milik ThemeViewManager.
     */

    public function renderModals(callable $locateView): string
    {
        $output = '';

        foreach ($this->modals as $modal) {
            $viewPath = $locateView($modal['view']);

            if (! is_string($viewPath) || $viewPath === '') {
                continue;
            }

            if (! view_exists($viewPath)) {
                continue;
            }

            $output .= view(
                $viewPath,
                $modal['data'] ?? []
            );
        }

        return $output;
    }


    /**
     * Merender shell modal dari modul aktif atau theme global.
     *
     * $locateView tidak digunakan di sini karena versi lama
     * memiliki aturan khusus untuk partial modal_shell.
     */
    public function renderModalShells(
        string $module,
        string $theme,
        callable $addJs
    ): string {
        $output = '';
        $hasDynamic = false;

        foreach ($this->modalShells as $shell) {
            $viewPath = "Modules\\{$module}\\Views\\partials\\modal_shell";

            $moduleViewFile = ROOTPATH
                . "Modules/{$module}/Views/partials/modal_shell.php";

            if (! is_file($moduleViewFile)) {
                $viewPath = "themes/{$theme}/partials/modal_shell";
            }

            if (! empty($shell['dynamic'])) {
                $hasDynamic = true;
            }

            $output .= view($viewPath, $shell);
        }

        if ($hasDynamic) {
            $addJs('custom_js');
        }

        return $output;
    }

    /**
     * Menghasilkan markup shell modal untuk konten inject.
     */
    public function renderModalShellsInject(): string
    {
        $output = '';

        foreach ($this->modalShells as $shell) {
            $id = (string) ($shell['id'] ?? '');
            $size = (string) ($shell['size'] ?? 'lg');

            $sizeClass = match ($size) {
                'sm'         => 'modal-sm',
                'lg'         => 'modal-lg',
                'xl'         => 'modal-xl',
                'fullscreen' => 'modal-fullscreen',
                default      => '',
            };

            $safeId = esc($id);
            $safeSizeClass = esc($sizeClass);

            $output .= <<<HTML
            <div class="modal fade" id="{$safeId}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog {$safeSizeClass}">
                    <div class="modal-content">
                        <div class="modal-body" id="{$safeId}Content">
                            <div class="text-muted text-center py-4">Memuat konten Inject...</div>
                        </div>
                    </div>
                </div>
            </div>

            HTML;
        }

        return $output;
    }
}
