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
			add_filter( 'cmb2_sanitize_flexible', array( $this, 'save_fields' ), 12, 4 );
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

			$this->layouts = $layouts;

			if ( false === $layouts ) {
				// We need layouts for this to work.
				return false;
			}

			// These are the values from the fields.
			$data = $field_type->field->value;

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
			return $value;
		}


		/**
		 * Add Flexible content scripts and styles
		 */
		public function add_scripts() {
			wp_enqueue_script( 'cmb2-flexible-content', plugin_dir_url( __FILE__ ) . 'assets/js/cmb2-flexible.js', array( 'jquery', 'cmb2-scripts' ), '', true );
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
					'group_title' => $layout[ 'title' ],
				),
			) );

			// Foreach layout field, add a field to the group.
			foreach ( $layout['fields'] as $subfield ) {
				$subfield_args = array(
					'id' => $subfield['id'],
					'type' => $subfield['type'],
					'name' => $subfield['name'],
					'array_key' => absint( $index ),
				);
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
			// Need to add a custom escaping function here.
			// Only need to escape the layout type.
			return $meta_value;
		}

	}

	RKV_CMB2_Flexible_Content_Field::get_instance();
} // End if().
