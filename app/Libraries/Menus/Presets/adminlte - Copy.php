<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Field mapping
    |--------------------------------------------------------------------------
    */
    'menu_id'         => 'id_menu',
    'menu_parent'     => 'id_parent',
    'menu_label'      => 'nama_menu',
    'menu_label_slug' => 'nama_menu_slug',
    'menu_icon'       => 'menu_icon',
    'menu_url'        => 'link',
    'menu_order'      => 'sort_order',
    'menu_position'   => 'position',
    'menu_badge'      => 'badge',
    'menu_content'    => 'content',

    /*
    |--------------------------------------------------------------------------
    | Options
    |--------------------------------------------------------------------------
    */
    'item_active'       => 'active',
    'icon_position'     => 'left',
    'icon_img_base_url' => null,
    'menu_icons_list'   => null,
    '_additional_item'  => [],
    'uri_segment'       => 2,

    /*
    |--------------------------------------------------------------------------
    | Top level nav (depth=0)
    |--------------------------------------------------------------------------
    */
    'nav_tag_open'  => "<ul class='nav nav-pills nav-sidebar flex-column' data-widget='treeview' role='menu'>\n",
    'nav_tag_close' => "</ul>\n",

    // parent0 (depth=0): nav-item has-treeview
    'parent_child_tag_open'        => "<li class='nav-item has-treeview%s'>",
    'parent_child_tag_open_active' => "<li class='nav-item has-treeview%s menu-open'>",

    // %s=url, %s=labelWithIcon, %s=toggleIcon
    'parent_child_anchor'        => "<a href='%s' class='nav-link'>%s%s%s</a>",
    'parent_child_anchor_active' => "<a href='%s' class='nav-link active'>%s%s%s</a>",

    'submenu_child_tag_open' => "<ul class='nav nav-treeview'>",

    /*
    |--------------------------------------------------------------------------
    | Child level (depth>0)
    |--------------------------------------------------------------------------
    */
    'parent_tag_open'        => "<li class='nav-item has-treeview%s'>",
    'parent_tag_open_active' => "<li class='nav-item has-treeview%s menu-open'>",

    // %s=url, %s=labelWithIcon, %s=toggleIcon
    'parent_anchor'        => "<a href='%s' class='nav-link'>%s %s %s</a>",
    'parent_anchor_active' => "<a href='%s' class='nav-link active'>%s %s %s</a>",

    'submenu_tag_open' => "<ul class='nav nav-treeview'>",

    /*
    |--------------------------------------------------------------------------
    | Leaf item (no children)
    |--------------------------------------------------------------------------
    */
    'item_tag_open'        => "<li class='nav-item'>",
    'item_tag_open_active' => "<li class='nav-item active'>",

    // %s=url, %s=iconHtml, %s=label
    'anchor'        => "<a href='%s' class='nav-link'>%s%s</a>",
    'anchor_active' => "<a href='%s' class='nav-link active'>%s%s</a>",

    'item_tag_close' => "</li>\n",

    // Leaf item depth=0 juga sama
    'item_tag' => "<li class='nav-item%s'>",
    // 'item0_tag_open_active'  => "<li class='nav-item active'>",
    'item_anchor'        => "<a href='%s' class='nav-link'>%s%s</a>",
    'item_anchor_active' => "<a href='%s' class='nav-link active'>%s%s</a>",
// 'parent_label_tag'       => '',
    /*
    |--------------------------------------------------------------------------
    | Divider
    |--------------------------------------------------------------------------
    */
    'item_divider_open'  => "<li class='nav-header'>\n",
    'anchor_divider'     => "%s\n",
    'item_divider_close' => "</li>\n",

    /*
    |--------------------------------------------------------------------------
    | Megamenu (opsional, rarely used in AdminLTE)
    |--------------------------------------------------------------------------
    */
    'megamenu_tag_open'  => "<li class='nav-item dropdown megamenu'>\n",
    'megamenu_tag_close' => "</li>\n",

    'megamenu_anchor' => "<a href='%s' class='nav-link dropdown-toggle' data-bs-toggle='dropdown'>%s%s%s</a>",

    'megamenu_content_open'  => "<div class='dropdown-menu'><div class='row'>",
    'megamenu_content_close' => "</div></div>\n",

    'megamenu_col_open'  => "<div class='col-md-3'><h6>%s</h6><ul class='list-unstyled'>",
    'megamenu_col_close' => "</ul></div>\n",

    /*
    |--------------------------------------------------------------------------
    | Icon & badge
    |--------------------------------------------------------------------------
    */
    'toggle_icon'   => "<i class='right fas fa-angle-left'></i>", // default for parent
    'icon_wrapper'  => "<i class='nav-icon %s'></i> ",
    'badge_wrapper' => '', // atau '<span class="badge badge-info right">%s</span>',

];
