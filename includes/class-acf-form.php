<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ACF_Form {

	private static $instance = null;
	private static $rendered = false;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'ateculus_contact_form', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_acf_submit_form', array( $this, 'handle_submission' ) );
		add_action( 'wp_ajax_nopriv_acf_submit_form', array( $this, 'handle_submission' ) );
		add_filter( 'script_loader_tag', array( $this, 'add_turnstile_attrs' ), 10, 2 );
	}

	public function add_turnstile_attrs( $tag, $handle ) {
		if ( 'cloudflare-turnstile' !== $handle ) {
			return $tag;
		}
		return str_replace( ' src=', ' async defer src=', $tag );
	}

	public function enqueue_assets() {
		$settings = ACF_Settings::get();

		wp_register_style(
			'acf-contact-form',
			ACF_PLUGIN_URL . 'assets/css/contact-form.css',
			array(),
			ACF_VERSION
		);

		// Conditionally load Turnstile script
		if ( ! empty( $settings['turnstile_enabled'] ) && ! empty( $settings['turnstile_site_key'] ) ) {
			wp_register_script(
				'cloudflare-turnstile',
				'https://challenges.cloudflare.com/turnstile/v0/api.js',
				array(),
				null,
				true
			);
		}

		wp_register_script(
			'acf-contact-form',
			ACF_PLUGIN_URL . 'assets/js/contact-form.js',
			array( 'jquery' ),
			ACF_VERSION,
			true
		);
		wp_localize_script( 'acf-contact-form', 'acfForm', array(
			'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
			'nonce'              => wp_create_nonce( 'acf_form_nonce' ),
			'turnstileEnabled'   => ! empty( $settings['turnstile_enabled'] ) && ! empty( $settings['turnstile_site_key'] ),
			'turnstileSiteKey'   => $settings['turnstile_site_key'] ?? '',
			'strings'            => array(
				'sending'  => __( 'Sending...', 'ateculus-contact-form' ),
				'send'     => __( 'Send Message', 'ateculus-contact-form' ),
			),
		) );
	}

	public function render_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'title'        => '',
			'show_title'   => 'true',
			'show_phone'   => 'false',
			'show_service' => 'false',
		), $atts, 'ateculus_contact_form' );

		wp_enqueue_style( 'acf-contact-form' );
		wp_enqueue_script( 'acf-contact-form' );

		$settings = ACF_Settings::get();
		if ( ! empty( $settings['turnstile_enabled'] ) && ! empty( $settings['turnstile_site_key'] ) ) {
			wp_enqueue_script( 'cloudflare-turnstile' );
		}

		ob_start();
		$this->load_template( 'form', array( 'atts' => $atts ) );
		return ob_get_clean();
	}

	/**
	 * Loads a template, checking the theme first for overrides.
	 * Theme override path: your-theme/ateculus-contact-form/form.php
	 */
	private function load_template( $template_name, $data = array() ) {
		$theme_file  = get_stylesheet_directory() . '/ateculus-contact-form/' . $template_name . '.php';
		$plugin_file = ACF_PLUGIN_DIR . 'templates/' . $template_name . '.php';

		$template = file_exists( $theme_file ) ? $theme_file : $plugin_file;

		if ( ! empty( $data ) ) {
			extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
		}

		if ( file_exists( $template ) ) {
			include $template;
		}
	}

	public function handle_submission() {
		if ( ! check_ajax_referer( 'acf_form_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'ateculus-contact-form' ) ) );
		}

		// Honeypot
		if ( ! empty( $_POST['acf_website'] ) ) {
			wp_send_json_success( array( 'message' => ACF_Settings::get( 'success_message' ) ) );
		}

		// Turnstile verification
		$settings = ACF_Settings::get();
		if ( ! empty( $settings['turnstile_enabled'] ) && ! empty( $settings['turnstile_secret_key'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ?? '' ) );
			if ( empty( $token ) ) {
				wp_send_json_error( array( 'message' => __( 'Please complete the security check.', 'ateculus-contact-form' ) ) );
			}
			$verify = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
				'body'    => array(
					'secret'   => $settings['turnstile_secret_key'],
					'response' => $token,
					'remoteip' => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
				),
				'timeout' => 10,
			) );
			if ( is_wp_error( $verify ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed. Please try again.', 'ateculus-contact-form' ) ) );
			}
			$result = json_decode( wp_remote_retrieve_body( $verify ), true );
			if ( empty( $result['success'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed. Please try again.', 'ateculus-contact-form' ) ) );
			}
		}

		$fields = ACF_Fields::get_fields();
		$values = array();
		$errors = array();

		foreach ( $fields as $field ) {
			$raw = wp_unslash( $_POST[ $field['id'] ] ?? '' );

			if ( 'email' === $field['type'] ) {
				$value = sanitize_email( $raw );
				if ( $field['required'] && ( empty( $value ) || ! is_email( $value ) ) ) {
					/* translators: %s: field label */
					$errors[ $field['id'] ] = sprintf( __( 'Please enter a valid %s.', 'ateculus-contact-form' ), strtolower( $field['label'] ) );
				}
			} elseif ( 'textarea' === $field['type'] ) {
				$value = sanitize_textarea_field( $raw );
				if ( $field['required'] && empty( trim( $value ) ) ) {
					$errors[ $field['id'] ] = sprintf( __( 'Please enter your %s.', 'ateculus-contact-form' ), strtolower( $field['label'] ) );
				}
			} else {
				$value = sanitize_text_field( $raw );
				if ( $field['required'] && empty( trim( $value ) ) ) {
					/* translators: %s: field label */
					$errors[ $field['id'] ] = sprintf( __( 'Please enter your %s.', 'ateculus-contact-form' ), strtolower( $field['label'] ) );
				}
			}

			$values[ $field['id'] ] = $value;
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array(
				'message' => __( 'Please correct the errors below.', 'ateculus-contact-form' ),
				'errors'  => $errors,
			) );
		}

		$sent = ACF_Notifier::notify( $fields, $values );

		if ( $sent ) {
			wp_send_json_success( array(
				'message' => ACF_Settings::get( 'success_message' ) ?: __( 'Thank you! Your message has been sent.', 'ateculus-contact-form' ),
			) );
		} else {
			wp_send_json_error( array(
				'message' => ACF_Settings::get( 'error_message' ) ?: __( 'Sorry, there was a problem sending your message. Please try again.', 'ateculus-contact-form' ),
			) );
		}
	}
}
