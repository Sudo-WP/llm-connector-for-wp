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

	// Copy API key to clipboard.
	$( document ).on( 'click', '.wp-llm-copy-key', function( e ) {
		e.preventDefault();
		var $button = $( this );
		var apiKey = $button.data( 'key' );
		var originalText = $button.text();

		// Try modern clipboard API first.
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( apiKey ).then( function() {
				// Success feedback.
				$button.text( 'Copied!' ).attr( 'aria-label', 'Copied to clipboard' );
				setTimeout( function() {
					$button.text( originalText ).attr( 'aria-label', 'Copy to clipboard' );
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
		$( 'body' ).append( $temp );
		$temp.val( text ).select();

		try {
			document.execCommand( 'copy' );
			$button.text( 'Copied!' ).attr( 'aria-label', 'Copied to clipboard' );
			setTimeout( function() {
				$button.text( originalText ).attr( 'aria-label', 'Copy to clipboard' );
			}, 2000 );
		} catch ( err ) {
			alert( 'Failed to copy to clipboard. Please select and copy the key manually.' );
		}

		$temp.remove();
	}
} );
