/**
 * Continue-listening consent handler.
 *
 * Progressive enhancement for the server-rendered consent panel: one click
 * opts the signed-in viewer into listening history via REST, then swaps the
 * panel to the "enabled" state without a reload. Without JavaScript the
 * panel simply stays as-is, matching the block's zero-JS contract.
 *
 * @package
 */
( function () {
	'use strict';

	var settings = window.musicWaveListening || {};
	var labels = settings.labels || {};

	function restHeaders() {
		return settings.restNonce ? { 'X-WP-Nonce': settings.restNonce } : {};
	}

	function setStatus( section, message ) {
		var status = section
			? section.querySelector( '[data-mw-listening-status]' )
			: null;
		if ( ! status ) {
			return;
		}
		status.textContent = message || '';
		status.hidden = ! message;
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-mw-listening-consent]' );
		if ( ! button || button.disabled ) {
			return;
		}
		event.preventDefault();

		if ( ! window.wp || ! window.wp.apiFetch ) {
			return;
		}

		var section = button.closest( '.mw-continue-listening' );
		button.disabled = true;
		button.setAttribute( 'aria-busy', 'true' );
		setStatus( section, labels.saving || '' );

		window.wp
			.apiFetch( {
				path: '/music-wave/v1/listening/consent',
				method: 'POST',
				data: { consent: true },
				headers: restHeaders(),
			} )
			.then( function ( response ) {
				if ( ! response || response.consent !== true ) {
					throw new Error( 'consent not granted' );
				}
				// The history rail is server-rendered; swap the panel to the
				// enabled state in place instead of reloading the page.
				button.hidden = true;
				button.removeAttribute( 'aria-busy' );
				setStatus(
					section,
					labels.enabled || 'سابقهٔ گوش‌دادن روشن است.'
				);
			} )
			.catch( function () {
				button.disabled = false;
				button.removeAttribute( 'aria-busy' );
				setStatus(
					section,
					labels.error ||
						'سابقهٔ گوش‌دادن فعال نشد. دوباره امتحان کنید.'
				);
			} );
	} );
} )();
