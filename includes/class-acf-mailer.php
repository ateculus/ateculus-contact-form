<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ACF_Mailer {

	/**
	 * @param array $fields  Field config from ACF_Fields::get_fields()
	 * @param array $values  Sanitized values keyed by field id
	 */
	public static function send( $fields, $values ) {
		$settings = ACF_Settings::get();
		$provider = $settings['provider'] ?? 'phpmail';

		if ( 'phpmail' !== $provider ) {
			add_action( 'phpmailer_init', array( __CLASS__, 'configure_phpmailer' ) );
		}

		// Extract key values for the email envelope
		$from_name   = '';
		$reply_email = '';
		$subject     = '';

		foreach ( $fields as $field ) {
			$val = $values[ $field['id'] ] ?? '';
			if ( empty( $from_name ) && 'text' === $field['type'] && false !== strpos( $field['id'], 'name' ) ) {
				$from_name = $val;
			}
			if ( empty( $reply_email ) && 'email' === $field['type'] && is_email( $val ) ) {
				$reply_email = $val;
			}
			if ( empty( $subject ) && ! empty( $val )
				&& ( false !== strpos( $field['id'], 'service' ) || false !== strpos( $field['id'], 'subject' ) )
			) {
				$subject = $val;
			}
		}

		if ( empty( $subject ) ) {
			$subject = __( 'New Contact Form Submission', 'ateculus-contact-form' );
		}

		$sender_name  = $settings['from_name']  ?: get_bloginfo( 'name' );
		$sender_email = $settings['from_email'] ?: get_option( 'admin_email' );
		$send_to      = $settings['to_email']   ?: get_option( 'admin_email' );

		add_filter( 'wp_mail_from', function () use ( $sender_email ) { return $sender_email; } );
		add_filter( 'wp_mail_from_name', function () use ( $sender_name ) { return $sender_name; } );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( $reply_email ) {
			$headers[] = sprintf( 'Reply-To: %s <%s>', $from_name ?: $reply_email, $reply_email );
		}

		$email_subject = sprintf(
			/* translators: 1: site name, 2: subject */
			__( '[%1$s] %2$s', 'ateculus-contact-form' ),
			get_bloginfo( 'name' ),
			$subject
		);

		$email_body = self::build_email_body( $fields, $values );
		$result     = wp_mail( $send_to, $email_subject, $email_body, $headers );

		if ( 'phpmail' !== $provider ) {
			remove_action( 'phpmailer_init', array( __CLASS__, 'configure_phpmailer' ) );
		}

		return $result;
	}

	public static function configure_phpmailer( $phpmailer ) {
		$settings = ACF_Settings::get();

		$phpmailer->isSMTP();
		$phpmailer->Host     = $settings['smtp_host'] ?? '';
		$phpmailer->Port     = intval( $settings['smtp_port'] ?? 587 );
		$phpmailer->SMTPAuth = ! empty( $settings['smtp_auth'] );
		$phpmailer->Username = $settings['smtp_username'] ?? '';
		$phpmailer->Password = $settings['smtp_password'] ?? '';

		$enc = $settings['smtp_encryption'] ?? '';
		if ( 'ssl' === $enc ) {
			$phpmailer->SMTPSecure = 'ssl';
		} elseif ( 'tls' === $enc ) {
			$phpmailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
		} else {
			$phpmailer->SMTPSecure  = '';
			$phpmailer->SMTPAutoTLS = false;
		}
	}

	private static function build_email_body( $fields, $values ) {
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();
		$date      = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );

		ob_start();
		?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
  .wrapper { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; }
  .header { background: #1a1a2e; color: #fff; padding: 24px 32px; }
  .header h2 { margin: 0; font-size: 20px; }
  .body { padding: 32px; }
  .field { margin-bottom: 20px; }
  .field-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 6px; }
  .field-value { font-size: 15px; color: #333; border-left: 3px solid #4f46e5; padding-left: 12px; }
  .field-value.message { background: #f8f8f8; border-radius: 6px; padding: 14px; white-space: pre-wrap; border-left: none; }
  .footer { background: #f8f8f8; padding: 16px 32px; font-size: 12px; color: #888; border-top: 1px solid #eee; }
  a { color: #4f46e5; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h2><?php esc_html_e( 'New Contact Form Submission', 'ateculus-contact-form' ); ?></h2>
    <p style="margin:4px 0 0;opacity:.7;font-size:13px;"><?php echo esc_html( $site_name ); ?></p>
  </div>
  <div class="body">
    <?php foreach ( $fields as $field ) :
      $val = $values[ $field['id'] ] ?? '';
      if ( '' === $val ) continue;
      $is_message = ( 'textarea' === $field['type'] );
      $is_email   = ( 'email' === $field['type'] );
    ?>
    <div class="field">
      <div class="field-label"><?php echo esc_html( $field['label'] ); ?></div>
      <div class="field-value<?php echo $is_message ? ' message' : ''; ?>">
        <?php if ( $is_email ) : ?>
          <a href="mailto:<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $val ); ?></a>
        <?php elseif ( $is_message ) : ?>
          <?php echo nl2br( esc_html( $val ) ); ?>
        <?php else : ?>
          <?php echo esc_html( $val ); ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <div class="field">
      <div class="field-label"><?php esc_html_e( 'Submitted At', 'ateculus-contact-form' ); ?></div>
      <div class="field-value"><?php echo esc_html( $date ); ?></div>
    </div>
  </div>
  <div class="footer">
    <?php
    /* translators: %s: site URL */
    printf( esc_html__( 'Sent via the contact form at %s', 'ateculus-contact-form' ),
      '<a href="' . esc_url( $site_url ) . '">' . esc_html( $site_url ) . '</a>'
    );
    ?>
  </div>
</div>
</body>
</html>
		<?php
		return ob_get_clean();
	}
}
