<?php
/**
 * Plugin Name: CMB2 Field Type: Flexible Content
 * Plugin URI: https://github.com/reaktivstudios/cmb2-flexible-content
 * Description: Adds a flexible content field for CMB2
 * Version: 0.0.1
 * Author: Reaktiv Studios
 * Author URI: https://reaktivstudios.com
 * License: GPLv2+
 *
 * @package  cmb2-flexible-content-field
 */

if ( ! class_exists( 'RKV_CMB2_Flexible_Content_Field', false ) ) {

	/**
	 * Flexible content field render and sanitization callbacks
	 */
	class RKV_CMB2_Flexible_Content_Field {

		/**
		 * Constain instance of class
		 *
		 * @var RKV_CMB2_Flexible_Content_Field
		 */
		protected static $instance;

		/**
		 * Set up static instance of class
		 *
		 * @return class RKV_CMB2_Flexible_Content_Field instance
		 */
		public static function get_instance() {
			if ( ! isset( static::$instance ) && ! ( self::$instance instanceof RKV_CMB2_Flexible_Content_Field ) ) {
				static::$instance = new RKV_CMB2_Flexible_Content_Field();
				static::$instance->init();
			}
			return static::$instance;
		}

		/**
		 * Add hooks and filters to flexible content field
		 */
		private function init() {
			add_action( 'cmb2_render_flexible', array( $this, 'render_fields' ), 10, 5 );
			add_filter( 'cmb2_sanitize_flexible', array( $this, 'save_fields' ), 12, 4 );
		}

		/**
		 * Render fields callback
		 *
		 * Grabs the layouts from the field data and the current data from the database,
		 * then constructs a new field group for each used layout and renders that out, one by one.
		 *
		 * Groups are used so that the CMB2 API can automatically apply
		 * necessary render functions for each individual field.
		 *
		 * The data for each group needs to be overrrdden though since it is stored in a different type of array.
		 *
		 * @param  object $field         Field arguments and parameters.
		 * @param  array  $escaped_value Escaped value from database.
		 * @param  int    $object_id     Integer for full object.
		 * @param  string $object_type  Object type.
		 * @param  string $field_type    Field type.
		 */
		public function render_fields( $field, $escaped_value, $object_id, $object_type, $field_type ) {
			$metabox = $field->get_cmb();
			$layouts = isset( $field->args['layouts'] ) ? $field->args['layouts'] : false;

			if ( false === $layouts ) {
				// We need layouts for this to work.
				return false;
			}

			// Stubbed data.
			// This is what data should look like when getting it from the database.
			$data = array(
				array(
					'layout' => 'text',
					'values' => array(
						'title' => 'title value',
						'description' => 'description value',
					),
				),
				array(
					'layout' => 'text',
					'values' => array(
						'title' => 'title value 2',
						'description' => 'description value 2',
					),
				),
			);

			// Store these so tehy can accessed in the hook.
			$this->stored_data = $data;

			$field_id = $field->_id();
			foreach ( $data as $i => $group ) {
				$layout_data = $layouts[ $group['layout'] ];
				$layout_fields = $layout_data['fields'];
				$group_id = $field_id . '[' . $i . ']';

				// Create a new group that will hold the layout group.
				// Make sure to define the ID as an array so that it is passed to the sanitization callback
				// The array_key should be defined on both main group field and all subfields.
				$group_args = array(
					'id' => $field->_id() . '[' . $i . ']',
					'type' => 'group',
					'array_key' => absint( $i ),
				);
				$group_name = $metabox->add_field( $group_args );
				$group_args['fields'] = array();

				// Foreach layout field, add a field to the group.
				foreach ( $layout_fields as $subfield ) {
					$subfield_args = array(
						'id' => $subfield['id'],
						'type' => $subfield['type'],
						'name' => $subfield['name'],
						'array_key' => absint( $i ),
					);
					$subfield_id = $metabox->add_group_field( $group_name, $subfield_args );
					$group_args['fields'][ $subfield['id'] ] = $subfield_args;
				}

				// Set some necessary defaults.
				$group_args['context'] = 'normal';
				$group_args['show_names'] = true;

				add_filter( 'cmb2_override_' . $group_id . '_meta_value', array( $this, 'override_meta_value' ), 10, 4 );
				$metabox->render_group( $group_args );
				remove_filter( 'cmb2_override_' . $group_id . '_meta_value', array( $this, 'override_meta_value' ) );
			}

		}

		/**
		 * Override the meta value for each group with data from database
		 *
		 * @param  object $data      Original data set.
		 * @param  int    $object_id Object ID.
		 * @param  array  $a         Array of arguments for field.
		 * @param  object $object    Full field object.
		 * @return array            Data to save
		 */
		public function override_meta_value( $data, $object_id, $a, $object ) {
			if ( isset( $object->args['array_key'] ) ) {

				$array_key = absint( $object->args['array_key'] );
				$data = $this->stored_data[ $array_key ];
				$data = array( $data['values'] );
			}
			return $data;
		}

		/**
		 * Sanitization callback
		 *
		 * @param  array $override_value Value that's being overridden.
		 * @param  array $value          Value from post object.
		 * @param  int   $object_id      Object / field ID.
		 * @param  array $field_args     Full list of arguments.
		 * @return string                Data
		 */
		public function save_fields( $override_value, $value, $object_id, $field_args ) {
			// Get the value and then sanitize it according to sanitization rules.
			return '';
		}

	}

	RKV_CMB2_Flexible_Content_Field::get_instance();
}
