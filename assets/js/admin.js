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
	// (for existing keys in the table — providers line)
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
			$code.text('••••••••••••••••••••••••••••••••');
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
	// Copy key to clipboard (for existing keys)
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

	// ========================================
	// Access Log — toggle custom date inputs
	// ========================================
	var $rangeSelect = $('.wp-llm-log-range');
	if ($rangeSelect.length) {
		$rangeSelect.on('change', function() {
			var show = $(this).val() === 'custom';
			$('.wp-llm-log-custom-range').toggle(show);
		});
	}

	// ========================================
	// Connect Your AI Client — snippet renderer
	// ========================================
	var mcp = (wpLlmConnector && wpLlmConnector.mcp) || null;
	var $clientSelect = $('#wp-llm-client-select');
	var $snippetBox = $('#wp-llm-mcp-snippet');
	var $verifiedBadge = $('#wp-llm-client-verified');
	var $copyConfigBtn = $('#wp-llm-copy-config');
	var $testBtn = $('#wp-llm-test-connection');
	var $testStatus = $('#wp-llm-test-status');

	if (mcp && $clientSelect.length) {
		var i18n = wpLlmConnector.i18n || {};

		function maskKey() {
			// Prefer the plaintext key if we have one (just-generated), so the
			// preview reflects the real key's prefix. Fall back to the saved
			// prefix of the first active key, else the placeholder.
			var source = mcp.fullKey || mcp.maskedPrefix || i18n.placeholderKey || 'YOUR_API_KEY_HERE';
			var first8 = source.substring(0, 8);
			return first8 + '...';
		}

		function realKeyForCopy() {
			return mcp.fullKey && mcp.fullKey.length
				? mcp.fullKey
				: (i18n.placeholderKey || 'YOUR_API_KEY_HERE');
		}

		function baseConfig(key) {
			var serverId = 'wordpress-' + (mcp.siteSlug || 'site');
			var mcpServers = {};
			mcpServers[serverId] = {
				url: mcp.restUrl,
				headers: {}
			};
			mcpServers[serverId].headers[mcp.headerName] = key;
			return { mcpServers: mcpServers };
		}

		function renderSnippet(client, key) {
			var json = JSON.stringify(baseConfig(key), null, 2);
			switch (client) {
				case 'claude-code':
					return '// File: ~/.claude/mcp.json\n' + json;
				case 'gemini-cli':
					return '// File: ~/.gemini/mcp.json\n' + json;
				case 'cursor-windsurf-vscode':
					return '// Cursor: .cursor/mcp.json\n'
						+ '// Windsurf: .windsurf/mcp.json\n'
						+ '// VS Code (Cline): .vscode/cline_mcp_settings.json\n'
						+ json;
				case 'claude-web':
				default:
					return json;
			}
		}

		function updatePreview() {
			var client = $clientSelect.val();
			$snippetBox.text(renderSnippet(client, maskKey()));
			$verifiedBadge.toggle(client === 'claude-web');
		}

		$clientSelect.on('change', updatePreview);
		updatePreview();

		// --- Copy full config ---
		$copyConfigBtn.on('click', function() {
			var $btn = $(this);
			var $icon = $btn.find('.dashicons');
			var $text = $btn.find('.wp-llm-btn-text');
			var full = renderSnippet($clientSelect.val(), realKeyForCopy());

			function onSuccess() {
				$btn.addClass('copied');
				$icon.removeClass('dashicons-clipboard').addClass('dashicons-yes');
				$text.text(i18n.copyConfigDone || 'Copied!');
				setTimeout(function() {
					$btn.removeClass('copied');
					$icon.removeClass('dashicons-yes').addClass('dashicons-clipboard');
					$text.text(i18n.copyConfigText || 'Copy full config');
				}, 2000);
				if (!mcp.hasFullKey && i18n.noFullKeyNotice) {
					// One-time informational notice when falling back to placeholder.
					$btn.attr('title', i18n.noFullKeyNotice);
				}
			}

			function onFailure() {
				window.prompt(i18n.copyError || 'Copy this manually:', full);
			}

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(full).then(onSuccess).catch(function() {
					// execCommand fallback.
					var $temp = $('<textarea>');
					$('body').append($temp);
					$temp.val(full).select();
					var ok = false;
					try { ok = document.execCommand('copy'); } catch (err) { ok = false; }
					$temp.remove();
					if (ok) { onSuccess(); } else { onFailure(); }
				});
			} else {
				var $temp = $('<textarea>');
				$('body').append($temp);
				$temp.val(full).select();
				var ok = false;
				try { ok = document.execCommand('copy'); } catch (err) { ok = false; }
				$temp.remove();
				if (ok) { onSuccess(); } else { onFailure(); }
			}
		});

		// --- Test connection ---
		$testBtn.on('click', function() {
			$testStatus
				.removeClass('is-ok is-fail')
				.addClass('is-testing')
				.text(i18n.testTesting || 'Testing...');

			$.post(wpLlmConnector.ajaxUrl, {
				action: 'wp_llm_connector_test_connection',
				nonce: wpLlmConnector.nonce
			})
				.done(function(res) {
					if (res && res.success) {
						$testStatus
							.removeClass('is-testing is-fail')
							.addClass('is-ok')
							.text(i18n.testConnected || 'Connected');
					} else {
						$testStatus
							.removeClass('is-testing is-ok')
							.addClass('is-fail')
							.text(i18n.testFailed || 'Failed');
					}
				})
				.fail(function() {
					$testStatus
						.removeClass('is-testing is-ok')
						.addClass('is-fail')
						.text(i18n.testFailed || 'Failed');
				});
		});
	}
});
