<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\RequestInterface;
use Config\Database;

class MenuRouteBridge
{
    protected BaseConnection $db;
    protected RequestInterface $request;

    public function __construct(
        ?RequestInterface $request = null,
        ?BaseConnection $db = null
    ) {
        $this->request = $request ?? service('request');
        $this->db = $db ?? Database::connect();
    }

    /**
     * Mencari menu aktif yang URL-nya cocok dengan request saat ini.
     */
    public function resolveCurrentMenu(): ?array
    {
        $currentPath = $this->normalizePath(
            $this->request->getUri()->getPath()
        );

        if ($currentPath === null) {
            return null;
        }

        $menus = $this->db->table('menus')
            ->select([
                'id',
                'title',
                'url',
                'section',
                'position',
                'parent_id',
                'is_active',
            ])
            ->where('is_active', 1)
            ->orderBy('sequence', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($menus as $menu) {
            $menuPath = $this->normalizePath(
                (string) ($menu['url'] ?? '')
            );

            if ($menuPath === null || $menuPath !== $currentPath) {
                continue;
            }

            $menu['section'] = strtolower(
                trim((string) ($menu['section'] ?? 'backend'))
            );

            return $menu;
        }

        return null;
    }

    /**
     * Menyamakan URL menu dengan path request.
     */
    protected function normalizePath(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || $url === '#') {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return null;
        }

        // Abaikan URL eksternal.
        if (isset($parts['host'])) {
            $currentHost = $this->request->getUri()->getHost();

            if (strcasecmp($parts['host'], $currentHost) !== 0) {
                return null;
            }
        }

        $path = trim((string) ($parts['path'] ?? ''), '/');

        // Hilangkan base path proyek, misalnya /cakranata.
        $basePath = trim(
            (string) parse_url(site_url(), PHP_URL_PATH),
            '/'
        );

        if (
            $basePath !== ''
            && ($path === $basePath
                || str_starts_with($path, $basePath . '/'))
        ) {
            $path = substr($path, strlen($basePath));
            $path = trim($path, '/');
        }

        return strtolower($path);
    }
}
