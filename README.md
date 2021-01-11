# WP_Table Custom Toggle Column
Add a toggle column to WP_Table or its siblings.

## Use ##
`$toggled = \WP_Table_Custom_Column_Toggle::create( $propterties );`

Where default properties are:

```php
[
    'meta_key'        => 'column_meta_key',  // "toggle" for a single site is saved using post meta
    'column_id'       => 'column_id',
    'column_name'     => 'Column Toggle',
    'column_hooks'    => [
        'header'  => 'manage_page_posts_columns',
        'content' => 'manage_page_posts_custom_column',
    ],
    'use_siteoptions' => false,
]
```

Get toggled IDs using `$toggled->get_values();`

Example, note use of column hooks:

```php
$subsite_maintenance_sites = \WP_Table_Custom_Column_Toggle::create(
	[
		'column_id'       => 'subsite_maintenance',
		'column_name'     => '<span class="dashicons dashicons-hammer"></span>',
		'column_hooks'    => [
			'header'  => 'wpmu_blogs_columns',
			'content' => 'manage_sites_custom_column',
		],
		'use_siteoptions' => true,
	]
);
$subsite_maintenance = \Subsite_Maintenance::create( $subsite_maintenance_sites );
```

Working example at: https://github.com/soderlind/subsite-maintenance-mode/
