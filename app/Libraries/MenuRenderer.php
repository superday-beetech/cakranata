<?php

namespace App\Libraries;

class MenuRenderer
{
    protected array $preset;
    protected array $fieldMap;

    public function __construct(array $preset, array $fieldMap)
    {
        $this->preset   = $preset;
        $this->fieldMap = $fieldMap;
    }

    public function buildMenu(array $items, int $depth = 0): string
    {
        $html = '';
        usort($items, [$this, 'sortByOrder']);

        if ($depth === 0) {
            $html .= $this->preset['nav_tag_open'] ?? "<ul>\n";
        }

        foreach ($items as $item) {
            $hasChildren = !empty($item['children']);
            $childCount  = !empty($item['children']) ? count($item['children']) : 0;
            // if (empty($item['children'])) {
            //     continue; // skip kalau nggak ada child
            // }
            $isActive = !empty($item['active']);
            $url      = $item[$this->fieldMap['menu_url']] ?? '#';
            $label    = esc($item[$this->fieldMap['menu_label']] ?? 'No Label');
            $icon     = esc($item[$this->fieldMap['menu_icon']] ?? 'bi bi-circle');
            $badge    = esc($item[$this->fieldMap['menu_megamenu']] ?? false);
            $type     = (bool) ($badge);
            // dd($childCount);
            $iconHtml   = $icon ? sprintf($this->preset['icon_wrapper'] ?? '', $icon) : '';
            $iconAdmin  = $icon ? sprintf($this->preset['icon_wrapper_megamenu'] ?? '', $icon) : '';
            $badgeHtml  = $badge ? sprintf($this->preset['badge_wrapper'] ?? '', $badge) : '';
            $toggleIcon = $this->preset['toggle_icon'] ?? '';
            $finalLabel = $label . $badgeHtml;
            $twoCoulumn = null;
            // print_r($item);
            // $totalChild = $this->countDescendants($item);
            if ($childCount > 9) {
                $twoCoulumn = 'two-column';
            }
            // Divider by menu_url
            if ($url === 'divider') {
                $html .= $this->preset['item_divider_open']
                    . sprintf($this->preset['anchor_divider'], strtoupper($label  ?? ''))
                    . $this->preset['item_divider_close'];

                continue;
            }

            if (($type === '1' || $type) && $hasChildren && $this->preset['is_megamenu']) {
                $html .= $this->renderMegamenu($item, $url, $iconAdmin, $finalLabel, $toggleIcon, $twoCoulumn);
            } elseif ($hasChildren) {
                $html .= $this->renderParent($item, $url, $iconHtml, $finalLabel, $toggleIcon, $isActive, $depth, $twoCoulumn);
            } else {
                $html .= $this->renderItem($url, $iconHtml, $finalLabel, $isActive, $depth);
            }
        }

        if ($depth === 0) {
            $html .= $this->preset['nav_tag_close'] ?? "</ul>\n";
        }

        return $html;
    }

    protected function renderItem(string $url, string $iconHtml, string $label, bool $isActive, int $depth): string
    {
        $itemAnchor = $depth === 0
            ? ($isActive ? $this->preset['item_anchor_active'] ?? $this->preset['item_anchor'] : $this->preset['item_anchor'])
            : ($isActive ? $this->preset['anchor_active'] ?? $this->preset['anchor'] : $this->preset['anchor']);

        $liClass = $isActive ? ("\t" . $this->preset['item_active'] ?? ' active') : '';

        $liTag = $depth === 0
            ? ($isActive
                ? sprintf($this->preset['item_tag_open'] ?? $this->preset['item_tag_open'], $liClass)
                : sprintf($this->preset['item_tag_open'] ?? '<li>', $liClass))
            : ($isActive
                ? sprintf($this->preset['item_tag_open_child'] ?? $this->preset['item_tag_open'], $liClass)
                : sprintf($this->preset['item_tag_open_child'] ?? '<li>', $liClass));

        // Hitung placeholder
        $placeholders      = substr_count($itemAnchor, '%s');
        list($url, $attrs) = normalize_url($url);

        if ($placeholders === 3) {
            $anchor = sprintf($itemAnchor, $url, $iconHtml, $label);
        } elseif ($placeholders === 2) {
            $anchor = sprintf($itemAnchor, $url, $label);
        } else {
            $anchor = sprintf($itemAnchor, $url, $iconHtml, $label);
        }

        return $liTag . $anchor . ($this->preset['item_tag_close'] ?? '</li>');
    }

