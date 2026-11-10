<?php

return [
    /*
    |------------------------------------------------------------------
    | Field mapping
    |------------------------------------------------------------------
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
    |------------------------------------------------------------------
    | Options
    |------------------------------------------------------------------
    */
    'item_active'       => 'active',
    'icon_position'     => 'left',
    'icon_img_base_url' => null,
    'menu_icons_list'   => null,
    '_additional_item'  => [],
    'uri_segment'       => 2,

    /*
    |------------------------------------------------------------------
    | Top level nav (depth=0)
    |------------------------------------------------------------------
    */
    'nav_tag_open'  => "<ul class='navbar-nav navbar-nav-scroll ms-auto'>\n",
    'nav_tag_close' => "</ul>\n",

    // parent0 = depth=0
    'parent_child_tag_open'        => "<li class='nav-item dropdown%s'>",
    'parent_child_tag_open_active' => "<li class='nav-item dropdown%s active'>",
    // %s=url, %s=labelWithIcon, %s=toggleIcon
    'parent_child_anchor'        => "<a class='nav-link dropdown-toggle' href='%s' data-bs-toggle='dropdown'>%s%s%s</a>",
    'parent_child_anchor_active' => "<a class='nav-link dropdown-toggle active' href='%s' data-bs-toggle='dropdown'>%s%s%s</a>",
    'submenu_child_tag_open'     => "<ul class='dropdown-menu' aria-labelledby='accounntMenu'>",

    /*
    |------------------------------------------------------------------
    | Child level (depth>0)
    |------------------------------------------------------------------
    */
    'parent_tag_open'        => "<li class='dropdown-submenu dropend%s'>",
    'parent_tag_open_active' => "<li class='dropdown-submenu dropend%s active'>",
    // %s=url, %s=labelWithIcon, %s=toggleIcon
    'parent_anchor'        => "<a class='dropdown-item dropdown-toggle' href='%s'>%s%s%s</a>",
    'parent_anchor_active' => "<a class='dropdown-item dropdown-toggle active' href='%s'>%s%s%s</a>",
    'submenu_tag_open'     => "<ul class='dropdown-menu dropdown-menu-start' data-bs-popper='none'>",

    /*
    |------------------------------------------------------------------
    | Leaf item (no children)
    |------------------------------------------------------------------
    */
    'item_tag_open'              => "\n" . "<li class='%s'>",
    'item_tag_open_active'       => "\n" . "<li class='%s'>",
    'item_tag_open_child'        => "\n" . "<li class='%s'>",
    'item_tag_open_child_active' => "\n" . "<li class='%s'>",
    // %s=url, %s=iconHtml, %s=label
    'anchor'         => "\n" . "<a class='dropdown-item' href='%s'>%s%s</a>",
    'anchor_active'  => "\n" . "<a class='dropdown-item active' href='%s'>%s%s</a>",
    'item_tag_close' => "</li>\n",

    // Untuk leaf item depth=0
    'item_tag' => "<li class='nav-item'>",
    // 'item0_tag_open_active'  => "<li class='nav-item active'>",
    'item_anchor'        => "<a class='nav-link' href='%s'>%s%s</a>",
    'item_anchor_active' => "<a class='nav-link active' href='%s'>%s%s</a>",

    /*
    |------------------------------------------------------------------
    | Divider
    |------------------------------------------------------------------
    */
    'item_divider_open'  => "<li>\n",
    'anchor_divider'     => "<hr class='dropdown-divider'>\n",
    'item_divider_close' => "</li>\n",

    /*
    |------------------------------------------------------------------
    | Megamenu (opsional)
    |------------------------------------------------------------------
    */
    'megamenu_tag_open'      => "<li class='nav-item dropdown dropdown-fullwidth'>\n",
    'megamenu_tag_close'     => "</li>\n",
    'megamenu_anchor'        => "<a class='nav-link dropdown-toggle' href='%s' data-bs-toggle='dropdown'>%s%s%s</a>",
    'megamenu_content_open'  => "<div class='dropdown-menu dropdown-menu-end pb-0'>\n<div class='row p-4 g-4'>",
    'megamenu_content_close' => "</div></div>\n",
    'megamenu_col_open'      => "<div class='col-xl-3'>\n<h6 class='mb-0'>%s</h6><hr>\n<ul class='list-unstyled'>",
    'megamenu_col_close'     => "</ul></div>\n",

    /*
    |------------------------------------------------------------------
    | Icon & badge
    |------------------------------------------------------------------
    */
    'toggle_icon'   => '', // default for parent, kosong karena theme sudah pakai fa-angle di HTML
    'icon_wrapper'  => '<i class="%s"></i> ',
    'badge_wrapper' => '', // '<span class="badge bg-primary ms-1">%s</span>',

];
