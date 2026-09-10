/**
 * MusicWave chart tabs controller — accessible tablist with keyboard support.
 *
 * All panels are server-rendered (progressive enhancement); this script only
 * switches visibility, roving tabindex and aria-selected. No-JS users see the
 * default tab panel.
 */
( function () {
	'use strict';

	function activate( root, key, focus ) {
		var tabs = Array.prototype.slice.call(
			root.querySelectorAll( '[data-mw-chart-tab]' )
		);
		var panels = Array.prototype.slice.call(
			root.querySelectorAll( '[data-mw-chart-panel]' )
		);
		tabs.forEach( function ( tab ) {
			var isActive = tab.getAttribute( 'data-mw-chart-tab' ) === key;
			tab.classList.toggle( 'is-active', isActive );
			tab.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			tab.setAttribute( 'tabindex', isActive ? '0' : '-1' );
			if ( isActive && focus ) {
				tab.focus();
			}
		} );
		panels.forEach( function ( panel ) {
			var isActive = panel.getAttribute( 'data-mw-chart-panel' ) === key;
			if ( isActive ) {
				panel.removeAttribute( 'hidden' );
			} else {
				panel.setAttribute( 'hidden', '' );
			}
		} );
	}

	function initRoot( root ) {
		if ( root.__mwChartBound ) {
			return;
		}
		root.__mwChartBound = true;

		var tabs = Array.prototype.slice.call(
			root.querySelectorAll( '[data-mw-chart-tab]' )
		);
		if ( tabs.length < 2 ) {
			return;
		}

		root.addEventListener( 'click', function ( event ) {
			var tab = event.target.closest( '[data-mw-chart-tab]' );
			if ( ! tab || ! root.contains( tab ) ) {
				return;
			}
			activate( root, tab.getAttribute( 'data-mw-chart-tab' ), false );
		} );

		root.addEventListener( 'keydown', function ( event ) {
			var tab = event.target.closest( '[data-mw-chart-tab]' );
			if ( ! tab ) {
				return;
			}
			var keys = tabs.map( function ( element ) {
				return element.getAttribute( 'data-mw-chart-tab' );
			} );
			var index = keys.indexOf( tab.getAttribute( 'data-mw-chart-tab' ) );
			var next = null;
			if ( event.key === 'ArrowRight' || event.key === 'ArrowLeft' ) {
				var direction = event.key === 'ArrowRight' ? 1 : -1;
				if ( document.dir === 'rtl' ) {
					direction = direction * -1;
				}
				next = keys[ ( index + direction + keys.length ) % keys.length ];
			} else if ( event.key === 'Home' ) {
				next = keys[ 0 ];
			} else if ( event.key === 'End' ) {
				next = keys[ keys.length - 1 ];
			}
			if ( next ) {
				event.preventDefault();
				activate( root, next, true );
			}
		} );
	}

	function init() {
		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-mw-chart-tabs]' ),
			initRoot
		);
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
	document.addEventListener( 'mw-page-rendered', init );
} )();