    protected function renderParent(array $item, string $url, string $iconHtml, string $label, string $toggleIcon, bool $isActive, int $depth, $twoCoulumn): string
    {
        $parentUrl = 'javascript:;';

        // $collapseId = 'collapse' . $item[$this->fieldMap['menu_id']];
        if (isset($this->preset['submenu_tag_open_custom'])) {
            $submenuTagOpen = $this->preset['submenu_tag_open_custom'] ?? '<ul>';
        } else {
            $submenuTagOpen = $this->preset['submenu_tag_open'] ?? '<ul>';
        }
        if ($depth === 0) {
            if ($depth === 0 && isset($this->preset['collapse_parent_child_anchor'])) {
                $li = $isActive
                    ? sprintf($this->preset['parent_tag_open_active'] ?? $this->preset['parent_tag_open'], $this->preset['item_active_class'] ?? ' ')
                    : sprintf($this->preset['parent_tag_open'], '');

                $anchor = $isActive
                    ? sprintf($this->preset['parent_anchor_active'], $parentUrl, $iconHtml, $label, $toggleIcon)
                    : sprintf($this->preset['parent_anchor'], $parentUrl, $iconHtml, $label, $toggleIcon);

                if (isset($this->preset['submenu_tag_open_custom'])) {
                    $submenuOpen = $this->preset['submenu_tag_open_custom'] ?? '<ul>';
                } else {
                    $submenuOpen = $this->preset['submenu_tag_open'] ?? '<ul>';
                }
            } else {
                // fallback: original logic
                $li = $isActive
                    ? sprintf($this->preset['parent_child_tag_open_active'] ?? $this->preset['parent_child_tag_open'], '')
                    : sprintf($this->preset['parent_child_tag_open'], '');
                if (isset($this->preset['parent_label_tag'])) {
                    $anchor = $anchor = sprintf($this->preset['parent_label_tag'], $label);
                } else {

                    $anchor = $isActive
                        ? sprintf($this->preset['parent_child_anchor_active'], $twoCoulumn, 'javascript:;', '', $iconHtml, $label, $toggleIcon)
                        : sprintf($this->preset['parent_child_anchor'], $twoCoulumn, 'javascript:;', '', $iconHtml, $label, $toggleIcon);
                }
                // print_r(sprintf($this->preset['parent_child_anchor'], $twoCoulumn, 'javascript:;', '', $iconHtml, $label, $toggleIcon));
                // exit;
                // $submenuOpen = $this->preset['submenu_child_tag_open'] ?? '<ul>';
                $submenuOpen = $depth === 0 ? ($this->preset['submenu_child_tag_open'] ?? '<ul>') : $submenuTagOpen;
            }
        } else {
            $li = $isActive
                ? sprintf($this->preset['parent_tag_open_active'] ?? $this->preset['parent_tag_open'], $this->preset['item_active_class'] ?? ' ')
                : sprintf($this->preset['parent_tag_open'], '');

            $anchor = $isActive
                ? sprintf($this->preset['parent_anchor_active'], $parentUrl, $iconHtml, $label, $toggleIcon)
                : sprintf($this->preset['parent_anchor'], $parentUrl, $iconHtml, $label, $toggleIcon);

            // $submenuOpen = $this->preset['submenu_tag_open'] ?? '<ul>';
            $submenuOpen = $depth === 0
                ? ($this->preset['submenu_child_tag_open'] ?? '<ul>')
                : $submenuTagOpen;
        }

        $childrenHtml = $this->buildMenu($item['children'], $depth + 1);

        return $li . $anchor . $submenuOpen . $childrenHtml
            . ($this->preset['children_tag_close'] ?? '</ul>')
            . ($this->preset['parent_tag_close'] ?? '</li>');
    }

    protected function renderMegamenu(array $item, string $url, string $iconHtml, string $label, string $toggleIcon, $twoCoulumn): string
    {

        $presets = $this->preset;
        // $presets = array_merge($defaultPresets, $this->preset);

        // Check active state
        $hasActiveChild = $this->hasActiveChild($item);
        $anchorClass    = $hasActiveChild ? $presets['megamenu_anchor_parent_active'] : $presets['megamenu_anchor_parent'];

        // Open tag
        // $html = "<li class='{$liClass}'>\n";
        $html = "\n\n" . $presets['megamenu_tag_open'] . "\n";
        $icon = $iconHtml;
        // Render anchor tag
        $html .= $this->renderMegamenuAnchor($url, $icon, $label, $toggleIcon, $anchorClass);

        // Render content
        $html .= $presets['megamenu_content_open'];
        $html .= $this->renderMegamenuColumns($item['children'], $presets);
        $html .= $presets['megamenu_content_close'];

        // Close tag
        // $html .= "</li>\n";
        $html .= "\n\n" . $presets['megamenu_tag_close'] . "\n";

        return $html;
    }

