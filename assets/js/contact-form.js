(function ($) {
	'use strict';

	$(document).ready(function () {
		var $form   = $('#acf-form');
		var $btn    = $('#acf-submit-btn');
		var $btnTxt = $btn.find('.acf-btn-text');
		var $successNotice = $('#acf-contact-form .acf-notice--success');
		var $errorNotice   = $('#acf-contact-form .acf-notice--error');

		function clearErrors() {
			$form.find('.acf-input').removeClass('acf-input--error');
			$form.find('.acf-field-error').text('');
			$successNotice.hide().text('');
			$errorNotice.hide().text('');
		}

		function showFieldErrors(errors) {
			$.each(errors, function (field, message) {
				$('#' + field).addClass('acf-input--error');
				$('#' + field + '_error').text(message);
			});
		}

		function setLoading(loading) {
			$btn.prop('disabled', loading).toggleClass('acf-loading', loading);
			$btnTxt.text(loading ? acfForm.strings.sending : acfForm.strings.send);
		}

		$form.on('submit', function (e) {
			e.preventDefault();
			clearErrors();
			setLoading(true);

			var data = $form.serialize();

			$.ajax({
				url:    acfForm.ajaxUrl,
				method: 'POST',
				data:   data + '&action=acf_submit_form&nonce=' + acfForm.nonce,
				success: function (response) {
					if (response.success) {
						$form[0].reset();
						$successNotice.text(response.data.message).show();
						$('html, body').animate({
							scrollTop: $successNotice.offset().top - 80
						}, 400);
					} else {
						if (response.data.errors) {
							showFieldErrors(response.data.errors);
						}
						$errorNotice.text(response.data.message).show();
					}
				},
				error: function () {
					$errorNotice.text('An unexpected error occurred. Please try again.').show();
				},
				complete: function () {
					setLoading(false);
					// Reset Turnstile so the user can try again
					if (typeof turnstile !== 'undefined') {
						turnstile.reset();
					}
				}
			});
		});

		// Clear individual field error on input
		$form.on('input change', '.acf-input', function () {
			$(this).removeClass('acf-input--error');
			$('#' + $(this).attr('id') + '_error').text('');
		});

		// Phone number: strip non-digits and auto-format as (123) 444-4444
		$form.on('keydown', 'input[type="tel"]', function (e) {
			// Allow: backspace, delete, tab, escape, enter, arrows, home, end
			var allowed = [8, 9, 13, 27, 46, 35, 36, 37, 38, 39, 40];
			if (allowed.indexOf(e.which) !== -1) return;
			// Allow Ctrl/Cmd+A/C/V/X
			if ((e.ctrlKey || e.metaKey) && [65, 67, 86, 88].indexOf(e.which) !== -1) return;
			// Block anything that isn't a digit
			if (e.which < 48 || e.which > 57) {
				if (e.which < 96 || e.which > 105) { // numpad
					e.preventDefault();
				}
			}
		});

		$form.on('input', 'input[type="tel"]', function () {
			var digits = $(this).val().replace(/\D/g, '').slice(0, 10);
			var formatted = '';
			if (digits.length === 0) {
				formatted = '';
			} else if (digits.length <= 3) {
				formatted = '(' + digits;
			} else if (digits.length <= 6) {
				formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
			} else {
				formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
			}
			$(this).val(formatted);
		});
	});

}(jQuery));
