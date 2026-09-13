/**
 * Stream rail: collapse/expand, mobile drawer, current-item marking, ⌘/Ctrl+K search.
 */
( function () {
	'use strict';

	var storageKey = 'musicwave-rail';
	var attribute = 'data-mw-rail';
	var readyAttribute = 'data-mw-rail-ready';
	var states = [ 'expanded', 'collapsed' ];
	var html = document.documentElement;

	function labels() {
		return ( window.musicwaveRail && window.musicwaveRail.labels ) || {};
	}
	function savedState() {
		try {
			var s = window.localStorage.getItem( storageKey );
			return states.indexOf( s ) !== -1 ? s : 'expanded';
		} catch ( e ) { return 'expanded'; }
	}
	function storeState( s ) {
		try { window.localStorage.setItem( storageKey, s ); } catch ( e ) {}
	}
	function currentState() {
		var s = html.getAttribute( attribute );
		return states.indexOf( s ) !== -1 ? s : savedState();
	}
	function isNarrow() {
		return window.matchMedia( '(max-width: 63.99rem)' ).matches;
	}
	function isDrawerMobile() {
		return 'drawer' === html.getAttribute( 'data-mw-mobile-chrome' ) && isNarrow();
	}
	function drawerOpen() {
		return '1' === html.getAttribute( 'data-mw-rail-open' );
	}
	function allButtons() {
		return Array.prototype.slice.call( document.querySelectorAll( '.mw-rail__toggle' ) );
	}

	function applyState( state ) {
		html.setAttribute( attribute, state );
		Array.prototype.forEach.call( document.querySelectorAll( '.mw-rail [data-mw-label]' ), function ( link ) {
			if ( 'collapsed' === state ) {
				link.setAttribute( 'title', link.getAttribute( 'data-mw-label' ) );
			} else {
				link.removeAttribute( 'title' );
			}
		} );
	}

	function setButton( button, expanded ) {
		var text = expanded ? ( labels().collapse || 'Collapse menu' ) : ( labels().expand || 'Expand menu' );
		button.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		button.setAttribute( 'aria-label', text );
		button.setAttribute( 'title', text );
	}

	function setDrawerOpen( open ) {
		html.setAttribute( 'data-mw-rail-open', open ? '1' : '0' );
		if ( document.body ) {
			document.body.style.overflow = open && isDrawerMobile() ? 'hidden' : '';
		}
		allButtons().forEach( function ( b ) { setButton( b, open ); } );
		if ( open ) {
			var first = document.querySelector( '.mw-rail .wp-block-navigation-item__content' );
			if ( first ) { first.focus( { preventScroll: true } ); }
		}
	}

	function syncButtons( state ) {
		if ( isDrawerMobile() ) {
			setDrawerOpen( drawerOpen() );
			return;
		}
		allButtons().forEach( function ( b ) { setButton( b, 'collapsed' !== state ); } );
	}

	function onToggleClick( event ) {
		event.preventDefault();
		if ( isDrawerMobile() ) {
			setDrawerOpen( ! drawerOpen() );
			return;
		}
		var next = 'collapsed' === currentState() ? 'expanded' : 'collapsed';
		storeState( next );
		applyState( next );
		syncButtons( next );
	}

	function icon() {
		var ns = 'http://www.w3.org/2000/svg';
		var svg = document.createElementNS( ns, 'svg' );
		svg.setAttribute( 'viewBox', '0 0 24 24' );
		svg.setAttribute( 'aria-hidden', 'true' );
		svg.setAttribute( 'focusable', 'false' );
		var path = document.createElementNS( ns, 'path' );
		path.setAttribute( 'fill', 'none' );
		path.setAttribute( 'stroke', 'currentColor' );
		path.setAttribute( 'stroke-width', '1.9' );
		path.setAttribute( 'stroke-linecap', 'round' );
		path.setAttribute( 'd', 'M4 6h16M4 12h10M4 18h16' );
		svg.appendChild( path );
		return svg;
	}

	/* Highlight custom destinations core cannot resolve (e.g. /account/...). */
	function markCurrent( rail ) {
		var here = window.location.pathname.replace( /\/+$/, '' ) || '/';
		Array.prototype.forEach.call( rail.querySelectorAll( '.wp-block-navigation-item__content' ), function ( link ) {
			var item = link.closest( '.wp-block-navigation-item' );
			if ( ! item ) { return; }
			if ( item.getAttribute( 'data-mw-current' ) ) {
				item.removeAttribute( 'data-mw-current' );
				item.classList.remove( 'current-menu-item' );
				link.removeAttribute( 'aria-current' );
			}
			if ( item.classList.contains( 'current-menu-item' ) ) { return; }
			var url;
			try { url = new URL( link.getAttribute( 'href' ), window.location.href ); } catch ( e ) { return; }
			if ( url.origin !== window.location.origin ) { return; }
			var target = url.pathname.replace( /\/+$/, '' ) || '/';
			var hereType = new URLSearchParams( window.location.search ).get( 'post_type' );
			var matches =
				( url.searchParams.has( 'post_type' ) && url.searchParams.get( 'post_type' ) === hereType ) ||
				( ! url.search && '/' !== target && ( here === target || 0 === here.indexOf( target + '/' ) ) );
			if ( matches ) {
				item.setAttribute( 'data-mw-current', '1' );
				item.classList.add( 'current-menu-item' );
				link.setAttribute( 'aria-current', 'page' );
			}
		} );
		// Only the most specific match stays active per rail.
		var current = rail.querySelectorAll( '[data-mw-current]' );
		if ( current.length > 1 ) {
			var best = null, bestLen = -1;
			Array.prototype.forEach.call( current, function ( item ) {
				var len = ( item.querySelector( 'a' ).getAttribute( 'href' ) || '' ).length;
				if ( len > bestLen ) { best = item; bestLen = len; }
			} );
			Array.prototype.forEach.call( current, function ( item ) {
				if ( item !== best ) {
					item.removeAttribute( 'data-mw-current' );
					item.classList.remove( 'current-menu-item' );
					item.querySelector( 'a' ).removeAttribute( 'aria-current' );
				}
			} );
		}
	}

	function enhance( rail ) {
		if ( rail.getAttribute( readyAttribute ) ) {
			markCurrent( rail );
			return;
		}
		rail.setAttribute( readyAttribute, '1' );

		Array.prototype.forEach.call( rail.querySelectorAll( '.wp-block-navigation-item__content' ), function ( link ) {
			var labelNode = link.querySelector( '.wp-block-navigation-item__label' );
			var label = ( ( labelNode || link ).textContent || '' ).trim();
			if ( label ) { link.setAttribute( 'data-mw-label', label ); }
		} );
		markCurrent( rail );

		var brand = rail.querySelector( '.mw-rail__brand' ) || rail;
		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'mw-rail__toggle';
		button.setAttribute( 'aria-controls', rail.id || 'mw-rail' );
		button.appendChild( icon() );
		button.addEventListener( 'click', onToggleClick );
		brand.appendChild( button );

		var topInner = document.querySelector( '.mw-site-header--stream .mw-stream-topbar .mw-site-header__inner' );
		if ( topInner && ! topInner.querySelector( '.mw-rail__toggle--bar' ) ) {
			var bar = button.cloneNode( true );
			bar.className = 'mw-rail__toggle mw-rail__toggle--bar';
			bar.addEventListener( 'click', onToggleClick );
			topInner.insertBefore( bar, topInner.firstChild );
		}

		if ( rail.parentNode && ! document.querySelector( '.mw-rail__backdrop' ) ) {
			var backdrop = document.createElement( 'div' );
			backdrop.className = 'mw-rail__backdrop';
			backdrop.addEventListener( 'click', function () { setDrawerOpen( false ); } );
			rail.parentNode.insertBefore( backdrop, rail );
		}

		// Close the drawer after choosing a destination (soft navigation friendly).
		rail.addEventListener( 'click', function ( e ) {
			if ( isDrawerMobile() && e.target.closest( 'a' ) ) { setDrawerOpen( false ); }
		} );
	}

	function initializeAll() {
		var rails = document.querySelectorAll( '.mw-site-header--stream .mw-rail' );
		if ( ! rails.length ) { return; }
		Array.prototype.forEach.call( rails, enhance );
		applyState( savedState() );
		syncButtons( currentState() );
		if ( ! isDrawerMobile() ) { setDrawerOpen( false ); }
	}

	window.addEventListener( 'storage', function ( e ) {
		if ( e.key === storageKey || null === e.key ) {
			var s = savedState();
			applyState( s );
			syncButtons( s );
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key && drawerOpen() ) {
			setDrawerOpen( false );
			return;
		}
		// Ctrl/⌘ + K (or "/" outside inputs) focuses the stream search.
		var typing = /^(INPUT|TEXTAREA|SELECT)$/.test( ( e.target && e.target.tagName ) || '' ) || ( e.target && e.target.isContentEditable );
		if ( ( ( e.ctrlKey || e.metaKey ) && 'k' === e.key.toLowerCase() ) || ( '/' === e.key && ! typing ) ) {
			var field = document.querySelector( '.mw-stream-search .wp-block-search__input, .mw-header-search .wp-block-search__input' );
			if ( field ) {
				e.preventDefault();
				field.focus();
				field.select();
			}
		}
	} );

	window.addEventListener( 'resize', function () {
		if ( ! isDrawerMobile() ) {
			setDrawerOpen( false );
			syncButtons( currentState() );
		}
	} );

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initializeAll );
	} else {
		initializeAll();
	}
	document.addEventListener( 'mw-page-rendered', initializeAll );
} )();