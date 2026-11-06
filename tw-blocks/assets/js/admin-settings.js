/**
 * TW Blocks Admin Settings Script
 * Handles live "Test Environment" (تست محیط) via REST API and local .woff2 font uploader.
 * Uses @wordpress/i18n with English source strings mapped via tw-blocks-fa_IR-tw-blocks-admin.json.
 */
( function ( wp ) {
	'use strict';

	const { __ } = wp.i18n;

	document.addEventListener( 'DOMContentLoaded', function () {
		const testBtn = document.getElementById( 'twb-run-env-test' );
		const diagContainer = document.getElementById( 'twb-driver-diagnostics' );
		const activeLabel = document.getElementById( 'twb-active-driver-label' );
		const uploadBtn = document.getElementById( 'twb-upload-woff2-btn' );
		const woff2Input = document.getElementById( 'twb-custom-woff2' );

		if ( testBtn && window.twbAdminConfig ) {
			testBtn.addEventListener( 'click', function () {
				testBtn.disabled = true;
				const originalText = testBtn.textContent;
				testBtn.textContent = __( 'Testing host environment...', 'tw-blocks' );

				fetch( window.twbAdminConfig.restUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': window.twbAdminConfig.restNonce,
					},
				} )
					.then( function ( res ) {
						return res.json();
					} )
					.then( function ( data ) {
						if ( activeLabel && data.active_driver_label ) {
							activeLabel.textContent = data.active_driver_label;
						}
						if ( diagContainer && data.drivers ) {
							diagContainer.innerHTML = '';
							Object.keys( data.drivers ).forEach( function ( key ) {
								const d = data.drivers[ key ];
								const box = document.createElement( 'div' );
								box.className = 'twb-driver-box ' + ( d.available ? 'is-available' : 'is-unavailable' );

								const h3 = document.createElement( 'h3' );
								h3.textContent = d.label + ' ';
								const statusSpan = document.createElement( 'span' );
								statusSpan.className = 'twb-status-text';
								statusSpan.textContent = d.available ? __( 'Ready', 'tw-blocks' ) : __( 'Unavailable', 'tw-blocks' );
								h3.appendChild( statusSpan );
								box.appendChild( h3 );

								const ul = document.createElement( 'ul' );
								( d.checks || [] ).forEach( function ( chk ) {
									const li = document.createElement( 'li' );
									const strong = document.createElement( 'strong' );
									strong.textContent = ( chk.passed ? '✓ ' : '✗ ' ) + chk.name + ': ';
									const span = document.createElement( 'span' );
									span.textContent = chk.message;
									li.appendChild( strong );
									li.appendChild( span );
									ul.appendChild( li );
								} );

								box.appendChild( ul );
								diagContainer.appendChild( box );
							} );
						}
					} )
					.finally( function () {
						testBtn.disabled = false;
						testBtn.textContent = originalText;
					} );
			} );
		}

		if ( uploadBtn && woff2Input && wp.media ) {
			let mediaFrame = null;
			uploadBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				if ( mediaFrame ) {
					mediaFrame.open();
					return;
				}
				mediaFrame = wp.media( {
					title: __( 'Select or Upload WOFF2 Font File', 'tw-blocks' ),
					button: {
						text: __( 'Use This Font', 'tw-blocks' ),
					},
					library: {
						type: 'font/woff2',
					},
					multiple: false,
				} );

				mediaFrame.on( 'select', function () {
					const attachment = mediaFrame.state().get( 'selection' ).first().toJSON();
					if ( attachment && attachment.url ) {
						woff2Input.value = attachment.url;
					}
				} );

				mediaFrame.open();
			} );
		}
	} );
} )( window.wp || { i18n: { __: function ( s ) { return s; } } } );
