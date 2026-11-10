<?php

namespace App\Models;

use CodeIgniter\Model;

class AdminModel extends Model
{
    protected $table         = 'menus';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'parent_id',
        'title',
        'slug',
        'url',
        'icon',
        'position',
        'section',
        'sequence',
        'is_divider',
        'is_active',
    ];

    /**
     * Mengambil seluruh navigasi aktif berdasarkan section.
     */
    public function getAllNavigation(string $group = 'backend'): array
    {
        $menuItems = $this->where('section', $group)
            ->where('is_active', 1)
            ->orderBy('position', 'ASC')
            ->orderBy('sequence', 'ASC')
            ->findAll();

        $positions = [
            'sidebar',
            'apps',
            'quick_links',
            'topbar',
        ];

        $navigation = [];

        foreach ($positions as $position) {
            $items = array_values(array_filter(
                $menuItems,
                fn($item) => $item['position'] === $position
            ));

            $navigation[$position] = $this->buildTree($items);
        }

        return $navigation;
    }

    /**
     * Menu bertingkat berdasarkan posisi.
     */
    public function getNestedMenu(
        string $group = 'backend',
        string $position = 'sidebar'
    ): array {
        $menuItems = $this->where('position', $position)
            ->where('is_active', 1)
            ->where('section', $group)
            ->orderBy('sequence', 'ASC')
            ->findAll();

        return $this->buildTree($menuItems);
    }

    public function getAppsMenu(string $group = 'backend'): array
    {
        return $this->getNestedMenu($group, 'apps');
    }

    public function getQuickLinks(string $group = 'backend'): array
    {
        return $this->getNestedMenu($group, 'quick_links');
    }

    public function getNavbarApps(string $group = 'backend'): array
    {
        return $this->getNestedMenu($group, 'navbar');
    }

    /**
     * Membentuk struktur parent-child.
     */
    protected function buildTree(
        array $elements,
        $parentId = null
    ): array {
        $branch = [];

        foreach ($elements as $element) {
            if ((int) ($element['parent_id'] ?? 0) !== (int) ($parentId ?? 0)) {
                continue;
            }

            $children = $this->buildTree(
                $elements,
                $element['id']
            );

            if ($children !== []) {
                $element['children'] = $children;
            }

            $branch[] = $element;
        }

        return $branch;
    }
}

/*
<!-- /?php

namespace App\Models;

use CodeIgniter\Model;

class AdminModel extends Model
{
    protected $table         = 'menus';
    protected $primaryKey    = 'id';

    protected $allowedFields = [
        'parent_id',
        'title',
        'icon',
        'url',
        'sequence',
        'section',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $returnType    = 'array';

    public function getNestedMenu(
        string $group = 'backend',
        string $position = 'sidebar'
    ): array {
        $menuItems = $this->where('position', $position)
            ->where('is_active', 1)
            ->where('section', $group)
            ->orderBy('sequence', 'ASC')
            ->findAll();

        return $this->buildTree($menuItems);
    }

    public function getAppsMenu(string $group = 'backend'): array
    {
        return $this->getNestedMenu($group, 'apps');
    }

    public function getQuickLinks(string $group = 'backend'): array
    {
        return $this->getNestedMenu($group, 'quick_links');
    }

    public function getNavbarApps(string $group = 'backend'): array
    {
        return $this->getNestedMenu($group, 'navbar');
    }

    protected function buildTree(
        array $elements,
        $parentId = null
    ): array {
        $branch = [];

        foreach ($elements as $element) {
            if ((int) ($element['parent_id'] ?? 0) !== (int) ($parentId ?? 0)) {
                continue;
            }

            $children = $this->buildTree(
                $elements,
                $element['id']
            );

            if ($children !== []) {
                $element['children'] = $children;
            }

            $branch[] = $element;
        }

        return $branch;
    }
} */

/*<!-- ?php

namespace App\Models;

use CodeIgniter\Model;

class AdminModel extends Model
{
    protected $table         = 'menus';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'parent_id',
        'title',
        'icon',
        'url',
        'sequence',
        'section',
        // 'badge',
        // 'divider',
        'is_active'
    ];
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    public function getNestedMenu($group = 'backend', $position = 'sidebar')
    {
        $menuItems = $this->where('position', $position)
            ->where('is_active', 1)
            ->where('section', $group)
            ->orderBy('sequence', 'ASC')
            // ->orderBy('urutan', 'ASC')
            ->findAll();

        return $this->buildTree($menuItems);
    }

    protected function buildTree(array $elements, $parentId = null): array
    {
        $branch = [];

        foreach ($elements as $element) {
            if ((int) $element['parent_id'] === (int) $parentId) {
                $children = $this->buildTree($elements, $element['id']);
                if ($children) {
                    $element['children'] = $children;
                }
                $branch[] = $element;
            }
        }

        return $branch;
    }
}

public function getMenuTree(): array
{
    $builder = $this->builder();
    $builder->where('active', 1)
            ->orderBy('position', 'ASC');

    $menus = $builder->get()->getResultArray();

    return $this->buildTree($menus);
}

protected function buildTree(array $elements, $parentId = null): array
{
    $branch = [];

    foreach ($elements as $element) {
        if ((int) $element['parent_id'] === (int) $parentId) {
            $children = $this->buildTree($elements, $element['id']);
            if ($children) {
                $element['children'] = $children;
            }
            $branch[] = $element;
        }
    }

    return $branch;
}
 *
 *
 */
