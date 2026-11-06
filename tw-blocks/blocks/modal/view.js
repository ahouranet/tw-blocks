/**
 * Lightweight (< 0.7 KB) controller for native HTML5 `<dialog>` modal (`tw-blocks/modal`).
 * Supports `.showModal()`, close button, ESC key (native), and backdrop click dismissal.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		const openBtn = event.target.closest( '[data-twb-modal-open]' );
		if ( openBtn ) {
			const dialogId = openBtn.getAttribute( 'data-twb-modal-open' );
			const dialog = dialogId ? document.getElementById( dialogId ) : null;
			if ( dialog && typeof dialog.showModal === 'function' ) {
				dialog.showModal();
			}
			return;
		}

		const closeBtn = event.target.closest( '[data-twb-modal-close]' );
		if ( closeBtn ) {
			const parentDialog = closeBtn.closest( 'dialog' );
			if ( parentDialog && typeof parentDialog.close === 'function' ) {
				parentDialog.close();
			}
			return;
		}

		if ( event.target.tagName === 'DIALOG' && event.target.open ) {
			const rect = event.target.getBoundingClientRect();
			const clickedInDialog =
				rect.top <= event.clientY &&
				event.clientY <= rect.top + rect.height &&
				rect.left <= event.clientX &&
				event.clientX <= rect.left + rect.width;
			if ( ! clickedInDialog ) {
				event.target.close();
			}
		}
	} );
} )();
