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
    | Wrapper (depth = 0)
    |--------------------------------------------------------------------------
    */
    'nav_tag_open'  => "<ul class=\"list-inline mb-0\">\n",
    'nav_tag_close' => "</ul>\n",

    /*
    |--------------------------------------------------------------------------
    | Parent with child (depth = 0, has children)
    |--------------------------------------------------------------------------
    */
    'parent_child_tag_open'        => "<div class='col-6 col-md-4'>\n",
    'parent_child_tag_open_active' => "<div class='col-6 col-md-4'>\n",
    'parent_child_tag_close'       => "</div>\n",
    'parent_tag_close'             => "</div>\n",
    'children_tag_close'           => "</div>\n",
    'parent_label_tag'             => "<h5 class='mb-2 mb-md-4'>%s</h5>\n",
    'parent_child_anchor'          => '',
    'parent_child_anchor_active'   => '',

    /*
    |--------------------------------------------------------------------------
    | Child wrapper (depth > 0)
    |--------------------------------------------------------------------------
    */
    'submenu_child_tag_open'  => "<ul class=\"list-inline mb-0\">\n",
    'submenu_child_tag_close' => "</ul>\n",

    /*
    |--------------------------------------------------------------------------
    | Parent with child (depth > 0)
    |--------------------------------------------------------------------------
    */
    'parent_tag_open'        => "<li class=\"list-inline-item\">\n",
    'parent_tag_open_active' => "<li class='nav-item active'>\n",
    'parent_tag_close'       => "\n",
    'parent_anchor'          => "<a class='nav-link' href='%s'>%s %s</a>\n",
    'parent_anchor_active'   => "<a class='nav-link active' href='%s'>%s %s</a>\n",
    'submenu_tag_open'       => '', // Tidak dipakai
    'submenu_tag_close'      => '',

    /*
    |--------------------------------------------------------------------------
    | Leaf item (tanpa child)
    |--------------------------------------------------------------------------
    */
    'item_tag_open'        => "<li class='list-inline-item'>\n",
    'item_tag_open_active' => "<li class='nav-item active'>\n",
    'item_tag_close'       => "</li>\n",
    'anchor'               => "<a class='nav-link' href='%s'>%s%s</a>\n",
    'anchor_active'        => "<a class='nav-link active' href='%s'>%s%s</a>\n",

    /*
    |--------------------------------------------------------------------------
    | Optional
    |--------------------------------------------------------------------------
    */
    'item_tag'           => "<li class='nav-item'>",
    'item_anchor'        => "<a class='nav-link' target='_self'  href='%s'>%s %s</a>",
    'item_anchor_active' => "<a class='nav-link active text-primary'  target='_self' href='%s'>%s %s</a>",
    'item_divider_open'  => "<li>\n",
    'anchor_divider'     => "<hr class='dropdown-divider'>\n",
    'item_divider_close' => "</li>\n",

    /*
    |--------------------------------------------------------------------------
    | Megamenu (optional - tidak dipakai)
    |--------------------------------------------------------------------------
    */
    'megamenu_tag_open'      => '',
    'megamenu_tag_close'     => '',
    'megamenu_anchor'        => '',
    'megamenu_content_open'  => '',
    'megamenu_content_close' => '',
    'megamenu_col_open'      => '',
    'megamenu_col_close'     => '',

    /*
    |--------------------------------------------------------------------------
    | Icon & badge
    |--------------------------------------------------------------------------
    */
    'toggle_icon'   => '',
    'icon_wrapper'  => "<i class='%s me-1'></i>",
    'badge_wrapper' => '',

];
