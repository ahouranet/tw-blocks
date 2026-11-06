/**
 * Minimal Vanilla JS (< 1.2 KB) for `tw-blocks/custom-loop` "Load More" button.
 * Enqueued ONLY when `paginationMode === 'load-more'`.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		const btn = event.target.closest( '.twb-loop-load-more' );
		if ( ! btn || btn.disabled ) {
			return;
		}

		const wrapper = btn.closest( '.twb-custom-loop' );
		const container = wrapper ? wrapper.querySelector( '[data-twb-loop-container]' ) : null;
		if ( ! container ) {
			return;
		}

		const endpoint = btn.getAttribute( 'data-endpoint' ) || '';
		const nextPage = parseInt( btn.getAttribute( 'data-page' ) || '1', 10 ) + 1;
		const perPage = parseInt( btn.getAttribute( 'data-per-page' ) || '6', 10 );
		const postType = btn.getAttribute( 'data-post-type' ) || 'post';
		const orderByMeta = btn.getAttribute( 'data-order-by-meta' ) || '';

		btn.disabled = true;
		const originalText = btn.textContent;

		const url = new URL( endpoint, window.location.origin );
		url.searchParams.set( 'page', String( nextPage ) );
		url.searchParams.set( 'per_page', String( perPage ) );
		url.searchParams.set( 'post_type', postType );
		if ( orderByMeta ) {
			url.searchParams.set( 'order_by_meta', orderByMeta );
		}

		fetch( url.toString(), { credentials: 'same-origin' } )
			.then( function ( res ) {
				return res.json();
			} )
			.then( function ( data ) {
				if ( data && Array.isArray( data.items ) ) {
					data.items.forEach( function ( item ) {
						const card = document.createElement( 'article' );
						card.className =
							'rounded-2xl border border-slate-200 bg-white p-5 shadow-xs hover:shadow-md transition flex flex-col justify-between gap-3 text-start';

						const body = document.createElement( 'div' );
						body.className = 'space-y-2';

						const time = document.createElement( 'time' );
						time.className = 'text-xs font-medium text-teal-700 block';
						time.textContent = item.date || '';

						const h3 = document.createElement( 'h3' );
						h3.className = 'text-base font-bold text-slate-900 leading-snug';
						const a = document.createElement( 'a' );
						a.href = item.link || '#';
						a.className = 'hover:text-teal-700 transition';
						a.textContent = item.title || '';
						h3.appendChild( a );

						const p = document.createElement( 'p' );
						p.className = 'text-sm text-slate-600 leading-relaxed';
						p.textContent = item.excerpt || '';

						body.appendChild( time );
						body.appendChild( h3 );
						body.appendChild( p );
						card.appendChild( body );
						container.appendChild( card );
					} );
				}

				btn.setAttribute( 'data-page', String( nextPage ) );
				if ( ! data || ! data.has_more ) {
					btn.remove();
				} else {
					btn.disabled = false;
					btn.textContent = originalText;
				}
			} )
			.catch( function () {
				btn.disabled = false;
				btn.textContent = originalText;
			} );
	} );
} )();
