(function ($) {
	'use strict';

	// Provider-specific hints shown in the settings page
	var providerHints = {
		gmail: {
			notice:   'Gmail requires an App Password (not your regular Gmail password). Enable 2-Factor Authentication, then visit myaccount.google.com → Security → App Passwords.',
			username: 'Your full Gmail address (e.g. you@gmail.com)',
			password: 'Your Gmail App Password (16-character code)',
		},
		mailgun: {
			notice:   'Use your Mailgun SMTP credentials. Find them in the Mailgun dashboard under Sending → Domain Settings → SMTP credentials.',
			username: 'Your Mailgun SMTP login (e.g. postmaster@mg.yourdomain.com)',
			password: 'Your Mailgun SMTP password',
		},
		sendgrid: {
			notice:   'SendGrid SMTP: use "apikey" as the username and your SendGrid API Key as the password.',
			username: 'Always use: apikey',
			password: 'Your SendGrid API Key (starts with SG.)',
		},
		custom: {
			notice:   '',
			username: '',
			password: '',
		},
		phpmail: {
			notice:   '',
			username: '',
			password: '',
		},
	};

	$(document).ready(function () {

		/* Notification webhook URL toggles */
		$('.acf-notify-toggle').each(function () {
			var $target = $('#' + $(this).data('target'));
			if (! $(this).is(':checked')) {
				$target.hide();
			}
		});

		$(document).on('change', '.acf-notify-toggle', function () {
			var $target = $('#' + $(this).data('target'));
			if ($(this).is(':checked')) {
				$target.slideDown(150).find('input[type="url"]').focus();
			} else {
				$target.slideUp(150);
			}
		});

		/* Turnstile key rows toggle */
		function toggleTurnstileRows() {
			var $rows = $('.acf-turnstile-keys');
			if ($('#acf_turnstile_enabled').is(':checked')) {
				$rows.show();
			} else {
				$rows.hide();
			}
		}
		$('#acf_turnstile_enabled').on('change', toggleTurnstileRows);
		var $provider     = $('#acf_provider');
		var $smtpSection  = $('#acf-smtp-settings');
		var $notice       = $('#acf-provider-notice');
		var $host         = $('#acf_smtp_host');
		var $port         = $('#acf_smtp_port');
		var $encryption   = $('#acf_smtp_encryption');
		var $auth         = $('#acf_smtp_auth');
		var $usernameHint = $('#acf-username-hint');
		var $passwordHint = $('#acf-password-hint');
		var $usernameField = $('#acf_smtp_username');

		function applyProvider(provider, autofill) {
			var $opt    = $provider.find('option[value="' + provider + '"]');
			var hints   = providerHints[provider] || providerHints.custom;
			var isPhp   = provider === 'phpmail';

			// Toggle SMTP section visibility
			$smtpSection.toggleClass('acf-smtp-hidden', isPhp);

			// Provider notice
			if (hints.notice) {
				$notice.text(hints.notice).show();
			} else {
				$notice.hide().text('');
			}

			// Auto-fill SMTP fields from data attributes (only when switching providers)
			if (autofill && !isPhp) {
				var host       = $opt.data('host');
				var port       = $opt.data('port');
				var encryption = $opt.data('encryption');
				var auth       = $opt.data('auth');

				if (host)       $host.val(host);
				if (port)       $port.val(port);
				if (encryption) $encryption.val(encryption);
				$auth.prop('checked', !!auth);
			}

			// SendGrid: auto-set username to 'apikey'
			if (provider === 'sendgrid' && autofill) {
				$usernameField.val('apikey');
			}

			// Hints
			$usernameHint.text(hints.username || '');
			$passwordHint.text(hints.password || '');
		}

		// On page load, apply current provider (no autofill)
		applyProvider($provider.val(), false);

		// On change, apply with autofill
		$provider.on('change', function () {
			applyProvider($(this).val(), true);
		});
	});

}(jQuery));
