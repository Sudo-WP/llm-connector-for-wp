jQuery(document).ready(function($) {
	'use strict';

	// ========================================
	// Confirm before disabling connector
	// ========================================
	var $enabledCheckbox = $('input[name="wp_llm_connector_settings[enabled]"]');
	$enabledCheckbox.data('initial-state', $enabledCheckbox.is(':checked'));

	$('form').on('submit', function(e) {
		var wasEnabled = $enabledCheckbox.data('initial-state');
		if (wasEnabled && !$enabledCheckbox.is(':checked')) {
			if (!confirm('Are you sure you want to disable the LLM Connector? All API access will be blocked.')) {
				e.preventDefault();
				$enabledCheckbox.prop('checked', true);
				return false;
			}
		}
		$enabledCheckbox.data('initial-state', $enabledCheckbox.is(':checked'));
	});

	// ========================================
	// Copy new API key to clipboard
	// Key is in data-key attribute and gets
	// removed from DOM after successful copy.
	// ========================================
	$(document).on('click', '.wp-llm-copy-new-key', function(e) {
		e.preventDefault();

		var $button = $(this);
		var apiKey = $button.attr('data-key');

		if (!apiKey || apiKey.length === 0) {
			alert('API key has already been copied and removed for security. Please generate a new key if needed.');
			return;
		}

		var $icon = $button.find('.dashicons');

		// Try modern clipboard API first.
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(apiKey).then(function() {
				showCopySuccess($button, $icon);
				// Security: remove key from DOM after successful copy.
				$button.removeAttr('data-key');
			}).catch(function() {
				fallbackCopy(apiKey, $button, $icon);
			});
		} else {
			fallbackCopy(apiKey, $button, $icon);
		}
	});

	// ========================================
	// Copy success visual feedback
	// ========================================
	function showCopySuccess($button, $icon) {
		$button.addClass('copied');
		$icon.removeClass('dashicons-clipboard').addClass('dashicons-yes');
		$button.find('.wp-llm-btn-text').text(wpLlmConnector.i18n.copiedText);

		// Reset after 2 seconds.
		setTimeout(function() {
			$button.removeClass('copied');
			$icon.removeClass('dashicons-yes').addClass('dashicons-clipboard');
			$button.find('.wp-llm-btn-text').text(wpLlmConnector.i18n.copyText);
		}, 2000);
	}

	// ========================================
	// Fallback copy for older browsers
	// ========================================
	function fallbackCopy(text, $button, $icon) {
		var $temp = $('<textarea>');
		var success = false;

		$('body').append($temp);
		$temp.val(text).select();

		try {
			success = document.execCommand('copy');
		} catch (err) {
			success = false;
		}

		$temp.remove();

		if (success) {
			showCopySuccess($button, $icon);
			// Security: remove key from DOM after successful copy.
			$button.removeAttr('data-key');
		} else {
			alert(wpLlmConnector.i18n.copyError);
		}
	}

	// ========================================
	// Scroll to new key row if present
	// ========================================
	var $newKeyRow = $('.wp-llm-new-key-row');
	if ($newKeyRow.length) {
		setTimeout(function() {
			$newKeyRow[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
		}, 300);
	}
});
