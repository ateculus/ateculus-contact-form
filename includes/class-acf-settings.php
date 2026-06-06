<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ACF_Settings {

	private static $instance = null;

	// Pre-configured provider settings
	const PROVIDERS = array(
		'phpmail'  => array(
			'label'      => 'PHP Mail (Default)',
			'host'       => '',
			'port'       => '',
			'encryption' => '',
			'auth'       => false,
		),
		'gmail'    => array(
			'label'      => 'Gmail',
			'host'       => 'smtp.gmail.com',
			'port'       => '587',
			'encryption' => 'tls',
			'auth'       => true,
		),
		'mailgun'  => array(
			'label'      => 'Mailgun',
			'host'       => 'smtp.mailgun.org',
			'port'       => '587',
			'encryption' => 'tls',
			'auth'       => true,
		),
		'sendgrid' => array(
			'label'      => 'SendGrid',
			'host'       => 'smtp.sendgrid.net',
			'port'       => '587',
			'encryption' => 'tls',
			'auth'       => true,
		),
		'custom'   => array(
			'label'      => 'Custom SMTP',
			'host'       => '',
			'port'       => '587',
			'encryption' => 'tls',
			'auth'       => true,
		),
	);

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_acf_get_provider_config', array( $this, 'ajax_get_provider_config' ) );
		add_filter( 'admin_footer_text', array( $this, 'footer_text' ) );
	}

	public function footer_text( $text ) {
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->id, array(
			'toplevel_page_ateculus-contact-form',
			'ateculus-contact_page_ateculus-contact-form',
			'ateculus-contact_page_ateculus-contact-fields',
		), true ) ) {
			return 'Thank you for using <strong>Ateculus-Contact</strong> &mdash; built by <a href="https://ateculus.com" target="_blank">Ateculus</a>.';
		}
		return $text;
	}

	public static function get( $key = null ) {
		$settings = get_option( ACF_OPTION_KEY, array() );
		if ( $key ) {
			return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
		}
		return $settings;
	}

	public function add_menu() {
		add_menu_page(
			__( 'Ateculus Contact Form', 'ateculus-contact-form' ),
			'Ateculus-Contact',
			'manage_options',
			'ateculus-contact-form',
			array( $this, 'render_settings_page' ),
			'dashicons-email-alt',
			82
		);

		add_submenu_page(
			'ateculus-contact-form',
			__( 'Settings', 'ateculus-contact-form' ),
			__( 'Settings', 'ateculus-contact-form' ),
			'manage_options',
			'ateculus-contact-form',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'ateculus-contact-form',
			__( 'Form Fields', 'ateculus-contact-form' ),
			__( 'Form Fields', 'ateculus-contact-form' ),
			'manage_options',
			'ateculus-contact-fields',
			array( ACF_Fields::get_instance(), 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'acf_settings_group',
			ACF_OPTION_KEY,
			array( $this, 'sanitize_settings' )
		);
	}

	public function sanitize_settings( $input ) {
		$clean = array();

		$clean['provider']        = sanitize_text_field( $input['provider'] ?? 'phpmail' );
		$clean['smtp_host']       = sanitize_text_field( $input['smtp_host'] ?? '' );
		$clean['smtp_port']       = absint( $input['smtp_port'] ?? 587 );
		$clean['smtp_encryption'] = in_array( $input['smtp_encryption'] ?? '', array( '', 'tls', 'ssl' ), true )
			? $input['smtp_encryption']
			: 'tls';
		$clean['smtp_auth']       = ! empty( $input['smtp_auth'] ) ? '1' : '0';
		$clean['smtp_username']   = sanitize_text_field( $input['smtp_username'] ?? '' );

		// Only update password if a new one was entered
		$existing                 = self::get();
		$clean['smtp_password']   = ! empty( $input['smtp_password'] )
			? $input['smtp_password']
			: ( $existing['smtp_password'] ?? '' );

		$clean['from_name']       = sanitize_text_field( $input['from_name'] ?? '' );
		$clean['from_email']      = sanitize_email( $input['from_email'] ?? '' );
		$clean['to_email']        = sanitize_email( $input['to_email'] ?? '' );
		$clean['success_message'] = wp_kses_post( $input['success_message'] ?? '' );
		$clean['error_message']   = wp_kses_post( $input['error_message'] ?? '' );

		// Turnstile
		$clean['turnstile_enabled']  = ! empty( $input['turnstile_enabled'] ) ? '1' : '0';
		$clean['turnstile_site_key'] = sanitize_text_field( $input['turnstile_site_key'] ?? '' );
		$clean['turnstile_secret_key'] = ! empty( $input['turnstile_secret_key'] )
			? sanitize_text_field( $input['turnstile_secret_key'] )
			: ( $existing['turnstile_secret_key'] ?? '' );

		// Notifications
		$clean['notify_email']       = ! empty( $input['notify_email'] ) ? '1' : '0';
		$clean['notify_discord']     = ! empty( $input['notify_discord'] ) ? '1' : '0';
		$clean['notify_discord_url'] = esc_url_raw( $input['notify_discord_url'] ?? '' );
		$clean['notify_slack']       = ! empty( $input['notify_slack'] ) ? '1' : '0';
		$clean['notify_slack_url']   = esc_url_raw( $input['notify_slack_url'] ?? '' );

		return $clean;
	}

	public function enqueue_admin_assets( $hook ) {
		if ( empty( $_GET['page'] ) || 'ateculus-contact-form' !== $_GET['page'] ) {
			return;
		}
		wp_enqueue_style(
			'acf-admin',
			ACF_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			ACF_VERSION
		);
		wp_enqueue_script(
			'acf-admin',
			ACF_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			ACF_VERSION,
			true
		);
		wp_localize_script( 'acf-admin', 'acfAdmin', array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'acf_admin_nonce' ),
			'providers' => self::PROVIDERS,
		) );
	}

	public function ajax_get_provider_config() {
		check_ajax_referer( 'acf_admin_nonce', 'nonce' );
		$provider = sanitize_text_field( $_POST['provider'] ?? 'phpmail' );
		$config   = self::PROVIDERS[ $provider ] ?? self::PROVIDERS['phpmail'];
		wp_send_json_success( $config );
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings  = self::get();
		$providers = self::PROVIDERS;
		$current   = $settings['provider'] ?? 'phpmail';
		?>
		<div class="wrap acf-settings-wrap">
			<h1><?php esc_html_e( 'Contact Form Settings', 'ateculus-contact-form' ); ?></h1>

			<div class="acf-settings-layout">
				<div class="acf-settings-main">
					<form method="post" action="options.php" id="acf-settings-form">
						<?php settings_fields( 'acf_settings_group' ); ?>

						<!-- Shortcode Info -->
						<div class="acf-card acf-shortcode-info">
							<h2><?php esc_html_e( 'Shortcode', 'ateculus-contact-form' ); ?></h2>
							<p><?php esc_html_e( 'Use this shortcode to display the contact form on any page or post:', 'ateculus-contact-form' ); ?></p>
							<code class="acf-shortcode-tag">[ateculus_contact_form]</code>
						</div>

						<!-- Email Provider -->
						<div class="acf-card">
							<h2><?php esc_html_e( 'Email Provider', 'ateculus-contact-form' ); ?></h2>

							<table class="form-table">
								<tr>
									<th><label for="acf_provider"><?php esc_html_e( 'Provider', 'ateculus-contact-form' ); ?></label></th>
									<td>
										<select name="<?php echo ACF_OPTION_KEY; ?>[provider]" id="acf_provider">
											<?php foreach ( $providers as $key => $config ) : ?>
												<option value="<?php echo esc_attr( $key ); ?>"
													<?php selected( $current, $key ); ?>
													data-host="<?php echo esc_attr( $config['host'] ); ?>"
													data-port="<?php echo esc_attr( $config['port'] ); ?>"
													data-encryption="<?php echo esc_attr( $config['encryption'] ); ?>"
													data-auth="<?php echo $config['auth'] ? '1' : '0'; ?>"
												>
													<?php echo esc_html( $config['label'] ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
							</table>
						</div>

						<!-- SMTP Settings -->
						<div class="acf-card" id="acf-smtp-settings">
							<h2><?php esc_html_e( 'SMTP Configuration', 'ateculus-contact-form' ); ?></h2>

							<div class="acf-provider-notice" id="acf-provider-notice" style="display:none;"></div>

							<table class="form-table">
								<tr>
									<th><label for="acf_smtp_host"><?php esc_html_e( 'SMTP Host', 'ateculus-contact-form' ); ?></label></th>
									<td>
										<input type="text" name="<?php echo ACF_OPTION_KEY; ?>[smtp_host]"
											id="acf_smtp_host"
											value="<?php echo esc_attr( $settings['smtp_host'] ?? '' ); ?>"
											class="regular-text" placeholder="smtp.example.com" />
									</td>
								</tr>
								<tr>
									<th><label for="acf_smtp_port"><?php esc_html_e( 'SMTP Port', 'ateculus-contact-form' ); ?></label></th>
									<td>
										<input type="number" name="<?php echo ACF_OPTION_KEY; ?>[smtp_port]"
											id="acf_smtp_port"
											value="<?php echo esc_attr( $settings['smtp_port'] ?? '587' ); ?>"
											class="small-text" />
									</td>
								</tr>
								<tr>
									<th><label for="acf_smtp_encryption"><?php esc_html_e( 'Encryption', 'ateculus-contact-form' ); ?></label></th>
									<td>
										<select name="<?php echo ACF_OPTION_KEY; ?>[smtp_encryption]" id="acf_smtp_encryption">
											<option value="" <?php selected( $settings['smtp_encryption'] ?? '', '' ); ?>><?php esc_html_e( 'None', 'ateculus-contact-form' ); ?></option>
											<option value="tls" <?php selected( $settings['smtp_encryption'] ?? '', 'tls' ); ?>>TLS (STARTTLS)</option>
											<option value="ssl" <?php selected( $settings['smtp_encryption'] ?? '', 'ssl' ); ?>>SSL</option>
										</select>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'SMTP Auth', 'ateculus-contact-form' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="<?php echo ACF_OPTION_KEY; ?>[smtp_auth]"
												id="acf_smtp_auth" value="1"
												<?php checked( $settings['smtp_auth'] ?? '1', '1' ); ?> />
											<?php esc_html_e( 'Enable SMTP Authentication', 'ateculus-contact-form' ); ?>
										</label>
									</td>
								</tr>
								<tr id="acf-username-row">
									<th><label for="acf_smtp_username"><?php esc_html_e( 'Username', 'ateculus-contact-form' ); ?></label></th>
									<td>
										<input type="text" name="<?php echo ACF_OPTION_KEY; ?>[smtp_username]"
											id="acf_smtp_username"
											value="<?php echo esc_attr( $settings['smtp_username'] ?? '' ); ?>"
											class="regular-text" autocomplete="off" />
										<p class="description" id="acf-username-hint"></p>
									</td>
								</tr>
								<tr id="acf-password-row">
									<th><label for="acf_smtp_password"><?php esc_html_e( 'Password / API Key', 'ateculus-contact-form' ); ?></label></th>
									<td>
										<input type="password" name="<?php echo ACF_OPTION_KEY; ?>[smtp_password]"
											id="acf_smtp_password"
											value=""
											class="regular-text" autocomplete="new-password"
											placeholder="<?php esc_attr_e( 'Leave blank to keep existing password', 'ateculus-contact-form' ); ?>" />
										<p class="description" id="acf-password-hint"></p>
									</td>
								</tr>
							</table>
						</div>

						<!-- From / To Settings -->
						<div class="acf-card">
							<h2><?php esc_html_e( 'Email Addresses', 'ateculus-contact-form' ); ?></h2>
							<table class="form-table">
								<tr>
									<th><label for="acf_from_name"><?php esc_html_e( 'From Name', 'ateculus-contact-form' ); ?></label></th>
									<td>
										<input type="text" name="<?php echo ACF_OPTION_KEY; ?>[from_name]"
											id="acf_from_name"
											value="<?php echo esc_attr( $settings['from_name'] ?? '' ); ?>"
											class="regular-text" />
									</td>
								</tr>
								<tr>
									<th><label for="acf_from_email"><?php esc_html_e( 'From Email', 'ateculus-contact-form' ); ?></label></th>
									<td>
										<input type="email" name="<?php echo ACF_OPTION_KEY; ?>[from_email]"
											id="acf_from_email"
											value="<?php echo esc_attr( $settings['from_email'] ?? '' ); ?>"
											class="regular-text" />
									</td>
								</tr>
								<tr>
									<th><label for="acf_to_email"><?php esc_html_e( 'Send Emails To', 'ateculus-contact-form' ); ?></label></th>
									<td>
										<input type="email" name="<?php echo ACF_OPTION_KEY; ?>[to_email]"
											id="acf_to_email"
											value="<?php echo esc_attr( $settings['to_email'] ?? '' ); ?>"
											class="regular-text" />
										<p class="description"><?php esc_html_e( 'The address where contact form submissions will be delivered.', 'ateculus-contact-form' ); ?></p>
									</td>
								</tr>
							</table>
						</div>

						<!-- Messages -->
						<div class="acf-card">
							<h2><?php esc_html_e( 'Form Messages', 'ateculus-contact-form' ); ?></h2>
							<table class="form-table">
								<tr>
									<th><label for="acf_success_message"><?php esc_html_e( 'Success Message', 'ateculus-contact-form' ); ?></label></th>
									<td>
										<input type="text" name="<?php echo ACF_OPTION_KEY; ?>[success_message]"
											id="acf_success_message"
											value="<?php echo esc_attr( $settings['success_message'] ?? '' ); ?>"
											class="large-text" />
									</td>
								</tr>
								<tr>
									<th><label for="acf_error_message"><?php esc_html_e( 'Error Message', 'ateculus-contact-form' ); ?></label></th>
									<td>
										<input type="text" name="<?php echo ACF_OPTION_KEY; ?>[error_message]"
											id="acf_error_message"
											value="<?php echo esc_attr( $settings['error_message'] ?? '' ); ?>"
											class="large-text" />
									</td>
								</tr>
							</table>
						</div>

						<!-- Turnstile -->
						<div class="acf-card">
							<h2><?php esc_html_e( 'Cloudflare Turnstile', 'ateculus-contact-form' ); ?></h2>
							<p class="description" style="margin-bottom:16px;">
								<?php esc_html_e( 'Get free keys at', 'ateculus-contact-form' ); ?>
								<a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank">dash.cloudflare.com → Turnstile</a>.
								<?php esc_html_e( 'Create a site, choose "Managed", and paste both keys below.', 'ateculus-contact-form' ); ?>
							</p>
							<table class="form-table">
								<tr>
									<th><label for="acf_turnstile_site_key"><?php esc_html_e( 'Site Key', 'ateculus-contact-form' ); ?></label></th>
									<td>
										<input type="text" name="<?php echo ACF_OPTION_KEY; ?>[turnstile_site_key]"
											id="acf_turnstile_site_key"
											value="<?php echo esc_attr( $settings['turnstile_site_key'] ?? '' ); ?>"
											class="regular-text" placeholder="0x4AAAAAAA..." />
									</td>
								</tr>
								<tr>
									<th><label for="acf_turnstile_secret_key"><?php esc_html_e( 'Secret Key', 'ateculus-contact-form' ); ?></label></th>
									<td>
										<input type="password" name="<?php echo ACF_OPTION_KEY; ?>[turnstile_secret_key]"
											id="acf_turnstile_secret_key"
											value=""
											class="regular-text" autocomplete="new-password"
											placeholder="<?php esc_attr_e( 'Leave blank to keep existing key', 'ateculus-contact-form' ); ?>" />
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Enable Turnstile', 'ateculus-contact-form' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="<?php echo ACF_OPTION_KEY; ?>[turnstile_enabled]"
												id="acf_turnstile_enabled" value="1"
												<?php checked( $settings['turnstile_enabled'] ?? '0', '1' ); ?> />
											<?php esc_html_e( 'Show Turnstile widget on the contact form', 'ateculus-contact-form' ); ?>
										</label>
									</td>
								</tr>
							</table>
						</div>

						<!-- Notifications -->
						<div class="acf-card">
							<h2><?php esc_html_e( 'Notifications', 'ateculus-contact-form' ); ?></h2>
							<p class="description" style="margin-bottom:16px;">
								<?php esc_html_e( 'Choose where form submissions are sent. Tick the checkbox to activate a channel, paste the URL, then save.', 'ateculus-contact-form' ); ?>
							</p>

							<table class="form-table">

								<!-- Email -->
								<tr>
									<th><?php esc_html_e( 'Email', 'ateculus-contact-form' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="<?php echo ACF_OPTION_KEY; ?>[notify_email]"
												value="1" <?php checked( $settings['notify_email'] ?? '1', '1' ); ?> />
											<?php esc_html_e( 'Send email to the address configured in Email Addresses above', 'ateculus-contact-form' ); ?>
										</label>
									</td>
								</tr>

								<!-- Discord -->
								<tr>
									<th><?php esc_html_e( 'Discord', 'ateculus-contact-form' ); ?></th>
									<td>
										<label style="display:block;margin-bottom:8px;">
											<input type="checkbox" name="<?php echo ACF_OPTION_KEY; ?>[notify_discord]"
												value="1" <?php checked( $settings['notify_discord'] ?? '0', '1' ); ?> />
											<?php esc_html_e( 'Post to a Discord channel via webhook', 'ateculus-contact-form' ); ?>
										</label>
										<input type="url" name="<?php echo ACF_OPTION_KEY; ?>[notify_discord_url]"
											value="<?php echo esc_attr( $settings['notify_discord_url'] ?? '' ); ?>"
											class="large-text" placeholder="https://discord.com/api/webhooks/XXXXXXXXXX/XXXXXXXXXX" />
										<p class="description"><?php esc_html_e( 'Discord → Server Settings → Integrations → Webhooks → New Webhook → Copy Webhook URL', 'ateculus-contact-form' ); ?></p>
									</td>
								</tr>

								<!-- Slack -->
								<tr>
									<th><?php esc_html_e( 'Slack', 'ateculus-contact-form' ); ?></th>
									<td>
										<label style="display:block;margin-bottom:8px;">
											<input type="checkbox" name="<?php echo ACF_OPTION_KEY; ?>[notify_slack]"
												value="1" <?php checked( $settings['notify_slack'] ?? '0', '1' ); ?> />
											<?php esc_html_e( 'Post to a Slack channel via webhook', 'ateculus-contact-form' ); ?>
										</label>
										<input type="url" name="<?php echo ACF_OPTION_KEY; ?>[notify_slack_url]"
											value="<?php echo esc_attr( $settings['notify_slack_url'] ?? '' ); ?>"
											class="large-text" placeholder="https://hooks.slack.com/services/T.../B.../..." />
										<p class="description"><?php esc_html_e( 'api.slack.com → Your Apps → Incoming Webhooks → Add New Webhook to Workspace → copy the URL', 'ateculus-contact-form' ); ?></p>
									</td>
								</tr>

							</table>
						</div>

						<?php submit_button( __( 'Save Settings', 'ateculus-contact-form' ) ); ?>
					</form>
				</div>

				<!-- Sidebar -->
				<div class="acf-settings-sidebar">
					<div class="acf-card acf-help-card">
						<h3><?php esc_html_e( 'Provider Setup Guides', 'ateculus-contact-form' ); ?></h3>
						<ul>
							<li>
								<strong>Gmail:</strong>
								<?php esc_html_e( 'Use your Gmail address as the username. For the password, generate an App Password at', 'ateculus-contact-form' ); ?>
								<code>myaccount.google.com &rarr; Security &rarr; App Passwords</code>.
							</li>
							<li>
								<strong>Mailgun:</strong>
								<?php esc_html_e( 'Username is your full Mailgun SMTP login. Password is your Mailgun SMTP password from the dashboard.', 'ateculus-contact-form' ); ?>
							</li>
							<li>
								<strong>SendGrid:</strong>
								<?php esc_html_e( 'Username is always', 'ateculus-contact-form' ); ?> <code>apikey</code>.
								<?php esc_html_e( 'Password is your SendGrid API key.', 'ateculus-contact-form' ); ?>
							</li>
						</ul>
					</div>

					<div class="acf-card acf-theme-card">
						<h3><?php esc_html_e( 'Theme Customization', 'ateculus-contact-form' ); ?></h3>
						<p><?php esc_html_e( 'Override the form template by copying:', 'ateculus-contact-form' ); ?></p>
						<code>plugins/ateculus-contact-form/templates/form.php</code>
						<p><?php esc_html_e( 'to:', 'ateculus-contact-form' ); ?></p>
						<code>your-theme/ateculus-contact-form/form.php</code>
						<p><?php esc_html_e( 'CSS custom properties are available for quick color changes:', 'ateculus-contact-form' ); ?></p>
						<code>--acf-primary<br>--acf-bg<br>--acf-border<br>--acf-radius<br>--acf-font</code>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
