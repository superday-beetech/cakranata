<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Field mapping ke database
    |--------------------------------------------------------------------------
    */
    'menu_id'         => 'id_menu',
    'menu_label'      => 'nama_menu',
    'menu_label_slug' => 'nama_menu_slug',
    'menu_url'        => 'link',
    'menu_parent'     => 'id_parent',
    'menu_order'      => 'urutan',
    'menu_icon'       => 'menu_icon',
    'menu_badge'      => 'badge',
    'menu_content'    => 'content',

    /*
    |--------------------------------------------------------------------------
    | Render options
    |--------------------------------------------------------------------------
    */
    'icon_position'     => 'left', // or 'right'
    'icon_img_base_url' => null, // contoh: base_url('uploads/icons/')
    'menu_icons_list'   => null, // kalau punya list icon tertentu
    '_additional_item'  => [],   // item tambahan manual
    'uri_segment'       => 2,    // segmen untuk detect active

    /*
    |--------------------------------------------------------------------------
    | Tag template & markup
    |--------------------------------------------------------------------------
    */

    // Wrapper nav
    'nav_tag_open'  => "<ul class=\"navbar-nav\">\n",
    'nav_tag_close' => "</ul>\n",

    // Item biasa (leaf)
    'item_tag_open'  => "\t<li class=\"nav-item%s\">",
    'item_tag_close' => "</li>\n",

    // Active item
    'item_tag_open_active'  => "\t<li class=\"nav-item active%s\">",
    'item_tag_close_active' => "</li>\n",

    // Anchor leaf
    // %s=url, %s=icon html, %s=label
    'anchor'        => '<a href="%s" class="nav-link">%s%s</a>',
    'anchor_active' => '<a href="%s" class="nav-link active">%s%s</a>',

    /*
    |--------------------------------------------------------------------------
    | Parent item (dropdown)
    |--------------------------------------------------------------------------
    */
    'parent_tag_open'         => "\t<li class=\"nav-item dropdown%s\">",
    'parent_tag_close'        => "</li>\n",
    'parent_tag_open_active'  => "\t<li class=\"nav-item dropdown active%s\">",
    'parent_tag_close_active' => "</li>\n",

    // %s=url, %s=icon html, %s=label, %s=toggle_icon
    'parent_anchor'        => '<a href="%s" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">%s%s%s</a>',
    'parent_anchor_active' => '<a href="%s" class="nav-link dropdown-toggle active" data-bs-toggle="dropdown">%s%s%s</a>',

    // Submenu ul
    'submenu_tag_open'  => "\n\t<ul class=\"dropdown-menu\">",
    'submenu_tag_close' => "</ul>\n",

    /*
    |--------------------------------------------------------------------------
    | Divider item
    |--------------------------------------------------------------------------
    */
    'divider' => "\t<li><hr class=\"dropdown-divider\"></li>\n",

    /*
    |--------------------------------------------------------------------------
    | Megamenu (opsional, jika theme support)
    |--------------------------------------------------------------------------
    */
    'megamenu_tag_open'  => "\t<li class=\"nav-item dropdown dropdown-fullwidth\">\n",
    'megamenu_tag_close' => "\t</li>\n",
    // parent anchor
    'megamenu_anchor' => '<a href="%s" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">%s%s%s</a>',
    // content wrapper
    'megamenu_content_open'  => "<div class=\"dropdown-menu dropdown-menu-end pb-0\">\n<div class=\"row p-4 g-4\">",
    'megamenu_content_close' => "</div></div>\n",
    // column
    'megamenu_col_open'  => "<div class=\"col-xl-3\">\n<h6 class=\"mb-0\">%s</h6><hr>\n<ul class=\"list-unstyled\">",
    'megamenu_col_close' => "</ul></div>\n",

    /*
    |--------------------------------------------------------------------------
    | Icon & badge
    |--------------------------------------------------------------------------
    */
    'toggle_icon'   => '<i class="right fas fa-angle-left"></i>',    // default for parent
    'icon_wrapper'  => '<i class="%s"></i> ',                        // wrap font icon
    'badge_wrapper' => '<span class="badge bg-primary ms-1">%s</span>', // badge html

];
