/**
 * Lightweight (< 1 KB) IntersectionObserver counter with Persian digits support.
 */
( function () {
	'use strict';

	const persianDigits = [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ];
	function toPersianDigits( num ) {
		return String( Math.round( num ) ).replace( /\d/g, function ( d ) {
			return persianDigits[ Number( d ) ] || d;
		} );
	}

	if ( typeof IntersectionObserver === 'undefined' ) {
		return;
	}

	const observer = new IntersectionObserver(
		function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( ! entry.isIntersecting ) {
					return;
				}
				const el = entry.target;
				observer.unobserve( el );

				const valueEl = el.querySelector( '[data-twb-counter-value]' );
				const target = parseInt( el.getAttribute( 'data-target' ) || '0', 10 );
				const duration = parseInt( el.getAttribute( 'data-duration' ) || '1200', 10 );
				if ( ! valueEl || target <= 0 ) {
					return;
				}

				const startTime = performance.now();
				function tick( now ) {
					const progress = Math.min( 1, ( now - startTime ) / duration );
					const eased = 1 - Math.pow( 1 - progress, 3 );
					valueEl.textContent = toPersianDigits( target * eased );
					if ( progress < 1 ) {
						requestAnimationFrame( tick );
					}
				}
				requestAnimationFrame( tick );
			} );
		},
		{ threshold: 0.25 }
	);

	document.querySelectorAll( '[data-twb-counter]' ).forEach( function ( item ) {
		observer.observe( item );
	} );
} )();
