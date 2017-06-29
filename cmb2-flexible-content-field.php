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
		 * Stored data to be passed to filter
		 *
		 * @var array
		 */
		protected $stored_data;

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
			$this->layouts = $layouts;

			if ( false === $layouts ) {
				// We need layouts for this to work.
				return false;
			}

			// These are the values from the fields.
			$data = $escaped_value;
			// Store these so they can accessed in the hook.
			$this->stored_data = $data;

			$field_id = $field->_id();
			echo '<div class="cmb-flexible-group" data-fieldid="' . esc_attr( $field_id ) . '">';
			echo '<div class="cmb-flexible-rows">';

			if ( ! empty( $data ) ) {
				foreach ( $data as $i => $group_details ) {
					$type = $group_details['layout'];
					$group = $this->create_group( $type, $field, $i );

					$this->render_group( $metabox, $group, $type, true );
				}
			}

			echo '</div>';

			echo '<div class="cmb-flexible-add">';
			echo '<button class="cmb-flexible-add-button button button-primary">Add Group</button>';
			echo '<ul class="cmb-flexible-add-list hidden">';
			foreach ( $layouts as $layout_key => $layout ) {
				echo '<li class="cmb-flexible-add-list-item">';
				echo '<button class="cmb2-add-flexible-row" data-type="' . esc_attr( $layout_key ) . '">' . esc_attr( $layout['title'] ) . '</button>';
				echo '</li>';
			}
			echo '</ul>';
			echo '</div>';

			echo '</div>';
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
			}

			// Wrap the data in array so it can be picked up by subgroups.
			return array( $data );
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

			if ( empty( $values ) ) {
				return $values;
			}

			// Set up the metabox.
			$flexible_field = $sanitizer_object->field;
			$metabox = $flexible_field->get_cmb();

			$field_id = $flexible_field->_id();
			// The saved array is used to hold sanitized values.
			$saved = array();
			foreach ( $values as $i => $group_vals ) {

				// Cache the type and save it in array.
				$type = isset( $group_vals['layout'] ) ? sanitize_key( $group_vals['layout'] ) : false;
				if ( ! $type ) {
					continue;
				}
				$saved[ $i ]['layout'] = $type;

				$field_group = $this->create_group( $type, $flexible_field, $i );
				$group_id = $field_group->_id();
				$field_group->data_to_save = array(
					$group_id => $group_vals,
				);

				foreach ( array_values( $field_group->fields() ) as $field_args ) {
					if ( 'title' === $field_args['type'] ) {
						// Don't process title fields.
						continue;
					}

					$field  = $metabox->get_field( $field_args, $field_group );
					$sub_id = $field->id( true );

					// If we flatten the array we don't need to do this.
					foreach ( (array) $group_vals as $field_group->index => $post_vals ) {
						$new_val = isset( $group_vals[ $field_group->index ][ $sub_id ] )
							? $group_vals[ $field_group->index ][ $sub_id ]
							: false;
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
			wp_enqueue_script( 'cmb2-flexible-content', plugin_dir_url( __FILE__ ) . 'assets/js/cmb2-flexible.js', array( 'jquery', 'cmb2-scripts' ), '0.1', true );
			wp_enqueue_style( 'cmb2-flexible-styles', plugin_dir_url( __FILE__ ) . 'assets/css/cmb2-flexible.css', array( 'cmb2-styles' ), '0.1' );
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

			$metabox = cmb2_get_metabox( $metabox_id );
			$field = cmb2_get_field( $metabox_id, $field_id );
			$group = $this->create_group( $type, $field, $index );

			ob_start();
			$this->render_group( $metabox, $group, $type );
			$output = ob_get_clean();

			wp_send_json_success( $output );
		}

		/**
		 * Render group field wrapped in a flexible container.
		 *
		 * If override is set to true, also add meta value override filter
		 *
		 * @param  object  $metabox  Metabox instance.
		 * @param  object  $group    Group field instance.
		 * @param  string  $type     Layout type.
		 * @param  boolean $override Should the layout field be added.
		 */
		public function render_group( $metabox, $group, $type, $override = false ) {
			$group_name = $group->_id();
			$index = $group->args['array_key'];

			echo '<div class="cmb-row cmb-flexible-row" data-groupid="' . esc_attr( $group_name ) . '" data-groupindex="' . absint( $index ) . '">';
			echo '<button class="dashicons-before dashicons-no-alt cmb-remove-flexible-row" type="button" title="Remove Entry"></button>';
			echo '<input id="' . esc_attr( $group_name ) . '[layout]" name="' . esc_attr( $group_name ) . '[layout]" value="' . esc_attr( $type ) . '" type="hidden" >';

			if ( true === $override ) {
				add_filter( 'cmb2_override_' . $group_name . '_meta_value', array( $this, 'override_meta_value' ), 10, 4 );
			}

			$metabox->render_group_row( $group, false );

			if ( true === $override ) {
				remove_filter( 'cmb2_override_' . $group_name . '_meta_value', array( $this, 'override_meta_value' ) );
			}

			echo '<div class="cmb-row cmb-remove-field-row">';
			echo '<button class="button cmb-shift-flexible-rows move-up alignleft dashicons-before dashicons-arrow-up-alt2"></button>';
			echo '<button class="button cmb-shift-flexible-rows move-down alignleft dashicons-before dashicons-arrow-down-alt2"></button>';
			echo '</div>';

			echo '</div>';
		}

		/**
		 * Create a group based on type and Flexible field object
		 *
		 * Creates a new group using the CMB API, and then dynamically
		 * generates subfield for that group based on the layouts defined
		 * by the user
		 *
		 * @param  string $type  Layout key.
		 * @param  object $field Flexible field object.
		 * @param  int    $index Index in group list.
		 * @return object        New group field
		 */
		public function create_group( $type, $field, $index ) {
			$field_id = $field->_id();
			$metabox = $field->get_cmb();
			$index = absint( $index );
			$layout = isset( $field->args['layouts'] ) ? $field->args['layouts'][ $type ] : false;

			// Create a new group that will hold the layout group.
			// Make sure to define the ID as an array so that it is passed to the sanitization callback
			// The array_key should be defined on both main group field and all subfields.
			$group_id = $field_id . '[' . $index . ']';
			$group_name = $metabox->add_field( array(
				'id' => $group_id,
				'type' => 'group',
				'array_key' => absint( $index ),
				'repeatable' => false,
				// TODO: Set these with field defaults.
				'context' => 'normal',
				'show_names' => true,
				'options' => array(
					'group_title' => $layout['title'],
				),
			) );

			// Foreach layout field, add a field to the group.
			foreach ( $layout['fields'] as $subfield_args ) {
				$subfield_args['array_key'] = absint( $index );
				$subfield_id = $metabox->add_group_field( $group_name, $subfield_args );
			}

			// Set some necessary defaults.
			$group = $metabox->get_field( $group_name );
			return $group;
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
			// Need to add a custom escaping function here.
			// Only need to escape the layout type.
			return $meta_value;
		}

	}

	RKV_CMB2_Flexible_Content_Field::get_instance();
} // End if().
