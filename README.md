## Adding a Flexible Field

To add a flexible field, use

```
// Basic CMB2 Metabox declaration
$cmb = new_cmb2_box( array(
	'id'           => 'prefix-metabox-id',
	'title'        => __( 'Flexible Content Test' ),
	'object_types' => array( 'post', ),
) );

// Sample Flexible Field
$cmb->add_field( array(
	'name'       => __( 'Test Flexible', 'cmb2-flexible' ),
	'desc'       => __( 'field description (optional)', 'cmb2-flexible' ),
	'id'         => 'prefix_flexible',
	'type'       => 'flexible',
	'layouts' => array(
			'text' => array(
				'title' => 'Text Group',
				'fields' => array(
					array(
						'type' => 'text',
						'name' => 'Title for Text Group',
						'id' => 'title',
					),
					array(
						'type' => 'textarea',
						'name' => 'Description for Text Group',
						'id' => 'description',
					)
				),
			),
			'image' => array(
				'title' => 'Image Group',
				'fields' => array(
					array(
						'type' => 'file',
						'name' => 'Image for Image Group',
						'id' => 'title',
					),
					array(
						'type' => 'textarea',
						'name' => 'Description for Image Group',
						'id' => 'description',
					)
				),
			),
		)
) );
```

To retrieve data from a flexible field, use:
```
$flexible_fields = get_post_meta( $post_id, 'flexible_field_name', true );
foreach( $flexible_fields as $field ) {
    if ( 'text' === $field['layout'] ) {
        echo '<h2>' . $field['title'] . '</h2>';
        echo $field['description'];
    }
}
```