/**
 * Lightweight (< 1.1 KB) RTL-aware scroll-snap controller for `tw-blocks/slider`.
 * Correctly handles `dir="rtl"` negative horizontal scroll coordinates in modern browsers.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		const btn = event.target.closest( '[data-twb-slider-dir]' );
		if ( ! btn ) {
			return;
		}

		const slider = btn.closest( '[data-twb-slider]' );
		const track = slider ? slider.querySelector( '[data-twb-slider-track]' ) : null;
		if ( ! track ) {
			return;
		}

		const dir = btn.getAttribute( 'data-twb-slider-dir' );
		const isRtl =
			( slider.getAttribute( 'dir' ) || document.documentElement.dir || '' ).toLowerCase() === 'rtl' ||
			window.getComputedStyle( track ).direction === 'rtl';

		const step = Math.max( 260, Math.round( track.clientWidth * 0.8 ) );
		const sign = dir === 'next' ? 1 : -1;
		const delta = isRtl ? -sign * step : sign * step;

		track.scrollBy( { left: delta, behavior: 'smooth' } );
	} );
} )();
