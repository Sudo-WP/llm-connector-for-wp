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
	// Warning when disabling read-only mode
	// ========================================
	$('input[name="wp_llm_connector_settings[read_only_mode]"]').on('change', function() {
		if (!$(this).is(':checked')) {
			if (!confirm('WARNING: Disabling read-only mode will allow LLMs to make changes to your site. This is not recommended unless you fully trust the LLM provider and have backups in place.\n\nContinue anyway?')) {
				$(this).prop('checked', true);
			}
		}
	});

	// ========================================
	// Reveal/Hide API key functionality
	// ========================================
	$(document).on('click', '.wp-llm-reveal-key:not(:disabled)', function(e) {
		e.preventDefault();
		var $button = $(this);
		var $keyElement = $('#wp-llm-generated-key');
		var apiKey = $button.data('key');
		
		if (typeof wpLlmConnector === 'undefined') {
			console.error('wpLlmConnector is not defined');
			return;
		}

		if ($keyElement.hasClass('wp-llm-api-key-hidden')) {
			// Reveal the key
			$keyElement
				.removeClass('wp-llm-api-key-hidden')
				.addClass('wp-llm-api-key-revealed')
				.text(apiKey)
				.attr('title', wpLlmConnector.i18n.revealedKeyTitle);
			
			$button
				.addClass('revealed')
				.attr('aria-label', wpLlmConnector.i18n.hideKeyLabel)
				.find('.dashicons')
				.removeClass('dashicons-visibility')
				.addClass('dashicons-hidden');
			
			$button.contents().filter(function() {
				return this.nodeType === 3; // Text node
			}).first().replaceWith(' ' + wpLlmConnector.i18n.hideText);
			
		} else {
			// Hide the key
			$keyElement
				.removeClass('wp-llm-api-key-revealed')
				.addClass('wp-llm-api-key-hidden')
				.text('••••••••••••••••••••••••••••••••')
				.attr('title', wpLlmConnector.i18n.hiddenKeyTitle);
			
			$button
				.removeClass('revealed')
				.attr('aria-label', wpLlmConnector.i18n.revealKeyLabel)
				.find('.dashicons')
				.removeClass('dashicons-hidden')
				.addClass('dashicons-visibility');
			
			$button.contents().filter(function() {
				return this.nodeType === 3; // Text node
			}).first().replaceWith(' ' + wpLlmConnector.i18n.revealText);
		}
	});

	// ========================================
	// Copy API key to clipboard
	// ========================================
	$(document).on('click', '.wp-llm-copy-key:not(:disabled)', function(e) {
		e.preventDefault();
		var $button = $(this);
		var apiKey = $button.data('key');
		var $icon = $button.find('.dashicons');
		var originalText = $button.contents().filter(function() {
			return this.nodeType === 3;
		}).text();

		if (typeof wpLlmConnector === 'undefined') {
			console.error('wpLlmConnector is not defined');
			alert('Failed to copy to clipboard. Please select and copy the key manually.');
			return;
		}

		// Try modern clipboard API first
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(apiKey).then(function() {
				// Success
				$button
					.addClass('copied')
					.attr('aria-label', wpLlmConnector.i18n.copiedLabel);
				
				$icon
					.removeClass('dashicons-clipboard')
					.addClass('dashicons-yes');
				
				$button.contents().filter(function() {
					return this.nodeType === 3;
				}).first().replaceWith(' ' + wpLlmConnector.i18n.copiedText);
				
				// Reset after 2 seconds
				setTimeout(function() {
					$button
						.removeClass('copied')
						.attr('aria-label', wpLlmConnector.i18n.copyLabel);
					
					$icon
						.removeClass('dashicons-yes')
						.addClass('dashicons-clipboard');
					
					$button.contents().filter(function() {
						return this.nodeType === 3;
					}).first().replaceWith(' ' + originalText);
				}, 2000);
			}).catch(function() {
				// Fallback on error
				fallbackCopyToClipboard(apiKey, $button, $icon, originalText);
			});
		} else {
			// Fallback for older browsers
			fallbackCopyToClipboard(apiKey, $button, $icon, originalText);
		}
	});

	// ========================================
	// Fallback copy method for older browsers
	// ========================================
	function fallbackCopyToClipboard(text, $button, $icon, originalText) {
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
			if (typeof wpLlmConnector !== 'undefined') {
				$button
					.addClass('copied')
					.attr('aria-label', wpLlmConnector.i18n.copiedLabel);
				
				$icon
					.removeClass('dashicons-clipboard')
					.addClass('dashicons-yes');
				
				$button.contents().filter(function() {
					return this.nodeType === 3;
				}).first().replaceWith(' ' + wpLlmConnector.i18n.copiedText);
				
				setTimeout(function() {
					$button
						.removeClass('copied')
						.attr('aria-label', wpLlmConnector.i18n.copyLabel);
					
					$icon
						.removeClass('dashicons-yes')
						.addClass('dashicons-clipboard');
					
					$button.contents().filter(function() {
						return this.nodeType === 3;
					}).first().replaceWith(' ' + originalText);
				}, 2000);
			} else {
				$button.text('Copied!');
				setTimeout(function() {
					$button.text(originalText);
				}, 2000);
			}
		} else {
			if (typeof wpLlmConnector !== 'undefined') {
				alert(wpLlmConnector.i18n.copyError);
			} else {
				alert('Failed to copy to clipboard. Please select and copy the key manually.');
			}
		}
	}

	// ========================================
	// Double-click to temporarily reveal
	// ========================================
	$(document).on('dblclick', '#wp-llm-generated-key.wp-llm-api-key-hidden', function(e) {
		e.preventDefault();
		var $keyElement = $(this);
		var $revealBtn = $('.wp-llm-reveal-key[data-key]').first();
		
		if ($revealBtn.length && !$revealBtn.hasClass('revealed')) {
			// Trigger reveal
			$revealBtn.trigger('click');
			
			// Auto-hide after 5 seconds
			setTimeout(function() {
				if ($revealBtn.hasClass('revealed')) {
					$revealBtn.trigger('click');
				}
			}, 5000);
		}
	});

	// Add tooltip for double-click hint
	$('#wp-llm-generated-key').attr('title', function(index, attr) {
		return attr + ' (Double-click to reveal for 5 seconds)';
	});
});
