<?php
/**
 * Default contact form template.
 * Override by copying to: your-theme/ateculus-contact-form/form.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title      = ! empty( $atts['title'] ) ? $atts['title'] : __( 'Get In Touch', 'ateculus-contact-form' );
$show_title = filter_var( $atts['show_title'] ?? 'true', FILTER_VALIDATE_BOOLEAN );
$fields     = ACF_Fields::get_fields();
$settings   = ACF_Settings::get();
$turnstile  = ! empty( $settings['turnstile_enabled'] ) && ! empty( $settings['turnstile_site_key'] );
$col_map    = array( '50' => 'acf-col-half', '100' => 'acf-col-full', '33' => 'acf-col-third' );
?>
<div class="acf-contact-wrapper" id="acf-contact-form">

	<?php if ( $show_title ) : ?>
		<h2 class="acf-form-title"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>

	<div class="acf-notice acf-notice--success" role="alert" aria-live="polite" style="display:none;"></div>
	<div class="acf-notice acf-notice--error"   role="alert" aria-live="polite" style="display:none;"></div>

	<form class="acf-form" id="acf-form" novalidate>

		<!-- Honeypot -->
		<div style="position:absolute;left:-9999px;top:-9999px;height:0;overflow:hidden;" aria-hidden="true">
			<input type="text" name="acf_website" tabindex="-1" autocomplete="off" value="" />
		</div>

		<div class="acf-fields-grid">
			<?php foreach ( $fields as $field ) :
				$fid      = esc_attr( $field['id'] );
				$required = ! empty( $field['required'] );
				$col      = $col_map[ $field['width'] ] ?? 'acf-col-full';
			?>
			<div class="acf-field-wrap <?php echo esc_attr( $col ); ?>">
				<div class="acf-field">
					<label for="<?php echo $fid; ?>" class="acf-label">
						<?php echo esc_html( $field['label'] ); ?>
						<?php if ( $required ) : ?><span class="acf-required" aria-hidden="true">*</span><?php endif; ?>
					</label>

					<?php if ( 'textarea' === $field['type'] ) : ?>
						<textarea
							id="<?php echo $fid; ?>"
							name="<?php echo $fid; ?>"
							class="acf-input acf-textarea"
							rows="5"
							placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
							<?php echo $required ? 'required' : ''; ?>
						></textarea>

					<?php elseif ( 'select' === $field['type'] ) : ?>
						<select id="<?php echo $fid; ?>" name="<?php echo $fid; ?>" class="acf-input"
							<?php echo $required ? 'required' : ''; ?>>
							<?php if ( $field['placeholder'] ) : ?>
								<option value=""><?php echo esc_html( $field['placeholder'] ); ?></option>
							<?php endif; ?>
							<?php foreach ( array_filter( array_map( 'trim', explode( "\n", $field['options'] ) ) ) as $opt ) : ?>
								<option value="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $opt ); ?></option>
							<?php endforeach; ?>
						</select>

					<?php else : ?>
						<input
							type="<?php echo esc_attr( $field['type'] ); ?>"
							id="<?php echo $fid; ?>"
							name="<?php echo $fid; ?>"
							class="acf-input"
							placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
							<?php echo $required ? 'required' : ''; ?>
						/>
					<?php endif; ?>

					<span class="acf-field-error" id="<?php echo $fid; ?>_error" role="alert"></span>
				</div>
			</div>
			<?php endforeach; ?>

			<?php if ( $turnstile ) : ?>
			<div class="acf-field-wrap acf-col-full">
				<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $settings['turnstile_site_key'] ); ?>"></div>
			</div>
			<?php endif; ?>

			<div class="acf-field-wrap acf-col-full">
				<div class="acf-submit-row">
					<button type="submit" class="acf-submit-btn" id="acf-submit-btn">
						<span class="acf-btn-text"><?php esc_html_e( 'Send Message', 'ateculus-contact-form' ); ?></span>
						<span class="acf-btn-spinner" aria-hidden="true"></span>
					</button>
				</div>
			</div>
		</div>

	</form>
</div>
