<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ACF_Fields {

	const OPTION_KEY = 'ateculus_contact_form_fields';

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_acf_save_fields',  array( $this, 'ajax_save_fields' ) );
		add_action( 'wp_ajax_acf_reset_fields', array( $this, 'ajax_reset_fields' ) );
	}

	/**
	 * Returns stored fields. Seeds defaults into the DB on first call so
	 * the Form Fields builder shows them as editable/removable items.
	 */
	public static function get_fields() {
		$fields = get_option( self::OPTION_KEY );
		if ( false === $fields ) {
			// Option has never been saved — persist defaults so they appear in the builder.
			$fields = self::get_default_fields();
			add_option( self::OPTION_KEY, $fields );
		} elseif ( ! is_array( $fields ) || empty( $fields ) ) {
			$fields = self::get_default_fields();
		}
		return $fields;
	}

	/**
	 * Called on plugin activation to ensure defaults are in the DB immediately.
	 */
	public static function seed_defaults() {
		if ( false === get_option( self::OPTION_KEY ) ) {
			add_option( self::OPTION_KEY, self::get_default_fields() );
		}
	}

	public static function get_default_fields() {
		return array(
			array(
				'id'          => 'acf_name',
				'type'        => 'text',
				'label'       => 'Full Name',
				'placeholder' => 'John Doe',
				'required'    => true,
				'width'       => '50',
				'options'     => '',
			),
			array(
				'id'          => 'acf_email',
				'type'        => 'email',
				'label'       => 'Email Address',
				'placeholder' => 'john@example.com',
				'required'    => true,
				'width'       => '50',
				'options'     => '',
			),
			array(
				'id'          => 'acf_phone',
				'type'        => 'tel',
				'label'       => 'Phone Number',
				'placeholder' => '+1 (800) 123-4567',
				'required'    => false,
				'width'       => '50',
				'options'     => '',
			),
			array(
				'id'          => 'acf_service',
				'type'        => 'select',
				'label'       => 'Service Interested In',
				'placeholder' => '-- Select a Service --',
				'required'    => false,
				'width'       => '50',
				'options'     => "IT Infrastructure\nCyber Security\nCloud Services\nSoftware Development\nData Analytics\nManaged IT Services\nOther",
			),
			array(
				'id'          => 'acf_message',
				'type'        => 'textarea',
				'label'       => 'Message',
				'placeholder' => 'Tell us about your project or question...',
				'required'    => true,
				'width'       => '100',
				'options'     => '',
			),
		);
	}

	public function enqueue_assets( $hook ) {
		if ( empty( $_GET['page'] ) || 'ateculus-contact-fields' !== $_GET['page'] ) {
			return;
		}
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_style(
			'acf-fields-builder',
			ACF_PLUGIN_URL . 'assets/css/fields-builder.css',
			array(),
			ACF_VERSION
		);
		wp_enqueue_script(
			'acf-fields-builder',
			ACF_PLUGIN_URL . 'assets/js/fields-builder.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			ACF_VERSION,
			true
		);
		wp_localize_script( 'acf-fields-builder', 'acfBuilder', array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'acf_fields_nonce' ),
			'fields'   => self::get_fields(),
			'strings'  => array(
				'confirmDelete' => __( 'Delete this field? This cannot be undone.', 'ateculus-contact-form' ),
				'confirmReset'  => __( 'Reset all fields to defaults? Any custom fields will be removed.', 'ateculus-contact-form' ),
				'saved'         => __( 'Fields saved successfully!', 'ateculus-contact-form' ),
				'saveError'     => __( 'Error saving fields. Please try again.', 'ateculus-contact-form' ),
				'resetDone'     => __( 'Fields reset to defaults.', 'ateculus-contact-form' ),
				'idPlaceholder' => __( 'auto_generated', 'ateculus-contact-form' ),
			),
		) );
	}

	public function ajax_save_fields() {
		check_ajax_referer( 'acf_fields_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$raw    = wp_unslash( $_POST['fields'] ?? '[]' );
		$input  = json_decode( $raw, true );

		if ( ! is_array( $input ) ) {
			wp_send_json_error( array( 'message' => 'Invalid data.' ) );
		}

		$allowed_types = array( 'text', 'email', 'tel', 'number', 'textarea', 'select' );
		$allowed_widths = array( '50', '100', '33' );
		$clean = array();

		foreach ( $input as $field ) {
			$id = sanitize_key( $field['id'] ?? '' );
			if ( empty( $id ) ) {
				continue;
			}
			$clean[] = array(
				'id'          => $id,
				'type'        => in_array( $field['type'] ?? 'text', $allowed_types, true ) ? $field['type'] : 'text',
				'label'       => sanitize_text_field( $field['label'] ?? '' ),
				'placeholder' => sanitize_text_field( $field['placeholder'] ?? '' ),
				'required'    => ! empty( $field['required'] ),
				'width'       => in_array( $field['width'] ?? '100', $allowed_widths, true ) ? $field['width'] : '100',
				'options'     => sanitize_textarea_field( $field['options'] ?? '' ),
			);
		}

		update_option( self::OPTION_KEY, $clean );
		wp_send_json_success( array( 'message' => __( 'Fields saved!', 'ateculus-contact-form' ) ) );
	}

	public function ajax_reset_fields() {
		check_ajax_referer( 'acf_fields_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}
		delete_option( self::OPTION_KEY );
		wp_send_json_success( array(
			'message' => __( 'Reset to defaults!', 'ateculus-contact-form' ),
			'fields'  => self::get_default_fields(),
		) );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap acf-settings-wrap">
			<h1><?php esc_html_e( 'Form Fields', 'ateculus-contact-form' ); ?></h1>

			<div class="acf-card acfb-card" style="max-width:900px;">

				<div class="acfb-toolbar">
					<p class="description">
						<?php esc_html_e( 'Drag rows to reorder. Click Edit to configure a field. Click Save when done.', 'ateculus-contact-form' ); ?>
					</p>
					<div class="acfb-toolbar-btns">
						<button type="button" class="button" id="acfb-reset-btn">
							<?php esc_html_e( 'Reset to Defaults', 'ateculus-contact-form' ); ?>
						</button>
						<button type="button" class="button button-primary" id="acfb-add-btn">
							+ <?php esc_html_e( 'Add Field', 'ateculus-contact-form' ); ?>
						</button>
					</div>
				</div>

				<div id="acfb-field-list" class="acfb-field-list">
					<!-- Populated by fields-builder.js -->
				</div>

				<div class="acfb-save-bar">
					<button type="button" class="button button-primary button-large" id="acfb-save-btn">
						<?php esc_html_e( 'Save Fields', 'ateculus-contact-form' ); ?>
					</button>
					<span class="acfb-status" id="acfb-status"></span>
				</div>
			</div>

			<div class="acf-card" style="max-width:900px;margin-top:0;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Tips', 'ateculus-contact-form' ); ?></h3>
				<ul style="margin:0;padding-left:20px;line-height:1.9;font-size:13px;color:#666;">
					<li><?php esc_html_e( 'The field ID is auto-generated from the label and cannot be changed after saving (it is the POST key).', 'ateculus-contact-form' ); ?></li>
					<li><?php esc_html_e( 'At least one Email type field is required to receive replies.', 'ateculus-contact-form' ); ?></li>
					<li><?php esc_html_e( 'Dropdown options: one option per line. The first blank option uses the Placeholder text.', 'ateculus-contact-form' ); ?></li>
					<li><?php esc_html_e( 'Half-width fields sit side-by-side on medium+ screens and stack on mobile.', 'ateculus-contact-form' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}
}
