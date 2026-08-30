/**
 * Dashboard tab controller: reveals account sections below the quick links.
 *
 * Runs once per rendered dashboard; the persistent player navigation swaps in
 * fresh markup through `mw-page-rendered`, so enhancement is re-entrant and
 * guarded per instance.
 *
 * @package
 */
( function () {
	'use strict';

	function tabButton( key ) {
		return document.querySelector(
			'[data-mw-dashboard-tab="' + key + '"]'
		);
	}

	function panelFor( tab ) {
		var key = tab.getAttribute( 'data-mw-dashboard-tab' );

		return key
			? document.querySelector(
					'[data-mw-dashboard-panel="' + key + '"]'
			  )
			: null;
	}

	function setState( tab, open ) {
		var panel = panelFor( tab );
		if ( ! panel ) {
			return;
		}

		tab.classList.toggle( 'is-active', open );
		tab.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		panel.hidden = ! open;
		panel.classList.toggle( 'is-open', open );
	}

	function closeAll() {
		document
			.querySelectorAll( '[data-mw-dashboard-tab]' )
			.forEach( function ( tab ) {
				setState( tab, false );
			} );
	}

	function updateHash( openKey ) {
		if ( ! window.history || ! window.history.replaceState ) {
			return;
		}

		var url = window.location.href.split( '#' )[ 0 ];
		window.history.replaceState(
			null,
			'',
			openKey ? url + '#mw-' + openKey : url
		);
	}

	function openOnly( tab ) {
		var key = tab.getAttribute( 'data-mw-dashboard-tab' );
		var wasOpen = tab.getAttribute( 'aria-expanded' ) === 'true';

		closeAll();
		if ( ! wasOpen ) {
			setState( tab, true );
			updateHash( key );
		} else {
			updateHash( '' );
		}
	}

	function isStacked() {
		var root = document.querySelector( '[data-mw-dashboard]' );

		return !! root && root.classList.contains( 'is-style-stacked' );
	}

	// One delegated binding for every dashboard on the page, including ones
	// swapped in later by the persistent player navigation.
	document.addEventListener( 'click', function ( event ) {
		var tab = event.target.closest( '[data-mw-dashboard-tab]' );
		if ( ! tab || 'BUTTON' !== tab.tagName ) {
			return;
		}
		event.preventDefault();
		if ( isStacked() ) {
			var panel = panelFor( tab );
			if ( panel && panel.scrollIntoView ) {
				panel.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}

			return;
		}
		openOnly( tab );
	} );

	// Progressive enhancement: the server renders every panel expanded so the
	// dashboard stays readable without JavaScript. Once this controller runs,
	// collapse everything and open only the deep-linked panel (if any).
	// The Stacked block style opts out entirely: every panel stays visible as
	// a section and the tab bar doubles as an in-page anchor list.
	function enhance() {
		var dashboard = document.querySelector( '[data-mw-dashboard]' );
		if ( ! dashboard || dashboard.getAttribute( 'data-mw-enhanced' ) ) {
			return;
		}
		dashboard.setAttribute( 'data-mw-enhanced', '1' );

		if ( dashboard.classList.contains( 'is-style-stacked' ) ) {
			document
				.querySelectorAll( '[data-mw-dashboard-panel]' )
				.forEach( function ( panel ) {
					panel.hidden = false;
					panel.classList.add( 'is-open' );
				} );

			return;
		}

		closeAll();
		var hash = ( window.location.hash || '' ).replace( '#mw-', '' );
		// The hash is URL-controlled input: only accept plain tab keys so it can
		// never be interpreted as selector syntax inside tabButton().
		if ( hash && /^[A-Za-z0-9_-]+$/.test( hash ) ) {
			var linked = tabButton( hash );
			if ( linked ) {
				setState( linked, true );
			}
		}
	}

	document.addEventListener( 'DOMContentLoaded', enhance );
	document.addEventListener( 'mw-page-rendered', enhance );
} )();
