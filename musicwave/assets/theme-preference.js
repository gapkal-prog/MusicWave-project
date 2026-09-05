( function () {
	'use strict';

	var storageKey = 'musicwave-theme';
	var themes = [ 'system', 'light', 'dark' ];
	var readyAttribute = 'data-mw-theme-ready';

	function savedTheme() {
		try {
			var saved = window.localStorage.getItem( storageKey );
			return themes.indexOf( saved ) !== -1 ? saved : 'system';
		} catch ( error ) {
			return 'system';
		}
	}

	function applyTheme( theme ) {
		if ( 'system' === theme ) {
			document.documentElement.removeAttribute( 'data-mw-theme' );
			return;
		}

		document.documentElement.setAttribute( 'data-mw-theme', theme );
	}

	function storeTheme( theme ) {
		try {
			if ( 'system' === theme ) {
				window.localStorage.removeItem( storageKey );
			} else {
				window.localStorage.setItem( storageKey, theme );
			}
		} catch ( error ) {}
	}

	function labels() {
		return window.musicwaveThemePreference &&
			window.musicwaveThemePreference.labels
			? window.musicwaveThemePreference.labels
			: {};
	}

	function updateButton( button, theme ) {
		var text = labels()[ theme ] || theme;
		// The button ships three inline SVG icons; theme-toggle.css reveals the
		// one matching data-mw-theme-value. Legacy markup (a single glyph) is
		// left untouched so the control keeps working without the icons.
		button.setAttribute( 'data-mw-theme-value', theme );
		button.setAttribute( 'aria-label', text );
		button.setAttribute( 'title', text );
	}

	function nextTheme( theme ) {
		return themes[ ( themes.indexOf( theme ) + 1 ) % themes.length ];
	}

	function allButtons() {
		return Array.prototype.slice.call(
			document.querySelectorAll( '.mw-theme-toggle' )
		);
	}

	function syncButtons( theme ) {
		allButtons().forEach( function ( button ) {
			updateButton( button, theme );
		} );
	}

	function initialize( button ) {
		button.addEventListener( 'click', function () {
			var theme = nextTheme( savedTheme() );
			storeTheme( theme );
			applyTheme( theme );
			// Header, footer, and sidebar toggles share one preference; keep
			// every instance (including ones added later) in sync.
			syncButtons( theme );
		} );
	}

	function initializeAll() {
		var theme = savedTheme();
		applyTheme( theme );
		allButtons().forEach( function ( button ) {
			updateButton( button, theme );
			if ( ! button.getAttribute( readyAttribute ) ) {
				button.setAttribute( readyAttribute, '1' );
				initialize( button );
			}
		} );
	}

	// Another tab changed the preference: mirror it without a reload.
	window.addEventListener( 'storage', function ( event ) {
		if ( event.key === storageKey || null === event.key ) {
			var theme = savedTheme();
			applyTheme( theme );
			syncButtons( theme );
		}
	} );

	// Swapped-in pages from the persistent player navigation reuse the same
	// initialization path through the `mw-page-rendered` event.
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initializeAll );
	} else {
		initializeAll();
	}
	document.addEventListener( 'mw-page-rendered', initializeAll );
} )();
