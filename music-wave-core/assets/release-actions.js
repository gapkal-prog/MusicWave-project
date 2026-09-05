/**
 * Release action bar helpers: system share sheet + clipboard fallback + shuffle.
 *
 * Share: uses navigator.share when available and canShare({url}) passes; otherwise
 * copies the canonical link (navigator.clipboard → legacy execCommand fallback).
 * Shuffle: randomizes the resolved playback queue before handing it to the global player.
 *
 * Accessible, RTL-safe, progressive enhancement: buttons are server-rendered.
 */

( function () {
	'use strict';

	var settings = window.musicWaveReleaseActions || {};
	var copiedLabel = settings.copied || 'پیوند کپی شد';
	var copyFailedLabel = settings.copyFailed || 'کپی انجام نشد.';

	function announce( button, message ) {
		var status = button.parentElement
			? button.parentElement.querySelector( '[data-mw-share-status]' )
			: null;
		if ( ! status ) {
			return;
		}
		status.textContent = message || '';
		status.hidden = ! message;
		if ( message ) {
			window.setTimeout( function () {
				if ( status.textContent === message ) {
					status.textContent = '';
					status.hidden = true;
				}
			}, 3200 );
		}
	}

	function copyTextFallback( text ) {
		try {
			var helper = document.createElement( 'textarea' );
			helper.value = text;
			helper.setAttribute( 'readonly', '' );
			helper.style.position = 'fixed';
			helper.style.opacity = '0';
			helper.style.pointerEvents = 'none';
			document.body.appendChild( helper );
			helper.select();
			helper.setSelectionRange( 0, helper.value.length );
			var ok = document.execCommand( 'copy' );
			helper.remove();
			return ok;
		} catch ( e ) {
			return false;
		}
	}

	function copyText( text ) {
		if (
			navigator.clipboard &&
			typeof navigator.clipboard.writeText === 'function'
		) {
			return navigator.clipboard.writeText( text ).then(
				function () {
					return true;
				},
				function () {
					return copyTextFallback( text );
				}
			);
		}
		return Promise.resolve( copyTextFallback( text ) );
	}

	function handleShareClick( event ) {
		var button = event.target.closest( '[data-mw-share-button]' );
		if ( ! button ) {
			return;
		}
		event.preventDefault();
		var url =
			button.getAttribute( 'data-mw-share-url' ) || window.location.href;
		var title =
			button.getAttribute( 'data-mw-share-title' ) || document.title;
		var text = button.getAttribute( 'data-mw-share-text' ) || title;

		if (
			navigator.share &&
			typeof navigator.canShare === 'function' &&
			! navigator.canShare( { url } )
		) {
			// canShare explicitly says no — skip to clipboard rather than throwing.
			copyText( url ).then( function ( ok ) {
				announce( button, ok ? copiedLabel : copyFailedLabel );
			} );
			return;
		}

		if ( navigator.share ) {
			navigator.share( { title, text, url } ).then(
				function () {},
				function ( error ) {
					if ( error && error.name === 'AbortError' ) {
						return;
					}
					copyText( url ).then( function ( ok ) {
						announce( button, ok ? copiedLabel : copyFailedLabel );
					} );
				}
			);
			return;
		}

		copyText( url ).then( function ( ok ) {
			announce( button, ok ? copiedLabel : copyFailedLabel );
		} );
	}

	function shuffleArray( items ) {
		var copy = items.slice();
		for ( var i = copy.length - 1; i > 0; i-- ) {
			var j = Math.floor( Math.random() * ( i + 1 ) );
			var tmp = copy[ i ];
			copy[ i ] = copy[ j ];
			copy[ j ] = tmp;
		}
		return copy;
	}

	function maybeShuffleAndPlay( rawData ) {
		if (
			! rawData ||
			! Array.isArray( rawData.tracks ) ||
			! rawData.tracks.length
		) {
			return rawData;
		}
		rawData.tracks = shuffleArray( rawData.tracks );
		return rawData;
	}

	function handleShuffleClick( event ) {
		var button = event.target.closest( '[data-mw-shuffle-button]' );
		if ( ! button ) {
			return;
		}
		event.preventDefault();
		var releaseId =
			parseInt( button.getAttribute( 'data-mw-release-id' ), 10 ) || 0;
		if ( releaseId < 1 ) {
			return;
		}

		var controller = window._mwPreviewController;
		var busy = button.getAttribute( 'aria-busy' ) === 'true';
		if ( busy ) {
			return;
		}
		button.setAttribute( 'aria-busy', 'true' );
		button.classList.add( 'is-loading' );

		// Prefer the global preview queue controller: resolve via REST, shuffle, then set queue.
		// We hook into the fetch path by monkey-patching apiFetch for one call, then restore.
		if ( ! window.wp || ! window.wp.apiFetch ) {
			button.removeAttribute( 'aria-busy' );
			button.classList.remove( 'is-loading' );
			return;
		}

		var originalFetch = window.wp.apiFetch;
		var captured = null;
		var patched = function ( options ) {
			return originalFetch( options ).then( function ( data ) {
				captured = maybeShuffleAndPlay( data );
				return captured;
			} );
		};

		// Temporarily patch wp.apiFetch for the single queue fetch triggered below
		window.wp.apiFetch = patched;

		function restorePatch() {
			window.wp.apiFetch = originalFetch;
		}

		// Trigger the normal queue path via a synthetic card-play button — the player
		// controller already wires .mw-card-play[data-mw-release-id] to playback-queue.
		var synthetic = document.createElement( 'button' );
		synthetic.setAttribute( 'data-mw-release-id', String( releaseId ) );
		synthetic.className = 'mw-card-play';

		// If preview controller exists, call its internal release queue path directly
		// by dispatching; otherwise fetch manually and use the controller API when ready.
		var fallbackDone = false;
		window.setTimeout( function () {
			if ( ! fallbackDone ) {
				restorePatch();
			}
		}, 8000 );

		if ( controller && typeof controller.playRelease === 'function' ) {
			// Use controller path — it will invoke our patched apiFetch
			try {
				controller.playRelease( synthetic, releaseId );
			} catch ( e ) {
				restorePatch();
			}
			// Give the async fetch time to complete, then restore
			window.setTimeout( function () {
				fallbackDone = true;
				restorePatch();
				button.removeAttribute( 'aria-busy' );
				button.classList.remove( 'is-loading' );
				if ( captured && controller && controller.sync ) {
					try {
						controller.sync();
					} catch ( e2 ) {}
				}
			}, 1800 );
			return;
		}

		// No controller yet — fetch and try to bootstrap the player
		window.wp
			.apiFetch( {
				path:
					'/music-wave/v1/releases/' + releaseId + '/playback-queue',
				method: 'GET',
				headers:
					window.musicWavePreviewPlayer &&
					window.musicWavePreviewPlayer.restNonce
						? {
								'X-WP-Nonce':
									window.musicWavePreviewPlayer.restNonce,
						  }
						: {},
			} )
			.then( function ( data ) {
				var shuffled = maybeShuffleAndPlay( data );
				// If controller loaded by now, use it
				var c2 = window._mwPreviewController;
				if ( c2 && typeof c2.playRelease === 'function' ) {
					// Re-inject shuffled data via a second patched fetch
					var p2 = window.wp.apiFetch;
					window.wp.apiFetch = function () {
						return Promise.resolve( shuffled );
					};
					try {
						c2.playRelease( synthetic, releaseId );
					} catch ( e ) {}
					window.setTimeout( function () {
						window.wp.apiFetch = p2;
					}, 1500 );
				}
			} )
			.catch( function () {} )
			.finally( function () {
				fallbackDone = true;
				restorePatch();
				button.removeAttribute( 'aria-busy' );
				button.classList.remove( 'is-loading' );
			} );
	}

	document.addEventListener( 'click', handleShareClick );
	document.addEventListener( 'click', handleShuffleClick );
} )();
