<?php

namespace App\Models;

use CodeIgniter\Model;

class MenuModel extends Model
{
    protected $table         = 'menus';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'parent_id',
        'nama_menu',
        'link',
        'menu_icon',
        'position',
        'urutan',
        'badge',
        'megamenu',
        'menu_group_id',
        'aktif'
    ];
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    public function getNestedMenu($group = 'backend', $position = 'sidebar')
    {
        $menuItems = $this->where('position', $position)
            ->where('is_active', 1)
            ->where('section', $group)
            // ->where('position', $position)
            ->orderBy('sequence', 'ASC')
            // ->orderBy('urutan', 'ASC')
            ->findAll();

        return $this->buildTree($menuItems);
    }

    protected function buildTree(array $elements, $parentId = null): array
    {
        $branch = [];

        foreach ($elements as $element) {
            if ((int) $element['id_parent'] === (int) $parentId) {
                $children = $this->buildTree($elements, $element['id_menu']);
                if ($children) {
                    $element['children'] = $children;
                }
                $branch[] = $element;
            }
        }

        return $branch;
    }
}

/**
 * // Di model MenuModel.php
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
