/**
 * MusicWave synced lyrics highlighter — dependency-free, progressive enhancement.
 *
 * Listens to the nearest <audio> on the page (global preview player first)
 * and highlights the active [data-mw-lyrics-time] row. Without audio it
 * degrades to a readable plain list; no-JS users get the <noscript> plain block.
 */
( function () {
	'use strict';

	function parseTime( value ) {
		var seconds = parseFloat( value );
		return isFinite( seconds ) ? seconds : null;
	}

	function initPanel( panel ) {
		var rows = Array.prototype.slice.call(
			panel.querySelectorAll( '[data-mw-lyrics-time]' )
		);
		if ( ! rows.length ) {
			return;
		}

		var timed = rows
			.map( function ( row ) {
				return {
					row: row,
					time: parseTime( row.getAttribute( 'data-mw-lyrics-time' ) ),
				};
			} )
			.filter( function ( entry ) {
				return entry.time !== null;
			} )
			.sort( function ( a, b ) {
				return a.time - b.time;
			} );

		if ( ! timed.length ) {
			return;
		}

		function audio() {
			return (
				document.querySelector( '[data-mw-preview-player] audio' ) ||
				document.querySelector( 'audio' )
			);
		}

		function paint( current ) {
			var activeIndex = -1;
			timed.forEach( function ( entry, index ) {
				if ( entry.time <= current + 0.25 ) {
					activeIndex = index;
				}
			} );
			timed.forEach( function ( entry, index ) {
				entry.row.classList.toggle( 'is-active', index === activeIndex );
				entry.row.classList.toggle( 'is-past', index < activeIndex );
				if ( index === activeIndex ) {
					entry.row.setAttribute( 'aria-current', 'true' );
				} else {
					entry.row.removeAttribute( 'aria-current' );
				}
			} );
			if (
				activeIndex >= 0 &&
				! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
			) {
				var active = timed[ activeIndex ].row;
				if ( typeof active.scrollIntoView === 'function' ) {
					active.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
				}
			}
		}

		function bind() {
			var player = audio();
			if ( ! player || player.__mwLyricsBound ) {
				return;
			}
			player.__mwLyricsBound = true;
			player.addEventListener( 'timeupdate', function () {
				paint( player.currentTime || 0 );
			} );
			player.addEventListener( 'seeked', function () {
				paint( player.currentTime || 0 );
			} );
		}

		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', bind );
		} else {
			bind();
		}
		document.addEventListener( 'mw-page-rendered', bind );
	}

	function init() {
		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-mw-lyrics-synced]' ),
			initPanel
		);
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
	document.addEventListener( 'mw-page-rendered', init );
} )();
