/**
 * Accessible catalog autocomplete.
 *
 * Progressive enhancement only: without JavaScript the search field stays a
 * plain input inside the catalog filter form and submits a normal GET request.
 * With JavaScript it becomes an ARIA 1.2 combobox backed by
 * `music-wave/v1/catalog/suggest`, which returns published catalog data only.
 */
( function ( config ) {
	'use strict';

	if ( ! config || ! config.endpoint || ! window.fetch ) {
		return;
	}

	var MIN_LENGTH = parseInt( config.minLength, 10 ) || 2;
	var DEBOUNCE = 220;

	function debounce( callback, wait ) {
		var timer = null;
		return function () {
			var args = arguments;
			window.clearTimeout( timer );
			timer = window.setTimeout( function () {
				callback.apply( null, args );
			}, wait );
		};
	}

	function setup( input ) {
		var wrapper = input.closest( '.mw-catalog-suggest' );
		if ( ! wrapper || wrapper.getAttribute( 'data-mw-suggest-ready' ) ) {
			return;
		}
		wrapper.setAttribute( 'data-mw-suggest-ready', 'true' );

		var listId =
			'mw-catalog-suggest-list-' +
			Math.random().toString( 36 ).slice( 2, 8 );
		var list = document.createElement( 'ul' );
		list.id = listId;
		list.className = 'mw-catalog-suggest__list';
		list.setAttribute( 'role', 'listbox' );
		list.hidden = true;
		wrapper.appendChild( list );

		var status = wrapper.querySelector( '[data-mw-suggest-status]' );
		var active = -1;
		var items = [];
		var controller = null;

		input.setAttribute( 'role', 'combobox' );
		input.setAttribute( 'aria-expanded', 'false' );
		input.setAttribute( 'aria-controls', listId );
		input.setAttribute( 'aria-autocomplete', 'list' );
		input.setAttribute( 'autocomplete', 'off' );

		function close() {
			list.hidden = true;
			list.innerHTML = '';
			items = [];
			active = -1;
			input.setAttribute( 'aria-expanded', 'false' );
			input.removeAttribute( 'aria-activedescendant' );
		}

		function announce( count ) {
			if ( ! status ) {
				return;
			}
			if ( ! count ) {
				status.textContent = config.noResults || '';
				return;
			}
			status.textContent = ( config.resultsCount || '%d' ).replace(
				'%d',
				String( count )
			);
		}

		function highlight( index ) {
			items.forEach( function ( option, position ) {
				var selected = position === index;
				option.setAttribute(
					'aria-selected',
					selected ? 'true' : 'false'
				);
				option.classList.toggle( 'is-active', selected );
			} );
			if ( index > -1 && items[ index ] ) {
				input.setAttribute(
					'aria-activedescendant',
					items[ index ].id
				);
			} else {
				input.removeAttribute( 'aria-activedescendant' );
			}
			active = index;
		}

		function render( suggestions ) {
			list.innerHTML = '';
			items = [];
			if ( ! suggestions.length ) {
				close();
				announce( 0 );
				return;
			}

			suggestions.forEach( function ( suggestion, index ) {
				var option = document.createElement( 'li' );
				option.id = listId + '-option-' + index;
				option.className = 'mw-catalog-suggest__option';
				option.setAttribute( 'role', 'option' );
				option.setAttribute( 'aria-selected', 'false' );
				option.textContent = suggestion.label;

				var type = document.createElement( 'span' );
				type.className = 'mw-catalog-suggest__type';
				type.textContent =
					config.typeLabels && config.typeLabels[ suggestion.type ]
						? config.typeLabels[ suggestion.type ]
						: suggestion.type;
				option.appendChild( type );

				option.addEventListener( 'mousedown', function ( event ) {
					event.preventDefault();
					if ( suggestion.url ) {
						window.location.href = suggestion.url;
						return;
					}
					input.value = suggestion.label;
					close();
				} );

				list.appendChild( option );
				items.push( option );
			} );

			list.hidden = false;
			input.setAttribute( 'aria-expanded', 'true' );
			highlight( -1 );
			announce( suggestions.length );
		}

		var query = debounce( function ( term ) {
			if ( controller && controller.abort ) {
				controller.abort();
			}
			controller = window.AbortController
				? new window.AbortController()
				: null;

			var url =
				config.endpoint +
				( config.endpoint.indexOf( '?' ) > -1 ? '&' : '?' ) +
				'term=' +
				window.encodeURIComponent( term );
			window
				.fetch( url, {
					credentials: 'same-origin',
					signal: controller ? controller.signal : undefined,
					headers: { Accept: 'application/json' },
				} )
				.then( function ( response ) {
					return response.ok ? response.json() : { items: [] };
				} )
				.then( function ( payload ) {
					render( payload && payload.items ? payload.items : [] );
				} )
				.catch( function () {
					close();
				} );
		}, DEBOUNCE );

		input.addEventListener( 'input', function () {
			var term = input.value.trim();
			if ( term.length < MIN_LENGTH ) {
				close();
				return;
			}
			query( term );
		} );

		input.addEventListener( 'keydown', function ( event ) {
			if ( list.hidden || ! items.length ) {
				return;
			}
			if ( 'ArrowDown' === event.key ) {
				event.preventDefault();
				highlight( active + 1 >= items.length ? 0 : active + 1 );
				return;
			}
			if ( 'ArrowUp' === event.key ) {
				event.preventDefault();
				highlight( active - 1 < 0 ? items.length - 1 : active - 1 );
				return;
			}
			if ( 'Enter' === event.key && active > -1 ) {
				event.preventDefault();
				items[ active ].dispatchEvent(
					new window.MouseEvent( 'mousedown' )
				);
				return;
			}
			if ( 'Escape' === event.key ) {
				close();
			}
		} );

		input.addEventListener( 'blur', function () {
			window.setTimeout( close, 120 );
		} );
	}

	function init() {
		var inputs = document.querySelectorAll(
			'.mw-catalog-suggest input[type="search"]'
		);
		Array.prototype.forEach.call( inputs, setup );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
	// The persistent player and catalog filters swap fresh form markup in via
	// `mw-page-rendered`; setup() is guarded per wrapper, so re-running is safe.
	document.addEventListener( 'mw-page-rendered', init );
} )( window.musicWaveCatalogSuggest );
