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

			// error_log( print_r( get_post_meta( 2, 'modular-page-builder-data', true ), true ) );
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

			$data = array(
				array(
					'layout' => 'text',
					'values' => array(
						'title' => 'title value',
						'description' => 'description value'
					),
				),
				array(
					'layout' => 'text',
					'values' => array(
						'title' => 'title value 2',
						'description' => 'description value 2'
					),
				)
			);

			// Store these so tehy can accessed in the hook
			$this->stored_data = $data;
			$prefix = $field->_id() . '_';
			$this->prefix = $prefix;

			foreach( $data_2 as $i => $group ) {
				$layout_data = $layouts[ $group['layout'] ];
				$layout_fields = $layout_data['fields'];
				$group_id = $prefix . $i;

				$group_args = array(
					'id' => $group_id,
					'type' => 'group'
				);

				$group_name = $metabox->add_field( $group_args );
				$group_args['fields'] = array();

				foreach( $layout_fields as $subfield ) {
					$subfield_args = array(
						'id' => $subfield['id'],
						'type' => $subfield['type'],
						'name' => $subfield['name']
					);
					$subfield_id = $metabox->add_group_field( $group_name, $subfield_args );
					$group_args['fields'][ $subfield['id'] ] = $subfield_args;
				}

				$group_args['context'] = 'normal';
				$group_args['show_names'] = true;

				add_filter( 'cmb2_override_' . $group_id . '_meta_value', array( $this, 'correct_value'), 10, 4 );
				$metabox->render_group( $group_args );
			}

			// $prefix = $field->_id() . '_';

			// foreach( $data as $group_id => $layout ) {
			// 	$layout_data = $layouts[ $layout ];
			// 	$layout_fields = $layout_data['fields'];


			// 	// Create an initial Group based on our data.
			// 	$group_args = array(
			// 		'id' => $prefix . $group_id,
			// 		'type' => 'group'
			// 	);
			// 	$create_group = $metabox->add_field( $group_args );
			// 	$group_args['fields'] = array();




			// 	// Get the fields that are needed for this group and add each of them as a subfield on the group.
			// 	foreach ( $layout_fields as $subfield ) {
			// 		$subfield_args = array(
			// 			'id' => $subfield['id'],
			// 			'type' => $subfield['type'],
			// 			'name' => $subfield['name']
			// 		);

			// 		$subfield_id = $metabox->add_group_field( $create_group, $subfield_args );
			// 		$group_args['fields'][ $subfield['id'] ] = $subfield_args;
			// 	}




			// 	// Add some default settings and render it all out to the page.
			// 	$group_args['context'] = 'normal';
			// 	$group_args['show_names'] = true;

			// 	// Get all post meta
			// 	// Add some sort of filter
			// 	// Filter each one
				
			// 	add_filter( 'cmb2_override_my-prefix_flexible_first_group_id_meta_value', array( $this, 'correct_value' ), 10, 4 );
			// 	$metabox->render_group( $group_args );
			// }


		}

		public function correct_value( $data, $object_id, $a, $object ) {
			$array_key = absint( str_replace( $this->prefix, '', $a['field_id'] ) );
			$data = $this->stored_data[ $array_key ];
			return array( $data['values'] );
		}

		public function save_fields( $override_value, $value, $object_id, $field_args ) {
			return '';
		}

	}
	
	RKV_CMB2_Flexible_Content_Field::get_instance();
}