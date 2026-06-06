(function ($) {
	'use strict';

	var s = acfBuilder.strings;

	var TYPE_LABELS = {
		text:     'Text',
		email:    'Email',
		tel:      'Phone',
		number:   'Number',
		textarea: 'Textarea',
		select:   'Dropdown',
	};

	/* ── Helpers ── */

	function esc(str) {
		return $('<div>').text(String(str || '')).html();
	}

	function escAttr(str) {
		return String(str || '')
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	function labelToId(label) {
		return 'acf_' + label.toLowerCase()
			.replace(/[^a-z0-9\s_]/g, '')
			.trim()
			.replace(/\s+/g, '_');
	}

	/* ── Field item HTML ── */

	function buildFieldHTML(field, isNew) {
		var id          = field.id          || '';
		var type        = field.type        || 'text';
		var label       = field.label       || '';
		var placeholder = field.placeholder || '';
		var required    = !! field.required;
		var width       = field.width       || '100';
		var options     = field.options     || '';

		var typeOptions = Object.keys(TYPE_LABELS).map(function (t) {
			return '<option value="' + t + '"' + (type === t ? ' selected' : '') + '>' + TYPE_LABELS[t] + '</option>';
		}).join('');

		var badgesHtml =
			'<span class="acfb-badge acfb-type-badge">' + esc(TYPE_LABELS[type] || type) + '</span>' +
			'<span class="acfb-badge acfb-width-badge">' + esc(width) + '%</span>' +
			(required ? '<span class="acfb-badge acfb-required-badge">Required</span>' : '');

		var editDisplay = isNew ? '' : ' style="display:none;"';

		return (
			'<div class="acfb-field-item">' +

				'<div class="acfb-field-summary">' +
					'<span class="acfb-drag-handle dashicons dashicons-menu" title="Drag to reorder"></span>' +
					'<span class="acfb-field-label-display">' + (esc(label) || '<em style="color:#aaa;">New Field</em>') + '</span>' +
					'<div class="acfb-badges">' + badgesHtml + '</div>' +
					'<div class="acfb-field-actions">' +
						'<button type="button" class="button acfb-edit-btn">Edit</button>' +
						'<button type="button" class="acfb-delete-btn dashicons dashicons-trash" title="Delete field"></button>' +
					'</div>' +
				'</div>' +

				'<div class="acfb-field-edit"' + editDisplay + '>' +
					'<div class="acfb-edit-grid">' +

						'<div class="acfb-edit-col">' +
							'<label>Label</label>' +
							'<input type="text" class="regular-text acfb-label" value="' + escAttr(label) + '" placeholder="Field Label" />' +
						'</div>' +

						'<div class="acfb-edit-col">' +
							'<label>Field ID</label>' +
							'<input type="text" class="regular-text acfb-id" value="' + escAttr(id) + '" placeholder="' + escAttr(s.idPlaceholder) + '"' + (isNew ? '' : ' readonly') + ' />' +
						'</div>' +

						'<div class="acfb-edit-col">' +
							'<label>Type</label>' +
							'<select class="acfb-type">' + typeOptions + '</select>' +
						'</div>' +

						'<div class="acfb-edit-col">' +
							'<label>Width</label>' +
							'<select class="acfb-width">' +
								'<option value="50"'  + (width === '50'  ? ' selected' : '') + '>Half (50%)</option>' +
								'<option value="100"' + (width === '100' ? ' selected' : '') + '>Full (100%)</option>' +
							'</select>' +
						'</div>' +

						'<div class="acfb-edit-col acfb-col-full">' +
							'<label>Placeholder</label>' +
							'<input type="text" class="large-text acfb-placeholder" value="' + escAttr(placeholder) + '" placeholder="Placeholder text..." />' +
						'</div>' +

						'<div class="acfb-edit-col acfb-col-full acfb-options-wrap"' + (type === 'select' ? '' : ' style="display:none;"') + '>' +
							'<label>Options <span class="description">(one per line)</span></label>' +
							'<textarea class="large-text acfb-options" rows="5">' + esc(options) + '</textarea>' +
						'</div>' +

						'<div class="acfb-edit-col acfb-col-required">' +
							'<label>' +
								'<input type="checkbox" class="acfb-required"' + (required ? ' checked' : '') + ' /> ' +
								'Required field' +
							'</label>' +
						'</div>' +

					'</div>' +
					'<div class="acfb-edit-footer">' +
						'<button type="button" class="button button-primary acfb-done-btn">Done</button>' +
						'<button type="button" class="button acfb-cancel-btn">Cancel</button>' +
					'</div>' +
				'</div>' +

			'</div>'
		);
	}

	/* ── Update summary row from edit values ── */

	function updateSummary($item) {
		var label    = $item.find('.acfb-label').val();
		var type     = $item.find('.acfb-type').val();
		var width    = $item.find('.acfb-width').val();
		var required = $item.find('.acfb-required').is(':checked');

		$item.find('.acfb-field-label-display')
			.html(label ? esc(label) : '<em style="color:#aaa;">New Field</em>');

		$item.find('.acfb-type-badge').text(TYPE_LABELS[type] || type);
		$item.find('.acfb-width-badge').text(width + '%');

		var $req = $item.find('.acfb-required-badge');
		if (required) {
			if (!$req.length) {
				$item.find('.acfb-badges').append('<span class="acfb-badge acfb-required-badge">Required</span>');
			}
		} else {
			$req.remove();
		}
	}

	/* ── Render all fields into the list ── */

	function renderFields(fields) {
		var $list = $('#acfb-field-list').empty();

		if (!fields || !fields.length) {
			$list.html('<p class="acfb-empty">No fields yet. Click <strong>+ Add Field</strong> to get started.</p>');
			return;
		}

		fields.forEach(function (field) {
			$list.append(buildFieldHTML(field, false));
		});

		initSortable();
	}

	/* ── jQuery UI Sortable ── */

	function initSortable() {
		$('#acfb-field-list').sortable({
			handle:      '.acfb-drag-handle',
			placeholder: 'acfb-field-item ui-sortable-placeholder',
			axis:        'y',
			tolerance:   'pointer',
			update: function () {
				// Order updated in DOM; collected on save
			},
		});
	}

	/* ── Collect all field data from DOM ── */

	function collectFields() {
		var fields = [];
		$('#acfb-field-list .acfb-field-item').each(function () {
			var $item = $(this);
			var id    = $item.find('.acfb-id').val().trim();
			if (!id) return; // skip fields with no ID yet
			fields.push({
				id:          id,
				type:        $item.find('.acfb-type').val(),
				label:       $item.find('.acfb-label').val(),
				placeholder: $item.find('.acfb-placeholder').val(),
				required:    $item.find('.acfb-required').is(':checked'),
				width:       $item.find('.acfb-width').val(),
				options:     $item.find('.acfb-options').val(),
			});
		});
		return fields;
	}

	/* ── Status display ── */

	function showStatus(msg, type) {
		var $s = $('#acfb-status').text(msg)
			.removeClass('success error')
			.addClass(type || 'success');
		setTimeout(function () { $s.text('').removeClass('success error'); }, 4000);
	}

	/* ── Init ── */

	$(document).ready(function () {

		// Render initial fields
		renderFields(acfBuilder.fields);

		/* Add Field */
		$('#acfb-add-btn').on('click', function () {
			var html = buildFieldHTML({}, true);
			var $item = $(html).appendTo('#acfb-field-list');
			// Close any other open edit panels
			$('#acfb-field-list .acfb-field-edit').not($item.find('.acfb-field-edit')).slideUp(150);
			$item.find('.acfb-field-edit').show();
			$item.find('.acfb-label').focus();
			$('#acfb-field-list p.acfb-empty').remove();

			// Scroll to new field
			$('html, body').animate({ scrollTop: $item.offset().top - 80 }, 300);
		});

		/* Edit toggle */
		$(document).on('click', '.acfb-edit-btn', function () {
			var $item  = $(this).closest('.acfb-field-item');
			var $panel = $item.find('.acfb-field-edit');
			var isOpen = $panel.is(':visible');

			// Close all others first
			$('#acfb-field-list .acfb-field-edit').slideUp(150);

			if (!isOpen) {
				$panel.slideDown(150);
				$item.find('.acfb-label').focus();
			}
		});

		/* Done */
		$(document).on('click', '.acfb-done-btn', function () {
			var $item  = $(this).closest('.acfb-field-item');
			var $id    = $item.find('.acfb-id');

			// Auto-generate ID for new fields if still empty
			if (!$id.val().trim()) {
				var label = $item.find('.acfb-label').val();
				if (label) {
					$id.val(labelToId(label));
				}
			}

			updateSummary($item);
			$item.find('.acfb-field-edit').slideUp(150);
		});

		/* Cancel */
		$(document).on('click', '.acfb-cancel-btn', function () {
			var $item  = $(this).closest('.acfb-field-item');
			var id     = $item.find('.acfb-id').val().trim();

			// If this is a brand-new unsaved field (no ID), remove it
			if (!id) {
				$item.remove();
				if (!$('#acfb-field-list .acfb-field-item').length) {
					$('#acfb-field-list').html('<p class="acfb-empty">No fields yet. Click <strong>+ Add Field</strong> to get started.</p>');
				}
			} else {
				$item.find('.acfb-field-edit').slideUp(150);
			}
		});

		/* Delete */
		$(document).on('click', '.acfb-delete-btn', function () {
			if (!confirm(s.confirmDelete)) return;
			var $item = $(this).closest('.acfb-field-item');
			$item.fadeOut(200, function () {
				$(this).remove();
				if (!$('#acfb-field-list .acfb-field-item').length) {
					$('#acfb-field-list').html('<p class="acfb-empty">No fields yet. Click <strong>+ Add Field</strong> to get started.</p>');
				}
			});
		});

		/* Auto-generate ID from label (new fields only) */
		$(document).on('input', '.acfb-label', function () {
			var $item = $(this).closest('.acfb-field-item');
			var $id   = $item.find('.acfb-id');
			if (!$id.prop('readonly')) {
				$id.val(labelToId($(this).val()));
			}
		});

		/* Show/hide options textarea based on type */
		$(document).on('change', '.acfb-type', function () {
			var $wrap = $(this).closest('.acfb-field-item').find('.acfb-options-wrap');
			if ($(this).val() === 'select') {
				$wrap.slideDown(150);
			} else {
				$wrap.slideUp(150);
			}
		});

		/* Save Fields */
		$('#acfb-save-btn').on('click', function () {
			var $btn   = $(this).prop('disabled', true).text('Saving…');
			var fields = collectFields();

			$.ajax({
				url:    acfBuilder.ajaxUrl,
				method: 'POST',
				data:   {
					action: 'acf_save_fields',
					nonce:  acfBuilder.nonce,
					fields: JSON.stringify(fields),
				},
				success: function (res) {
					if (res.success) {
						showStatus(s.saved, 'success');
					} else {
						showStatus(s.saveError, 'error');
					}
				},
				error: function () {
					showStatus(s.saveError, 'error');
				},
				complete: function () {
					$btn.prop('disabled', false).text('Save Fields');
				},
			});
		});

		/* Reset to Defaults */
		$('#acfb-reset-btn').on('click', function () {
			if (!confirm(s.confirmReset)) return;
			var $btn = $(this).prop('disabled', true);

			$.ajax({
				url:    acfBuilder.ajaxUrl,
				method: 'POST',
				data:   { action: 'acf_reset_fields', nonce: acfBuilder.nonce },
				success: function (res) {
					if (res.success) {
						renderFields(res.data.fields);
						showStatus(s.resetDone, 'success');
					}
				},
				complete: function () {
					$btn.prop('disabled', false);
				},
			});
		});

	});

}(jQuery));
