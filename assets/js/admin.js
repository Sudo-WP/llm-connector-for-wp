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
	// Reveal / Hide key toggle
	// ========================================
	$(document).on('click', '.wp-llm-reveal-key:not(:disabled)', function(e) {
		e.preventDefault();

		var $button = $(this);
		var $container = $button.closest('.wp-llm-key-container');
		var $code = $container.find('code.api-key-display');
		var $icon = $button.find('.dashicons');
		var key = $button.attr('data-key');

		if ($button.data('revealed')) {
			// Hide: restore masked display.
			$code.text('\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022');
			$code.addClass('wp-llm-api-key-hidden');
			$code.attr('title', wpLlmConnector.i18n.hiddenKeyTitle);
			$icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
			$button.contents().filter(function() { return this.nodeType === 3; }).last().replaceWith(' ' + wpLlmConnector.i18n.revealText);
			$button.attr('aria-label', wpLlmConnector.i18n.revealKeyLabel);
			$button.data('revealed', false);
		} else {
			// Reveal: show the key.
			$code.text(key);
			$code.removeClass('wp-llm-api-key-hidden');
			$code.attr('title', wpLlmConnector.i18n.revealedKeyTitle);
			$icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
			$button.contents().filter(function() { return this.nodeType === 3; }).last().replaceWith(' ' + wpLlmConnector.i18n.hideText);
			$button.attr('aria-label', wpLlmConnector.i18n.hideKeyLabel);
			$button.data('revealed', true);
		}
	});

	// ========================================
	// Copy key to clipboard
	// ========================================
	$(document).on('click', '.wp-llm-copy-key:not(:disabled)', function(e) {
		e.preventDefault();

		var $button = $(this);
		var apiKey = $button.attr('data-key');
		var $icon = $button.find('.dashicons');

		if (!apiKey || apiKey.length === 0) {
			return;
		}

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(apiKey).then(function() {
				showKeyCopySuccess($button, $icon);
			}).catch(function() {
				fallbackKeyCopy(apiKey, $button, $icon);
			});
		} else {
			fallbackKeyCopy(apiKey, $button, $icon);
		}
	});

	function showKeyCopySuccess($button, $icon) {
		$button.addClass('copied');
		$icon.removeClass('dashicons-clipboard').addClass('dashicons-yes');
		$button.contents().filter(function() { return this.nodeType === 3; }).last().replaceWith(' ' + wpLlmConnector.i18n.copiedText);

		setTimeout(function() {
			$button.removeClass('copied');
			$icon.removeClass('dashicons-yes').addClass('dashicons-clipboard');
			$button.contents().filter(function() { return this.nodeType === 3; }).last().replaceWith(' ' + wpLlmConnector.i18n.copyText);
		}, 2000);
	}

	function fallbackKeyCopy(text, $button, $icon) {
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
			showKeyCopySuccess($button, $icon);
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
