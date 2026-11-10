<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Field mapping
    |--------------------------------------------------------------------------
    */
    'menu_id'         => 'id',
    'menu_parent'     => 'parent_id',
    'menu_label'      => 'title',
    'menu_label_slug' => 'title_slug',
    'menu_icon'       => 'icon',
    'menu_url'        => 'url',
    'menu_order'      => 'sequence',
    'menu_position'   => 'position',
    'menu_badge'      => null,
    'menu_content'    => null,

    /*
    |--------------------------------------------------------------------------
    | Options
    |--------------------------------------------------------------------------
    */
    'item_active'       => 'active',
    'icon_position'     => 'left',
    'icon_img_base_url' => null,
    'is_megamenu'       => false,
    'menu_icons_list'   => null,
    '_additional_item'  => [],
    'uri_segment'       => 2,

    /*
    |--------------------------------------------------------------------------
    | Top level nav (depth=0)
    |--------------------------------------------------------------------------
    */
    'nav_tag_open'  => "<ul id='sidebarnav'>\n",
    'nav_tag_close' => "</ul>\n",

    // parent0 (depth=0): sidebar-item dengan submenu
    'parent_child_tag_open'        => "<li class='sidebar-item'>",
    'parent_child_tag_open_active' => "<li class='sidebar-item'>", // Adminmart tidak pakai class menu-open
    'children_tag_close'           => "</ul>\n</li>\n",

    // %s=url, %s=iconHtml, %s=label, %s=badge/toggle
    'parent_child_anchor'        => "<a class='sidebar-link has-arrow %s' href='%s' aria-expanded='false'>%s %s<span class='hide-menu'>%s</span></a>",
    'parent_child_anchor_active' => "<a class='sidebar-link has-arrow active %s' href='%s' aria-expanded='true'>%s %s<span class='hide-menu'>%s</span></a>",
    'submenu_child_tag_open'     => "<ul aria-expanded='false' class='collapse first-level'>\n",

    /*
    |--------------------------------------------------------------------------
    | Child level (depth>0)
    |--------------------------------------------------------------------------
    */
    'parent_tag_open'            => "<li class='sidebar-item'>",
    'parent_tag_open_active'     => "<li class='sidebar-item'>",
    'item_tag_open_child'        => "<li class='sidebar-item'>",
    'item_tag_open_child_active' => "<li class='sidebar-item'>",
    'parent_tag_close'           => "</li>\n",

    // %s=url, %s=iconHtml, %s=label, %s=badge/toggle
    'parent_anchor'        => "<a class='sidebar-link has-arrow' href='%s' aria-expanded='false'>%s<span class='hide-menu'>%s</span>%s</a>",
    'parent_anchor_active' => "<a class='sidebar-link has-arrow active' href='%s' aria-expanded='true'>%s<span class='hide-menu'>%s</span>%s</a>",

    'submenu_tag_open' => "<ul aria-expanded='false' class='collapse two-level'>\n",

    // item anak tanpa submenu
    'item_anchor_child'        => "<a href='%s' class='sidebar-link'>%s<span class='hide-menu'>%s</span></a>",
    'item_anchor_child_active' => "<a href='%s' class='sidebar-link active'>%s<span class='hide-menu'>%s</span></a>",

    /*
    |--------------------------------------------------------------------------
    | Leaf item (no children)
    |--------------------------------------------------------------------------
    */
    'item_tag_open'        => "<li class='sidebar-item'>",
    'item_tag_open_active' => "<li class='sidebar-item active'>",

    // %s=url, %s=iconHtml, %s=label
    'anchor'        => "<a href='%s' class='sidebar-link'>%s<span class='hide-menu'>%s</span></a>",
    'anchor_active' => "<a href='%s' class='sidebar-link active'>%s<span class='hide-menu'>%s</span></a>",

    'item_tag_close' => "</li>\n",

    // Leaf item depth=0 juga sama
    'item_tag'           => "<li class='sidebar-item%s'>",
    'item_anchor'        => "<a href='%s' class='sidebar-link'>%s<span class='hide-menu'>%s</span></a>",
    'item_anchor_active' => "<a href='%s' class='sidebar-link active'>%s<span class='hide-menu'>%s</span></a>",

    /*
    |--------------------------------------------------------------------------
    | Divider / Header
    |--------------------------------------------------------------------------
    */
    'item_divider_open'  => "<li class='nav-small-cap'>\n",
    'anchor_divider'     => "<i class='ti ti-dots nav-small-cap-icon fs-4'></i><span class='hide-menu'>%s</span>\n",
    'item_divider_close' => "</li>\n",

    /*
    |--------------------------------------------------------------------------
    | Megamenu (Adminmart tidak ada default, optional kalau mau bootstrap dropdown)
    |--------------------------------------------------------------------------
    */
    // 'megamenu_tag_open'  => "<li class='sidebar-item dropdown megamenu'>\n",
    // 'megamenu_tag_close' => "</li>\n",

    // 'megamenu_anchor' => "<a href='%s' class='sidebar-link dropdown-toggle' data-bs-toggle='dropdown'>%s<span class='hide-menu'>%s</span>%s</a>",

    // 'megamenu_content_open'  => "<div class='dropdown-menu'><div class='row'>",
    // 'megamenu_content_close' => "</div></div>\n",

    // 'megamenu_col_open'  => "<div class='col-md-3'><h6>%s</h6><ul class='list-unstyled'>",
    // 'megamenu_col_close' => "</ul></div>\n",

    /*
    |--------------------------------------------------------------------------
    | Icon & badge
    |--------------------------------------------------------------------------
    */
    'toggle_icon' => '', // Adminmart tidak pakai arrow terpisah, sudah dihandle class has-arrow
    // 'toggle_icon'  => "<span class='round-16 d-flex align-items-center justify-content-center'><i class='%s'></i></span>",
    'icon_wrapper'  => "<span class='d-flex'><i class='%s'></i></span>",
    'badge_wrapper' => '',
    // 'badge_wrapper' => "<span class='badge rounded-pill bg-primary ms-auto'>%s</span>",

];
