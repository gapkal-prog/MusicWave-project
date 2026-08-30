/**
 * Instant catalog filtering for the MusicWave release archive.
 *
 * Progressive enhancement only: the catalog filter form remains a standard
 * GET form for visitors without JavaScript. When supported, submissions,
 * active-filter chips, and pagination links load results in place without a
 * full page refresh, keeping the URL shareable via the History API.
 */
( function ( config ) {
	'use strict';

	if (
		! config ||
		! window.fetch ||
		! window.DOMParser ||
		! window.history ||
		! history.pushState
	) {
		return;
	}

	var REGION_SELECTORS = [
		'form.mw-catalog-filters',
		'.mw-catalog-results',
		'.wp-block-query',
	];
	var supportsSwap = !! document.querySelector( '.wp-block-query' );
	var inFlight = null;

	if (
		! document.querySelector( 'form.mw-catalog-filters' ) ||
		! supportsSwap
	) {
		return;
	}

	function setLoading( loading ) {
		var form = document.querySelector( 'form.mw-catalog-filters' );
		if ( ! form ) {
			return;
		}
		var button = form.querySelector( 'button[type="submit"]' );
		if ( loading ) {
			form.setAttribute( 'data-mw-busy', 'true' );
			form.setAttribute( 'aria-busy', 'true' );
			if ( button ) {
				button.setAttribute(
					'data-mw-original-label',
					button.textContent
				);
				button.textContent = config.applying || button.textContent;
				button.disabled = true;
			}
			return;
		}
		form.removeAttribute( 'data-mw-busy' );
		form.removeAttribute( 'aria-busy' );
		if ( button ) {
			var original = button.getAttribute( 'data-mw-original-label' );
			if ( original ) {
				button.textContent = original;
			}
			button.disabled = false;
		}
	}

	/**
	 * Build the GET URL exactly like a native form submission would, minus
	 * the empty controls and the hidden post type context input.
	 */
	function buildSubmitUrl( form ) {
		var data = new window.FormData( form );
		var params = new window.URLSearchParams();
		data.forEach( function ( value, key ) {
			if ( 'post_type' === key || '' === value ) {
				return;
			}
			params.append( key, value );
		} );
		var base = String( form.action || window.location.href ).split(
			/[?#]/
		)[ 0 ];
		var query = params.toString();
		return query ? base + '?' + query : base;
	}

	/**
	 * Replace the catalog regions with their counterparts from a parsed page.
	 */
	function swapRegions( doc ) {
		var swapped = false;
		REGION_SELECTORS.forEach( function ( selector ) {
			var current = document.querySelector( selector );
			var next = doc.querySelector( selector );
			if ( ! current || ! next || ! current.parentNode ) {
				return;
			}
			current.parentNode.replaceChild( next, current );
			swapped = true;
		} );
		return swapped;
	}

	function loadCatalogUrl( url, push ) {
		if ( inFlight ) {
			inFlight.abort();
		}
		var controller = window.AbortController
			? new window.AbortController()
			: null;
		inFlight = controller;
		setLoading( true );

		return window
			.fetch( url, {
				credentials: 'same-origin',
				headers: { Accept: 'text/html' },
				signal: controller ? controller.signal : undefined,
			} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				return response.text();
			} )
			.then( function ( html ) {
				var doc = new window.DOMParser().parseFromString(
					html,
					'text/html'
				);
				if ( ! swapRegions( doc ) ) {
					window.location.assign( url );
					return;
				}
				if ( doc.title ) {
					document.title = doc.title;
				}
				if ( push ) {
					history.pushState( { mwCatalog: true }, '', url );
				}
				// Swapped markup is unenhanced: let re-entrant modules such as
				// the catalog suggest combobox rebind, mirroring the event the
				// persistent player dispatches after its own soft navigations.
				document.dispatchEvent(
					new window.CustomEvent( 'mw-page-rendered', {
						detail: { url },
					} )
				);
			} )
			.catch( function ( error ) {
				if ( error && 'AbortError' === error.name ) {
					return;
				}
				// Network or parse failure: degrade to a regular navigation.
				window.location.assign( url );
			} )
			.then( function () {
				if ( inFlight === controller ) {
					inFlight = null;
				}
				setLoading( false );
			} );
	}

	// Delegated submit listener survives region swaps replacing the form.
	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;
		if (
			! form ||
			! form.matches ||
			! form.matches( 'form.mw-catalog-filters' )
		) {
			return;
		}
		event.preventDefault();
		loadCatalogUrl( buildSubmitUrl( form ), true );
	} );

	// Active filter chips and archive pagination also load in place.
	document.addEventListener( 'click', function ( event ) {
		var link =
			event.target && event.target.closest
				? event.target.closest(
						'.mw-catalog-results a, .wp-block-query-pagination a'
				  )
				: null;
		if ( ! link ) {
			return;
		}
		var href = link.getAttribute( 'href' );
		if ( ! href || '#' === href.charAt( 0 ) ) {
			return;
		}
		event.preventDefault();
		loadCatalogUrl(
			new window.URL( href, window.location.href ).href,
			true
		);
	} );

	window.addEventListener( 'popstate', function ( event ) {
		if ( ! event.state || ! event.state.mwCatalog ) {
			return;
		}
		loadCatalogUrl( window.location.href, false );
	} );

	history.replaceState( { mwCatalog: true }, '' );
} )( window.musicWaveCatalogFilters || null );
