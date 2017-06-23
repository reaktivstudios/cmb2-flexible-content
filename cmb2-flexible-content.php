<?php
/**
 * Plugin Name: CMB2 Field Type: Flexible Content
 * Plugin URI:
 * Description:
 * Version: 0.0.1
 * Author: Reaktiv Studios
 * Author URI: https://reaktivstudios.com
 * License: GPLv2+
 */
// Crucial Functions:
// cmb2/includes/CMB2_Field_Display.php : ln 35 : has a switch case with all fields
// cmb2/includes/CMB2_Types.php : ln 87 : General Render function
// cmb2/includes/CMB2.php : ln 483 : render_group_row
add_action( 'cmb2_render_flexible', 'rkv_render_flexible_field', 10, 5 );
function rkv_render_flexible_field( $field, $escaped_value, $object_id, $object_type, $field_type ) {
	$metabox = $field->get_cmb();
	// We would need to somehow grab a list of the groups (layouts) and the data within each group, then we can use the metabox instance here to dynamically create new group fields, and render out each one.
	$new_group = $metabox->add_field( array(
		'id' => 'flexible_test_group',
		'type' => 'group',
		'options' => array(
			'group_title' => 'Entry Group',
			'add_button' => 'Add Another',
			'remove_button' => 'Remove',
			'sortable' => true,
		)
	) );
	$sub_field = $metabox->add_group_field( $new_group, array(
		'name' => 'Flexible Test Field',
		'id' => 'title',
		'type' => 'text'
	) );
	$group_field = $metabox->get_field( $new_group );
	$metabox->render_group_row( $group_field, true );
	echo '<div class="cmb-row"><div class="cmb-td"><p class="cmb-add-row"><button type="button" data-selector="', $group_field->id(), '_repeat" data-grouptitle="', $group_field->options( 'group_title' ), '" class="cmb-add-group-row button">', $group_field->options( 'add_button' ), '</button></p></div></div>';
}
add_filter( 'cmb2_sanitize_flexible', 'rkv_sanitize_values_for_flexible_field', 12, 4 );
function rkv_sanitize_values_for_flexible_field( $override_value, $value, $object_id, $field_args ) {
	// This is where the data could be picked up
}
// Renders a sample flexible metabox on pages for use in testing
function rkv_flexible_test() {
	$prefix = 'my-prefix';
	$cmb = new_cmb2_box( array(
		'id'            => 'test_metabox_2',
		'title'         => __( 'Flexible Metabox', 'cmb2-flexible' ),
		'object_types'  => array( 'page', ), // Post type
		'context'       => 'normal',
		'priority'      => 'high',
		'show_names'    => true, // Show field names on the left
		// 'cmb_styles' => false, // false to disable the CMB stylesheet
		// 'closed'     => true, // Keep the metabox closed by default
	) );
	// Sample Flexible Field
	$cmb->add_field( array(
		'name'       => __( 'Test Flexible', 'cmb2-flexible' ),
		'desc'       => __( 'field description (optional)', 'cmb2-flexible' ),
		'id'         => $prefix . 'flexible',
		'type'       => 'flexible',
		'repeatable' => true,
		// This doesn't hook up anywhere, but is an example of how you could define layouts
		'layouts' => array(
			array(
				'title' => 'Image Group',
				'id'    => 'image-group',
				'fields' => array(
					array(
						'name' => 'This would be the first one',
						'type' => 'text',
						'id' => 'title'
					),
					array(
						'name' => 'This would be the second one',
						'type' => 'image',
						'id' => 'image'
					)
				)
			)
		)
	) );
}
add_action( 'cmb2_admin_init', 'rkv_flexible_test' );