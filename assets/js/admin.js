jQuery( document ).ready( function( $ ) {
	'use strict';

	// Confirm before disabling connector if it's currently enabled.
	var $enabledCheckbox = $( 'input[name="wp_llm_connector_settings[enabled]"]' );
	$enabledCheckbox.data( 'initial-state', $enabledCheckbox.is( ':checked' ) );

	$( 'form' ).on( 'submit', function( e ) {
		var wasEnabled = $enabledCheckbox.data( 'initial-state' );

		if ( wasEnabled && ! $enabledCheckbox.is( ':checked' ) ) {
			if ( ! confirm( 'Are you sure you want to disable the LLM Connector? All API access will be blocked.' ) ) {
				e.preventDefault();
				$enabledCheckbox.prop( 'checked', true );
				return false;
			}
		}

		$enabledCheckbox.data( 'initial-state', $enabledCheckbox.is( ':checked' ) );
	} );

	// Warning when disabling read-only mode.
	$( 'input[name="wp_llm_connector_settings[read_only_mode]"]' ).on( 'change', function() {
		if ( ! $( this ).is( ':checked' ) ) {
			if ( ! confirm( 'WARNING: Disabling read-only mode will allow LLMs to make changes to your site. This is not recommended unless you fully trust the LLM provider and have backups in place.\n\nContinue anyway?' ) ) {
				$( this ).prop( 'checked', true );
			}
		}
	} );

	// Reveal/Hide API key functionality.
	$( document ).on( 'click', '.wp-llm-reveal-key', function( e ) {
		e.preventDefault();
		var $button = $( this );
		var $keyElement = $( '#wp-llm-generated-key' );
		var apiKey = $button.data( 'key' );
		
		// Check if wpLlmConnector is defined.
		if ( typeof wpLlmConnector === 'undefined' ) {
			console.error( 'wpLlmConnector is not defined' );
			return;
		}

		if ( $keyElement.hasClass( 'wp-llm-api-key-hidden' ) ) {
			// Reveal the key.
			$keyElement.removeClass( 'wp-llm-api-key-hidden' ).addClass( 'wp-llm-api-key-revealed' ).text( apiKey ).attr( 'title', wpLlmConnector.i18n.copyLabel );
			$button.text( wpLlmConnector.i18n.hideText ).attr( 'aria-label', wpLlmConnector.i18n.hideText );
		} else {
			// Hide the key.
			$keyElement.removeClass( 'wp-llm-api-key-revealed' ).addClass( 'wp-llm-api-key-hidden' ).text( '••••••••••••••••••••••••••••••••' ).attr( 'title', 'Click Reveal to view the key' );
			$button.text( wpLlmConnector.i18n.revealText ).attr( 'aria-label', wpLlmConnector.i18n.revealText );
		}
	} );

	// Copy API key to clipboard.
	$( document ).on( 'click', '.wp-llm-copy-key', function( e ) {
		e.preventDefault();
		var $button = $( this );
		var apiKey = $button.data( 'key' );
		var originalText = $button.text();

		// Check if wpLlmConnector is defined.
		if ( typeof wpLlmConnector === 'undefined' ) {
			console.error( 'wpLlmConnector is not defined' );
			alert( 'Failed to copy to clipboard. Please select and copy the key manually.' );
			return;
		}

		// Try modern clipboard API first.
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( apiKey ).then( function() {
				// Success feedback.
				$button.text( wpLlmConnector.i18n.copiedText ).attr( 'aria-label', wpLlmConnector.i18n.copiedLabel );
				setTimeout( function() {
					$button.text( originalText ).attr( 'aria-label', wpLlmConnector.i18n.copyLabel );
				}, 2000 );
			} ).catch( function() {
				// Fallback if clipboard API fails.
				fallbackCopyToClipboard( apiKey, $button, originalText );
			} );
		} else {
			// Fallback for older browsers.
			fallbackCopyToClipboard( apiKey, $button, originalText );
		}
	} );

	// Fallback copy method for older browsers.
	function fallbackCopyToClipboard( text, $button, originalText ) {
		var $temp = $( '<textarea>' );
		var success = false;
		
		$( 'body' ).append( $temp );
		$temp.val( text ).select();

		try {
			success = document.execCommand( 'copy' );
		} catch ( err ) {
			success = false;
		}

		$temp.remove();

		if ( success ) {
			// Check if wpLlmConnector is defined before using it.
			if ( typeof wpLlmConnector !== 'undefined' ) {
				$button.text( wpLlmConnector.i18n.copiedText ).attr( 'aria-label', wpLlmConnector.i18n.copiedLabel );
				setTimeout( function() {
					$button.text( originalText ).attr( 'aria-label', wpLlmConnector.i18n.copyLabel );
				}, 2000 );
			} else {
				$button.text( 'Copied!' );
				setTimeout( function() {
					$button.text( originalText );
				}, 2000 );
			}
		} else {
			if ( typeof wpLlmConnector !== 'undefined' ) {
				alert( wpLlmConnector.i18n.copyError );
			} else {
				alert( 'Failed to copy to clipboard. Please select and copy the key manually.' );
			}
		}
	}
} );
