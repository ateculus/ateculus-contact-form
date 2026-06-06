<?php
/**
 * Plugin Name: Ateculus Contact Form
 * Plugin URI:  https://ateculus.com
 * Description: Flexible contact form with Turnstile, SMTP support, and multi-channel notifications (Email, Discord, Slack). Use [ateculus_contact_form] shortcode anywhere.
 * Version:     1.1.1
 * Author:      Ateculus
 * Author URI:  https://ateculus.com
 * License:     Ateculus Source License 1.0
 * Text Domain: ateculus-contact-form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ACF_VERSION', '1.1.1' );
define( 'ACF_PLUGIN_FILE', __FILE__ );
define( 'ACF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ACF_OPTION_KEY', 'ateculus_contact_form_settings' );

require_once ACF_PLUGIN_DIR . 'includes/class-acf-settings.php';
require_once ACF_PLUGIN_DIR . 'includes/class-acf-fields.php';
require_once ACF_PLUGIN_DIR . 'includes/class-acf-mailer.php';
require_once ACF_PLUGIN_DIR . 'includes/class-acf-notifier.php';
require_once ACF_PLUGIN_DIR . 'includes/class-acf-form.php';

function acf_init() {
	ACF_Settings::get_instance();
	ACF_Fields::get_instance();
	ACF_Form::get_instance();
}
add_action( 'plugins_loaded', 'acf_init' );

register_activation_hook( __FILE__, 'acf_activate' );
function acf_activate() {
	$defaults = array(
		'provider'             => 'phpmail',
		'smtp_host'            => '',
		'smtp_port'            => '',
		'smtp_encryption'      => '',
		'smtp_auth'            => '1',
		'smtp_username'        => '',
		'smtp_password'        => '',
		'from_name'            => get_bloginfo( 'name' ),
		'from_email'           => get_option( 'admin_email' ),
		'to_email'             => get_option( 'admin_email' ),
		'success_message'      => 'Thank you! Your message has been sent.',
		'error_message'        => 'Sorry, there was a problem sending your message. Please try again.',
		// Turnstile
		'turnstile_enabled'    => '0',
		'turnstile_site_key'   => '',
		'turnstile_secret_key' => '',
		// Notifications
		'notify_email'         => '1',
		'notify_discord'       => '0',
		'notify_discord_url'   => '',
		'notify_slack'         => '0',
		'notify_slack_url'     => '',
	);
	if ( ! get_option( ACF_OPTION_KEY ) ) {
		add_option( ACF_OPTION_KEY, $defaults );
	} else {
		// Merge new keys into existing settings so upgrades don't lose them
		$existing = get_option( ACF_OPTION_KEY, array() );
		$merged   = array_merge( $defaults, $existing );
		update_option( ACF_OPTION_KEY, $merged );
	}

	ACF_Fields::seed_defaults();
}
