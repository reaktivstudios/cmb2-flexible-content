<?php
/**
 * Plugin Name: CMB2 Field Type: Flexible Content
 * Plugin URI: https://github.com/reaktivstudios/cmb2-flexible-content
 * Description: Adds a flexible content field for CMB2
 * Version: 0.0.1
 * Author: Reaktiv Studios
 * Author URI: https://reaktivstudios.com
 * License: GPLv2+
 */

if ( ! class_exists( 'RKV_CMB2_Flexible_Content_Field', false  ) ) {

	class RKV_CMB2_Flexible_Content_Field {

		protected static $instance;

		public static function get_instance() {
			if ( ! isset( static::$instance ) && ! ( self::$instance instanceof RKV_CMB2_Flexible_Content_Field ) ) {
				static::$instance = new RKV_CMB2_Flexible_Content_Field();
				static::$instance->init();
			}
			return static::$instance;
		}

		private function init() {
			add_action( 'cmb2_render_flexible', array( $this, 'render_fields' ), 10, 5 );
			add_filter( 'cmb2_sanitize_flexible', array( $this, 'save_fields' ), 12, 4 );
		}

		public function render_fields( $field, $escaped_value, $object_id, $object_type, $field_type ) {
			$metabox = $field->get_cmb();


			$layouts = array(
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
							'type' => 'image',
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
			);


			// $data = array(
			// 	array(
			// 		'layout' => 'text',
			// 		'fields' => array(
			// 			'title' => 'This is some sample title',
			// 			'description' => 'This is a sample description'
			// 		)
			// 	)
			// );



			// 'flexible_field_key'
			$data = array(
				'first_group_id' => 'text',
				'second_group_id' => 'text',
			);

			$prefix = $field->_id() . '_';

			foreach( $data as $group_id => $layout ) {
				$layout_data = $layouts[ $layout ];
				$layout_fields = $layout_data['fields'];

				$group_args = array(
					'id' => $prefix . $group_id,
					'type' => 'group'
				);

				// error_log( print_r( $prefix . $group_id, true ) );
				$create_group = $metabox->add_field( $group_args );

				$group_args['fields'] = array();

				foreach ( $layout_fields as $subfield ) {
					$subfield_args = array(
						'id' => $subfield['id'],
						'type' => $subfield['type'],
						'name' => $subfield['name']
					);

					$subfield_id = $metabox->add_group_field( $create_group, $subfield_args );
					$group_args['fields'][ $subfield['id'] ] = $subfield_args;
				}

				$group_args['context'] = 'normal';
				$group_args['show_names'] = true;

				$metabox->render_group( $group_args );
				//$group_field = $metabox->get_field( $create_group );
				//$metabox->render_group_row( $group_field, true );
			}


			// $prefix = $field->cmb_id;
			// foreach ( $data as $i => $layout_object ) {
			// 	$layout_object_id = $metabox->add_field( array(
			// 		'id' => 'flexible_prefix_first_group',
			// 		'type' => 'group',
			// 		'options' => array(
			// 			'group_title' => 'Flexible Group ' . $i,
			// 			'add_button' => 'Add Another',
			// 			'remove_button' => 'Remove',
			// 			'sortable' => true,
			// 		),
			// 	) );

			// 	foreach( $layout_object['fields'] as $j => $subfield ) {
			// 		$metabox->add_group_field( $layout_object_id, array(
			// 			'name' => 'Flexible Group Text ' . $j,
			// 			'id' =>  $j,
			// 			'type' => 'textarea',
			// 		) );
			// 	}

			// 	$layout_object_field = $metabox->get_field( $layout_object_id );

			// 	// add_filter( 'cmb2_override_test_metabox_2_0_meta_value', array( $this, 'return_meta_value') );


			// 	$metabox->render_group_row( $layout_object_field, true );
			// }
		}

		public function save_fields( $override_value, $value, $object_id, $field_args ) {
			$_POST
			return '';
		}

	}
	
	RKV_CMB2_Flexible_Content_Field::get_instance();
}