    protected function hasActiveChild(array $item): bool
    {
        foreach ($item['children'] as $col) {
            foreach ($col['children'] ?? [] as $sub) {
                if (!empty($sub['active'])) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function renderMegamenuAnchor(string $url, string $icon, string $label, string $toggleIcon, string $anchorClass): string
    {
        return sprintf(
            $anchorClass,
            $url,
            $icon,
            $label,
            $toggleIcon
        );
    }

    protected function renderMegamenuColumns(array $columns, array $presets): string
    {
        $html = '';

        foreach ($columns as $col) {
            $colLabel = esc($col[$this->fieldMap['menu_label']] ?? 'No Title');
            $colUrl   = esc($col[$this->fieldMap['menu_url']] ?? '#');
            $subIcon  = esc($col[$this->fieldMap['menu_icon']] ?? 'bi bi-circle');
            $html .= isset($presets['megamenu_col_open1']) ? sprintf($presets['megamenu_col_open1'], $colUrl, sprintf($presets['icon_only'], $subIcon), $colLabel) : sprintf($presets['megamenu_col_open'], sprintf($presets['icon_only'], $subIcon), $colLabel);

            foreach ($col['children'] ?? [] as $sub) {
                $html .= $this->renderMegamenuItem($sub, $presets);
            }

            $html .= $presets['megamenu_col_close'] . "\n";
        }

        return $html;
    }

    protected function renderMegamenuItem(array $sub, array $presets): string
    {
        $subUrl      = esc($sub[$this->fieldMap['menu_url']] ?? '#');
        $subLabel    = esc($sub[$this->fieldMap['menu_label']] ?? 'No Label');
        $subIcon     = esc($sub[$this->fieldMap['menu_icon']] ?? 'bi bi-circle');
        $subIconHtml = $subIcon ? sprintf($presets['icon_wrapper_megamenu'], $subIcon) ?? sprintf($presets['icon_only'], $subIcon) : '';
        list($url)   = normalize_url($subUrl  ?? '');

        $itemAnchor = !empty($sub['active']) ? $presets['megamenu_anchor_child_active'] : $presets['megamenu_anchor_child'];

        return $presets['megamenu_tag_child_open'] . sprintf($itemAnchor, $url, "$subIconHtml", $subLabel) . $presets['megamenu_tag_child_close'];
        // return $presets['megamenu_tag_child_open'] . "<a class='{$itemClass}' href='{$url}'>{$subIconHtml}{$subLabel}</a>" . $presets['megamenu_tag_child_close'];
    }

    protected function renderMegamenuX(array $item, string $url, string $iconHtml, string $label, string $toggleIcon): string
    {
        // Cek active
        $hasActiveChild = false;
        foreach ($item['children'] as $col) {
            foreach ($col['children'] ?? [] as $sub) {
                if (!empty($sub['active'])) {
                    $hasActiveChild = true;

                    break 2;
                }
            }
        }

        $liClass = 'nav-item dropdown dropdown-fullwidth';
        if ($hasActiveChild) {
            $liClass .= ' ' . ($this->preset['item_active_class'] ?? 'active');
        }

        $anchorClass = 'nav-link dropdown-toggle';
        if ($hasActiveChild) {
            $anchorClass .= ' ' . ($this->preset['item_active_class'] ?? 'active');
        }

        $html = "<li class='{$liClass}'>\n";
        $html .= sprintf(
            "<a class='{$anchorClass}' href='%s' data-bs-toggle='dropdown'>%s%s%s</a>",
            $url,
            $iconHtml,
            $label,
            $toggleIcon
        );

        $html .= $this->preset['megamenu_content_open'] ?? "<div class='dropdown-menu'><div class='row'>";

        foreach ($item['children'] as $col) {
            $colLabel = esc($col[$this->fieldMap['menu_label']] ?? 'No Title');
            $html .= sprintf($this->preset['megamenu_col_open'] ?? '<div><h6>%s</h6><ul>', $colLabel);

            foreach ($col['children'] ?? [] as $sub) {
                $subUrl      = esc($sub[$this->fieldMap['menu_url']] ?? '#');
                $subLabel    = esc($sub[$this->fieldMap['menu_label']] ?? 'No Label');
                $subIcon     = esc($sub[$this->fieldMap['menu_icon']] ?? '');
                $subIconHtml = $subIcon ? sprintf($this->preset['icon_wrapper'] ?? '', $subIcon) : '';
                // $url         = normalize_url($subUrl);
                list($url) = normalize_url($subUrl ?? '');

                $subActive = !empty($sub['active']) ? ' active' : '';
                $html .= "<li class='{$subActive}'><a class='dropdown-item{$subActive}' href='{$url}'>{$subIconHtml}{$subLabel}</a></li>";
            }

            $html .= $this->preset['megamenu_col_close'] ?? "</ul></div>\n";
        }

        $html .= $this->preset['megamenu_content_close'] ?? "</div></div>\n";
        $html .= $this->preset['megamenu_tag_close']     ?? "</li>\n";

        return $html;
    }

    private function sortByOrder($a, $b)
    {
        $field = $this->fieldMap['menu_order'];

        return ($a[$field] ?? 0) - ($b[$field] ?? 0);
    }

    public function countChildren(array $item): int
    {
        if (empty($item['children'])) {
            return 0;
        }

        $count = count($item['children']);
        foreach ($item['children'] as $child) {
            $count += $this->countChildren($child); // rekursif
        }

        return $count;
    }

    public function countDescendants(array $item): int
    {
        $count = 0;

        if (!empty($item['children'])) {
            $count += count($item['children']);
            foreach ($item['children'] as $child) {
                $count += $this->countDescendants($child); // hitung grandchild
            }
        }

        return $count;
    }
}
