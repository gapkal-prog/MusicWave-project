/**
 * Stream rail: collapse/expand toggle with a remembered preference.
 *
 * The rail (parts/header-stream.html) is fully usable without this script —
 * it simply stays expanded. The script adds a toggle button to the brand
 * row, stores the choice in localStorage, mirrors it on <html> as
 * `data-mw-rail` (rail.css reads that attribute), keeps the button state
 * and the tooltip labels in sync, and marks the current destination for
 * links that core cannot resolve itself (custom URLs such as /?s=).
 *
 * Runs once per rail node: the persistent-player router re-dispatches
 * `mw-page-rendered` after a soft navigation, and a carried-over rail must
 * not receive a second toggle. Labels come from the localized
 * `musicwaveRail` object; the script has no wp.i18n dependency.
 */
( function () {
	'use strict';

	var storageKey = 'musicwave-rail';
	var attribute = 'data-mw-rail';
	var readyAttribute = 'data-mw-rail-ready';
	var states = [ 'expanded', 'collapsed' ];

	function labels() {
		return window.musicwaveRail && window.musicwaveRail.labels
			? window.musicwaveRail.labels
			: {};
	}

	function savedState() {
		try {
			var saved = window.localStorage.getItem( storageKey );
			return states.indexOf( saved ) !== -1 ? saved : 'expanded';
		} catch ( error ) {
			return 'expanded';
		}
	}

	function storeState( state ) {
		try {
			window.localStorage.setItem( storageKey, state );
		} catch ( error ) {}
	}

	function applyState( state ) {
		document.documentElement.setAttribute( attribute, state );
		// Collapsed items show icons only: mirror the hidden label into a
		// native tooltip; expanded items would only repeat their text.
		Array.prototype.forEach.call(
			document.querySelectorAll( '.mw-rail [data-mw-label]' ),
			function ( link ) {
				if ( 'collapsed' === state ) {
					link.setAttribute(
						'title',
						link.getAttribute( 'data-mw-label' )
					);
				} else {
					link.removeAttribute( 'title' );
				}
			}
		);
	}

	function currentState() {
		var state = document.documentElement.getAttribute( attribute );
		return states.indexOf( state ) !== -1 ? state : savedState();
	}

	function icon() {
		var svg = document.createElementNS(
			'http://www.w3.org/2000/svg',
			'svg'
		);
		svg.setAttribute( 'viewBox', '0 0 24 24' );
		svg.setAttribute( 'aria-hidden', 'true' );
		svg.setAttribute( 'focusable', 'false' );
		var path = document.createElementNS(
			'http://www.w3.org/2000/svg',
			'path'
		);
		path.setAttribute( 'fill', 'none' );
		path.setAttribute( 'stroke', 'currentColor' );
		path.setAttribute( 'stroke-width', '1.8' );
		path.setAttribute( 'stroke-linecap', 'round' );
		path.setAttribute( 'stroke-linejoin', 'round' );
		path.setAttribute( 'd', 'M4 6h16M4 12h10M4 18h16' );
		svg.appendChild( path );
		return svg;
	}

	function updateButton( button, state ) {
		var text =
			'collapsed' === state
				? labels().expand || 'Expand menu'
				: labels().collapse || 'Collapse menu';
		button.setAttribute(
			'aria-expanded',
			'collapsed' === state ? 'false' : 'true'
		);
		button.setAttribute( 'aria-label', text );
		button.setAttribute( 'title', text );
	}

	function allButtons() {
		return Array.prototype.slice.call(
			document.querySelectorAll( '.mw-rail__toggle' )
		);
	}

	function syncButtons( state ) {
		allButtons().forEach( function ( button ) {
			updateButton( button, state );
		} );
	}

	function toggle() {
		var state = 'collapsed' === currentState() ? 'expanded' : 'collapsed';
		storeState( state );
		applyState( state );
		syncButtons( state );
	}

	// Core marks `current-menu-item` only for links that point at a post or
	// an archive it can resolve. Custom destinations (search, a static path)
	// are matched against the current location here so the rail always
	// highlights where the visitor is.
	function markCurrent( rail ) {
		var here = window.location.pathname.replace( /\/+$/, '' ) || '/';
		var links = rail.querySelectorAll(
			'.wp-block-navigation-item__content'
		);
		Array.prototype.forEach.call( links, function ( link ) {
			var item = link.closest( '.wp-block-navigation-item' );
			if ( ! item ) {
				return;
			}
			// Marks from a previous soft navigation are re-evaluated.
			if ( item.getAttribute( 'data-mw-current' ) ) {
				item.removeAttribute( 'data-mw-current' );
				item.classList.remove( 'current-menu-item' );
				link.removeAttribute( 'aria-current' );
			}
			if ( item.classList.contains( 'current-menu-item' ) ) {
				return;
			}
			var url;
			try {
				url = new URL(
					link.getAttribute( 'href' ),
					window.location.href
				);
			} catch ( error ) {
				return;
			}
			if ( url.origin !== window.location.origin ) {
				return;
			}
			var target = url.pathname.replace( /\/+$/, '' ) || '/';
			var matches =
				( url.searchParams.has( 's' ) &&
					new URLSearchParams( window.location.search ).has(
						's'
					) ) ||
				( ! url.searchParams.has( 's' ) &&
					'/' !== target &&
					( here === target || 0 === here.indexOf( target + '/' ) ) );
			if ( matches ) {
				item.setAttribute( 'data-mw-current', '1' );
				item.classList.add( 'current-menu-item' );
				link.setAttribute( 'aria-current', 'page' );
			}
		} );
	}

	function enhance( rail ) {
		if ( rail.getAttribute( readyAttribute ) ) {
			markCurrent( rail );
			return;
		}
		rail.setAttribute( readyAttribute, '1' );

		Array.prototype.forEach.call(
			rail.querySelectorAll( '.wp-block-navigation-item__content' ),
			function ( link ) {
				var label = ( link.textContent || '' ).trim();
				if ( label ) {
					link.setAttribute( 'data-mw-label', label );
				}
				// core/home-link renders bare text; give it the same label
				// span as custom links so the collapsed state can hide it.
				if (
					label &&
					! link.querySelector( '.wp-block-navigation-item__label' )
				) {
					var span = document.createElement( 'span' );
					span.className = 'wp-block-navigation-item__label';
					span.textContent = label;
					link.textContent = '';
					link.appendChild( span );
				}
			}
		);
		markCurrent( rail );

		var brand = rail.querySelector( '.mw-rail__brand' ) || rail;
		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'mw-rail__toggle';
		button.setAttribute( 'aria-controls', rail.id || 'mw-rail' );
		button.appendChild( icon() );
		updateButton( button, currentState() );
		button.addEventListener( 'click', toggle );
		brand.appendChild( button );
	}

	function initializeAll() {
		var rails = document.querySelectorAll(
			'.mw-site-header--stream .mw-rail'
		);
		if ( ! rails.length ) {
			return;
		}
		Array.prototype.forEach.call( rails, enhance );
		applyState( savedState() );
		syncButtons( currentState() );
	}

	// Another tab changed the preference: mirror it without a reload.
	window.addEventListener( 'storage', function ( event ) {
		if ( event.key === storageKey || null === event.key ) {
			var state = savedState();
			applyState( state );
			syncButtons( state );
		}
	} );

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initializeAll );
	} else {
		initializeAll();
	}
	document.addEventListener( 'mw-page-rendered', initializeAll );
} )();
