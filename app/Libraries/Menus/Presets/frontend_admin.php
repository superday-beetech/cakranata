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
    'nav_tag_open'  => '',
    'nav_tag_close' => '',

    // parent (punya child) pakai collapse
    // %s=collapse id, %s=labelWithIcon, %s=toggleIcon
    'parent_child_tag_open'        => "<li class='nav-item'>",
    'parent_child_tag_open_active' => "<li class='nav-item active'>",
    'parent_child_anchor'          => "<a class='nav-link' data-bs-toggle='collapse' href='#%s' role='button' aria-expanded='false' aria-controls='%s'>%s%s</a>",
    'parent_child_anchor_active'   => "<a class='nav-link active' data-bs-toggle='collapse' href='#%s' role='button' aria-expanded='true' aria-controls='%s'>%s%s</a>",
    'submenu_child_tag_open'       => "<ul class='nav collapse flex-column' id='%s' data-bs-parent='#navbar-sidebar'>",
'collapse_parent_child_anchor'     => "<a class='nav-link' data-bs-toggle='collapse' href='#%s' role='button' aria-expanded='false' aria-controls='%s'>%s%s</a>",
'submenu_collapse_tag_open'        => "<ul class='nav collapse flex-column' id='%s' data-bs-parent='#navbar-sidebar'>",
    /*
    |------------------------------------------------------------------
    | Child level (depth>0) pakai dropdown-submenu
    |------------------------------------------------------------------
    */
    'parent_tag_open'        => "<li class='dropdown-submenu dropend%s'>",
    'parent_tag_open_active' => "<li class='dropdown-submenu dropend%s active'>",
    'parent_anchor'          => "<a class='dropdown-item dropdown-toggle' href='%s'>%s%s%s</a>",
    'parent_anchor_active'   => "<a class='dropdown-item dropdown-toggle active' href='%s'>%s%s%s</a>",
    'submenu_tag_open'       => "<ul class='dropdown-menu dropdown-menu-start' data-bs-popper='none'>",

    /*
    |------------------------------------------------------------------
    | Leaf item (no children)
    |------------------------------------------------------------------
    */
    'item_tag_open'        => "\n" . "<li class='nav-item%s'>",
    'item_tag_open_active' => "\n" . "<li class='nav-item active%s'>",
    'anchor'               => "\n" . "<a class='nav-link' href='%s'>%s%s</a>",
    'anchor_active'        => "\n" . "<a class='nav-link active' href='%s'>%s%s</a>",
    'item_tag_close'       => "</li>\n",

    // depth=0 no child
    'item_tag'           => "<li class='nav-item'>",
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
    'toggle_icon'   => '',
    'icon_wrapper'  => '<i class="%s"></i> ',
    'badge_wrapper' => '',

];
