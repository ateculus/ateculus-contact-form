<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ACF_Notifier {

	/**
	 * Dispatch to all enabled notification channels.
	 * Returns true if at least one channel succeeded (or none were configured).
	 *
	 * @param array $fields Field config from ACF_Fields::get_fields()
	 * @param array $values Sanitized values keyed by field id
	 */
	public static function notify( $fields, $values ) {
		$settings = ACF_Settings::get();
		$results  = array();

		if ( ! empty( $settings['notify_email'] ) ) {
			$results['email'] = ACF_Mailer::send( $fields, $values );
		}

		if ( ! empty( $settings['notify_discord'] ) && ! empty( $settings['notify_discord_url'] ) ) {
			$results['discord'] = self::send_discord( $settings['notify_discord_url'], $fields, $values );
		}

		if ( ! empty( $settings['notify_slack'] ) && ! empty( $settings['notify_slack_url'] ) ) {
			$results['slack'] = self::send_slack( $settings['notify_slack_url'], $fields, $values );
		}

		// No channels configured → fail so the form doesn't silently drop submissions
		if ( empty( $results ) ) {
			return false;
		}

		return in_array( true, $results, true );
	}

	/* ── Discord ── */

	private static function send_discord( $webhook_url, $fields, $values ) {
		$embed_fields = array();

		foreach ( $fields as $field ) {
			$val = trim( $values[ $field['id'] ] ?? '' );
			if ( '' === $val ) {
				continue;
			}
			$embed_fields[] = array(
				'name'   => $field['label'],
				'value'  => $val,
				'inline' => ( 'textarea' !== $field['type'] ),
			);
		}

		$payload = array(
			'username'   => get_bloginfo( 'name' ) . ' — Contact Form',
			'embeds'     => array(
				array(
					'title'       => '📬 New Contact Form Submission',
					'color'       => 4915330,
					'fields'      => $embed_fields,
					'timestamp'   => gmdate( 'c' ),
					'footer'      => array( 'text' => home_url() ),
				),
			),
		);

		$response = wp_remote_post( $webhook_url, array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 10,
		) );

		return ! is_wp_error( $response )
			&& wp_remote_retrieve_response_code( $response ) < 300;
	}

	/* ── Slack ── */

	private static function send_slack( $webhook_url, $fields, $values ) {
		$blocks = array(
			array(
				'type' => 'header',
				'text' => array( 'type' => 'plain_text', 'text' => '📬 New Contact Form Submission', 'emoji' => true ),
			),
		);

		$pair_buffer = array();

		foreach ( $fields as $field ) {
			$val = trim( $values[ $field['id'] ] ?? '' );
			if ( '' === $val ) {
				continue;
			}

			if ( 'textarea' === $field['type'] ) {
				// Flush any buffered inline fields first
				if ( ! empty( $pair_buffer ) ) {
					$blocks[]    = array( 'type' => 'section', 'fields' => $pair_buffer );
					$pair_buffer = array();
				}
				$blocks[] = array(
					'type' => 'section',
					'text' => array(
						'type' => 'mrkdwn',
						'text' => '*' . $field['label'] . ':*' . "\n" . $val,
					),
				);
			} else {
				$pair_buffer[] = array( 'type' => 'mrkdwn', 'text' => '*' . $field['label'] . ':*' . "\n" . $val );
				if ( count( $pair_buffer ) >= 2 ) {
					$blocks[]    = array( 'type' => 'section', 'fields' => $pair_buffer );
					$pair_buffer = array();
				}
			}
		}

		if ( ! empty( $pair_buffer ) ) {
			$blocks[] = array( 'type' => 'section', 'fields' => $pair_buffer );
		}

		$blocks[] = array( 'type' => 'divider' );
		$blocks[] = array(
			'type'     => 'context',
			'elements' => array(
				array(
					'type' => 'mrkdwn',
					'text' => 'Submitted via <' . home_url() . '|' . get_bloginfo( 'name' ) . '> &bull; '
						. wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
				),
			),
		);

		$response = wp_remote_post( $webhook_url, array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array( 'blocks' => $blocks ) ),
			'timeout' => 10,
		) );

		return ! is_wp_error( $response )
			&& wp_remote_retrieve_response_code( $response ) < 300;
	}
}
