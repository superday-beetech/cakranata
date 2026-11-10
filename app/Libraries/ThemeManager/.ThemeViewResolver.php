<?php

namespace App\Libraries\ThemeManager;

class ThemeViewResolver
{
    public function detectModule(): string
    {
        $router = service('router');

        $controller = trim(
            (string) ($router?->controllerName() ?? ''),
            '\\'
        );

        $controller = explode('::', $controller, 2)[0];

        if (
            preg_match(
                '~^Modules\\\\([a-zA-Z0-9_]+)(?:\\\\|$)~',
                $controller,
                $matches
            )
        ) {
            return $matches[1];
        }

        return 'App';
    }

    public function locateView(
        string $view,
        string $theme
    ): string {
        $module = $this->detectModule();

        $view = trim(
            str_replace('\\', '/', $view),
            '/'
        );

        $paths = [
            ROOTPATH . "Modules/{$module}/Views/themes/{$theme}/{$view}.php",
            ROOTPATH . "Modules/{$module}/Views/{$view}.php",
            APPPATH . "Views/themes/{$theme}/{$view}.php",
            APPPATH . "Views/{$view}.php",
        ];

        foreach ($paths as $path) {
            $path = str_replace(
                ['/', '\\'],
                DIRECTORY_SEPARATOR,
                $path
            );

            if (! is_file($path)) {
                continue;
            }

            $relativePath = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                substr($path, strlen(ROOTPATH))
            );

            $relativePath = preg_replace(
                '/\.php$/i',
                '',
                $relativePath
            );

            if (str_starts_with($relativePath, 'Modules/')) {
                return str_replace('/', '\\', $relativePath);
            }

            if (str_starts_with($relativePath, 'app/Views/')) {
                return substr(
                    $relativePath,
                    strlen('app/Views/')
                );
            }

            return $relativePath;
        }

        // Pertahankan fallback theme lama.
        return "themes/{$theme}/{$view}";
    }
}
