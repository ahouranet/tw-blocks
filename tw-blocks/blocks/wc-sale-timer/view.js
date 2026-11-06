/**
 * Lightweight (< 0.9 KB) countdown timer for `tw-blocks/wc-sale-timer` with Persian numerals.
 */
( function () {
	'use strict';

	const persianDigits = [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ];
	function toFa( str ) {
		return String( str ).replace( /\d/g, function ( d ) {
			return persianDigits[ Number( d ) ] || d;
		} );
	}
	function pad2( n ) {
		return n < 10 ? '0' + n : String( n );
	}

	const timers = document.querySelectorAll( '[data-twb-sale-end]' );
	if ( ! timers.length ) {
		return;
	}

	setInterval( function () {
		const nowSec = Math.floor( Date.now() / 1000 );
		timers.forEach( function ( el ) {
			const endSec = parseInt( el.getAttribute( 'data-twb-sale-end' ) || '0', 10 );
			const out = el.querySelector( '[data-twb-timer-output]' );
			if ( ! out || endSec <= 0 ) {
				return;
			}
			const diff = Math.max( 0, endSec - nowSec );
			const d = Math.floor( diff / 86400 );
			const h = Math.floor( ( diff % 86400 ) / 3600 );
			const m = Math.floor( ( diff % 3600 ) / 60 );
			const s = diff % 60;
			out.textContent = toFa( pad2( d ) + ':' + pad2( h ) + ':' + pad2( m ) + ':' + pad2( s ) );
		} );
	}, 1000 );
} )();
