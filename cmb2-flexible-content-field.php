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
			add_filter( 'cmb2_sanitize_flexible', array( $this, 'save_fields' ), 12, 5 );
			add_filter( 'cmb2_types_esc_flexible', array( $this, 'escape_values' ), 10, 2 );

			add_action( 'admin_enqueue_scripts', array( $this, 'add_scripts' ) );
			add_action( 'wp_ajax_get_flexible_content_row', array( $this, 'handle_ajax' ) );
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
			$metabox_id = $metabox->cmb_id;
			$layouts = isset( $field->args['layouts'] ) ? $field->args['layouts'] : false;

			// Add all possible dependencies for right now.
			$dependencies = $this->get_dependencies( $layouts );
			$field->add_js_dependencies( $dependencies );
			if ( false === $layouts ) {
				// We need layouts for this to work.
				return false;
			}

			// These are the values from the fields.
			$data = $escaped_value;

			$group = $this->create_group( $field );

			echo '<div class="cmb-row cmb-repeat-group-wrap ', esc_attr( $group->row_classes() ), '" data-fieldtype="flexible"><div class="cmb-td"><div data-groupid="', esc_attr( $group->id() ), '" id="', esc_attr( $group->id() ), '_repeat" ', $metabox->group_wrap_attributes( $group ), '>';

			echo '<div class="cmb-flexible-rows">';
			if ( ! empty( $data ) ) {
				foreach ( $data as $i => $group_details ) {
					$subfields = array();
					$type = $group_details['layout'];

					$group = $this->add_subfields( $group, $metabox, $type, $i );

					$metabox->render_group_row( $group, false );
				}
			}

			echo '</div>';

			echo '<div class="cmb-flexible-add">';
			echo '<button class="cmb-flexible-add-button button button-primary">Add Group</button>';
			echo '<ul class="cmb-flexible-add-list hidden">';
			foreach ( $layouts as $layout_key => $layout ) {
				echo '<li class="cmb-flexible-add-list-item">';
				echo '<button data-grouptitle="Group {#}" class="cmb-add-group-row cmb2-add-flexible-row" data-type="' . esc_attr( $layout_key ) . '">' . esc_attr( $layout['title'] ) . '</button>';
				echo '</li>';
			}
			echo '</ul>';
			echo '</div>';

			$this->prerender_wysiwyg( $data, $layouts, $group );

			echo '</div></div></div>';
		}

		/**
		 * Retrieves a list of JS dependencies based on file types.
		 *
		 * These are then added to the parent fields list of dependencies so that they are included at output.
		 *
		 * @param  array $layouts List of layouts.
		 * @return array          List of dependencies
		 */
		public function get_dependencies( $layouts ) {
			$dependencies = array();
			foreach ( $layouts as $layout ) {
				foreach ( $layout['fields'] as $field ) {
					switch ( $field['type'] ) {
						case 'colorpicker':
							wp_enqueue_style( 'wp-color-picker' );
							$dependencies[] = 'wp-color-picker';
							break;
						case 'file':
						case 'file_list':
							$dependencies[] = 'media-editor';
							break;
						case 'text_date':
						case 'text_time':
						case 'text_datetime_timestamp':
							$dependencies[] = 'jquery-ui-core';
							$dependencies[] = 'jquery-ui-datepicker';
							break;
						case 'text_datetime_timestamp':
						case 'text_time':
							$dependencies[] = 'jquery-ui-datetimepicker';
							break;
						case 'wysiwyg':
							$dependencies[] = 'wp-util';
							$dependencies[] = 'cmb2-wysiwyg';
							break;
					}
				}
			}

			if ( ! empty( $dependencies ) ) {
				$dependencies = array_unique( $dependencies );
			}

			return $dependencies;
		}

		/**
		 * Sanitization callback
		 *
		 * @param  array  $override_value    Value that's being overridden.
		 * @param  array  $values            Value from post object.
		 * @param  int    $object_id         Object / field ID.
		 * @param  array  $field_args        Full list of arguments.
		 * @param  object $sanitizer_object  Instance of CMB2_Sanitize.
		 * @return string                Data
		 */
		public function save_fields( $override_value, $values, $object_id, $field_args, $sanitizer_object ) {

			$flexible_field = $sanitizer_object->field;
			$field_id = $flexible_field->_id();
			$metabox = $flexible_field->get_cmb();
			$layouts = $flexible_field->args['layouts'];

			if ( empty( $values ) ) {
				return $values;
			}

			$group_args = array(
				'id' => $field_id,
				'type' => 'group',
				'context' => 'normal',
				'repeatable' => true,
				'fields' => array(),
			);
			$field_group = $flexible_field->get_field_clone( $group_args );
			$field_group->data_to_save = $values;

			// The saved array is used to hold sanitized values.
			$saved = array();
			foreach ( $values as $i => $group_vals ) {

				// Cache the type and save it in array.
				$type = isset( $group_vals['layout'] ) ? sanitize_key( $group_vals['layout'] ) : false;
				if ( ! $type ) {
					continue;
				}

				$saved[ $i ]['layout'] = $type;
				$field_group->index = $i;

				$layout = isset( $layouts[ $type ] ) ? $layouts[ $type ] : false;
				$group_args['fields'] = $layout['fields'];
				$field_group->set_prop( 'fields', $group_args['fields'] );
				$metabox->add_field( $group_args );
				foreach ( $layout['fields'] as $subfield_args ) {
					$sub_id = $subfield_args['id'];
					$field  = $metabox->get_field( $subfield_args, $field_group );
					$new_val = isset( $group_vals[ $sub_id ] ) ? $group_vals[ $sub_id ] : false;

					$new_val = $field->sanitization_cb( $new_val );

					if ( is_array( $new_val ) && $field->args( 'has_supporting_data' ) ) {
						if ( $field->args( 'repeatable' ) ) {
							$_new_val = array();
							foreach ( $new_val as $group_index => $grouped_data ) {
								// Add the supporting data to the $saved array stack.
								$saved[ $i ][ $grouped_data['supporting_field_id'] ][] = $grouped_data['supporting_field_value'];
								// Reset var to the actual value.
								$_new_val[ $group_index ] = $grouped_data['value'];
							}
							$new_val = $_new_val;
						} else {
							// Add the supporting data to the $saved array stack.
							$saved[ $i ][ $new_val['supporting_field_id'] ] = $new_val['supporting_field_value'];
							// Reset var to the actual value.
							$new_val = $new_val['value'];
						}
					}
					$saved[ $i ][ $sub_id ] = $new_val;
				}
				$saved[ $i ] = CMB2_Utils::filter_empty( $saved[ $i ] );
			}

			$saved = CMB2_Utils::filter_empty( $saved );

			return $saved;
		}


		/**
		 * Add Flexible content scripts and styles
		 */
		public function add_scripts() {
			wp_enqueue_script( 'cmb2-flexible-content', plugin_dir_url( __FILE__ ) . 'assets/js/cmb2-flexible.js', array( 'jquery', 'cmb2-scripts' ), '0.1.1', true );
			wp_enqueue_style( 'cmb2-flexible-styles', plugin_dir_url( __FILE__ ) . 'assets/css/cmb2-flexible.css', array( 'cmb2-styles' ), '0.1' );
		}

		/**
		 * Prerender WYSIWYG templates if necessary
		 *
		 * Iterates through all fields and then outputs a clone-able template if a wysiwyg field exists.
		 *
		 * @param  array  $data    Data from request.
		 * @param  array  $layouts Field layouts.
		 * @param  object $group   Full object.
		 */
		public function prerender_wysiwyg( $data, $layouts, $group ) {
			$wysiwygs = array();
			foreach ( $layouts as $layout ) {
				$fields = $layout['fields'];
				foreach ( $fields as $field ) {
					if ( 'wysiwyg' === $field['type'] ) {
						$wysiwygs[ $field['id'] ] = $field;
					}
				}
			}

			if ( ! empty( $data ) ) {
				foreach ( $data as $i => $group_details ) {
					$type = $group_details['layout'];
					foreach ( $layouts[ $type ]['fields'] as $subfield ) {
						if ( 'wysiwyg' === $subfield['type'] ) {
							unset( $wysiwygs[ $subfield['id'] ] );
						}
					}
				}
			}

			$metabox = $group->get_cmb();
			if ( ! empty( $wysiwygs ) ) {
				foreach ( $wysiwygs as $wysiwyg_id => $args ) {

					$group->index = 0;
					if ( ! $data ) {
						$metabox->add_field( $group->args() );
					}
					$wysiwyg_field = $metabox->add_group_field( $group->_id(), $args );
					$wysiwyg_field = $metabox->get_field( $args, $group );
					$types = new CMB2_Types( $wysiwyg_field );
					$wysiwyg_type = $types->get_new_render_type( 'wysiwyg', 'CMB2_Type_Wysiwyg', $args );
					$wysiwyg_type->add_wysiwyg_template_for_group();
				}
			}

		}

		/**
		 * Handle AJAX request for a new flexible row
		 *
		 * Creates a new group based on a few variables, renders the output
		 * then returns it.
		 */
		public function handle_ajax() {
			if ( ! ( isset( $_POST['cmb2_ajax_nonce'] ) && wp_verify_nonce( $_POST['cmb2_ajax_nonce'], 'ajax_nonce' ) ) ) {
				die();
			}

			$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : ''; // Input var okay.
			$metabox_id = isset( $_POST['metabox_id'] ) ? sanitize_key( wp_unslash( $_POST['metabox_id'] ) ) : ''; // Input var okay.
			$field_id = isset( $_POST['field_id'] ) ? sanitize_key( wp_unslash( $_POST['field_id'] ) ) : ''; // Input var okay.
			$index = isset( $_POST['latest_index'] ) ? absint( $_POST['latest_index'] ) + 1 : 0; // Input var okay.

			$field = cmb2_get_field( $metabox_id, $field_id );
			$metabox = $field->get_cmb();
			$group = $this->create_group( $field );

			$group = $this->add_subfields( $group, $metabox, $type, $index );

			ob_start();
			$metabox->render_group_row( $group, false );
			$output = ob_get_clean();

			wp_send_json_success( array(
				'output' => $output,
			) );
		}

		/**
		 * Create a basic group field by cloning the existing field.
		 *
		 * @param  object $field Full field object.
		 * @return object        Cloned field object
		 */
		public function create_group( $field ) {
			$field_id = $field->_id();

			$group_args = array(
				'id' => $field_id,
				'type' => 'group',
				'context' => 'normal',
				'repeatable' => true,
				'show_names' => true,
				'classes' => array( 'cmb-flexible-wrap' ),
				'fields' => array(),
				'options' => array(),
			);

			$field = $field->get_field_clone( $group_args );

			return $field;
		}

		/**
		 * Add subfields to cloned field.
		 *
		 * @param object $field   Full field object.
		 * @param object $metabox CMB2 instance.
		 * @param string $type    Layout type.
		 * @param int    $i       Field index.
		 */
		public function add_subfields( $field, $metabox, $type, $i ) {
			$subfields = array();
			$metabox = $field->get_cmb();
			$group_args = $field->args();
			$layout = isset( $field->args['layouts'] ) ? $field->args['layouts'][ $type ] : false;

			$subfields = $layout['fields'];
			$subfields[] = array(
				'type' => 'text',
				'id' => 'layout',
				'attributes' => array(
					'type' => 'hidden',
					'value' => $type,
				),
			);
			$group_args['fields'] = $subfields;
			$field->set_prop( 'fields', $subfields );

			if ( isset( $layout['title'] ) ) {
				$group_args['options'] = array(
					'group_title' => $layout['title'],
				);
				$field = $field->get_field_clone( $group_args );
			}

			$metabox->add_field( $group_args );
			$field->index = $i;

			return $field;
		}

		/**
		 * Value escape before output
		 *
		 * @param  string $val        Escaping default value.
		 * @param  array  $meta_value Value of metadata.
		 * @return array             Escaped value of metadata
		 */
		public function escape_values( $val, $meta_value ) {
			if ( is_array( $meta_value ) && ! empty( $meta_value ) ) {
				foreach ( $meta_value as $i => $value ) {
					$meta_value[ $i ]['layout'] = esc_attr( $value['layout'] );
				}
			}
			// Only need to escape the layout type.
			return $meta_value;
		}

	}

	RKV_CMB2_Flexible_Content_Field::get_instance();
} // End if().
