/**
 * Release slider: scroll-snap carousel driven by arrows, dots, and autoplay.
 *
 * The viewport is the single source of truth: arrows, dots, and autoplay all
 * scroll it, and a rAF-throttled scroll listener keeps controls in sync. Page
 * geometry is measured from the DOM on demand so the arrows keep working when
 * the pagination dots are disabled.
 *
 * @package
 */
( function () {
	'use strict';

	var labels = window.musicwaveSlider || {};
	var reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );

	/*
	 * Direction-aware scroll geometry.
	 *
	 * In right-to-left containers browsers report scrollLeft as 0 at the
	 * logical start and grow it towards NEGATIVE values as the row scrolls
	 * towards its logical end (Firefox, Chromium, Safari), so start/end
	 * detection and target math must use the logical offset instead of the
	 * raw property. Everything below works in "logical pixels": 0 at the
	 * start of the row, scrollWidth - clientWidth at the end.
	 */
	function isRightToLeft( element ) {
		return 'rtl' === window.getComputedStyle( element ).direction;
	}

	function logicalScroll( viewport ) {
		return Math.abs( viewport.scrollLeft );
	}

	function maxLogicalScroll( viewport ) {
		return Math.max( 0, viewport.scrollWidth - viewport.clientWidth );
	}

	function scrollToLogical( viewport, offset ) {
		var target = Math.max(
			0,
			Math.min( maxLogicalScroll( viewport ), offset )
		);

		viewport.scrollTo( {
			left: isRightToLeft( viewport ) ? -target : target,
			behavior: reducedMotion.matches ? 'auto' : 'smooth',
		} );
	}

	function isAtStart( viewport ) {
		return logicalScroll( viewport ) <= 2;
	}

	function isAtEnd( viewport ) {
		return logicalScroll( viewport ) >= maxLogicalScroll( viewport ) - 2;
	}

	function initialize( slider ) {
		var viewport = slider.querySelector( '[data-mw-slider-viewport]' );
		var slides = Array.prototype.slice.call(
			slider.querySelectorAll( '.mw-release-slider__slide' )
		);
		var previous = slider.querySelector( '[data-mw-slider-previous]' );
		var next = slider.querySelector( '[data-mw-slider-next]' );
		var dots = slider.querySelector( '[data-mw-slider-dots]' );

		if (
			! viewport ||
			slides.length < 2 ||
			( ! previous && ! next && ! dots )
		) {
			return;
		}
		var status = slider.querySelector( '[data-mw-slider-status]' );
		var autoplay = '1' === slider.getAttribute( 'data-autoplay' );
		var loop = '1' === slider.getAttribute( 'data-loop' );
		var pauseOnHover = '1' === slider.getAttribute( 'data-pause-hover' );
		var interval =
			parseInt( slider.getAttribute( 'data-interval' ), 10 ) || 5000;
		var timer = null;
		var paused = false;
		var pageCount = 1;

		function step() {
			var first = slides[ 0 ];
			var second = slides[ 1 ];

			return second
				? Math.abs( second.offsetLeft - first.offsetLeft )
				: first.offsetWidth || 1;
		}

		function visibleCount() {
			return Math.max( 1, Math.round( viewport.clientWidth / step() ) );
		}

		// Measured independently of the dots markup so arrow navigation works
		// even when "نقاط صفحه‌بندی" is switched off.
		function measurePageCount() {
			pageCount = Math.max(
				1,
				Math.ceil( slides.length / visibleCount() )
			);
		}

		function currentPage() {
			return Math.max(
				0,
				Math.min(
					pageCount - 1,
					Math.round(
						logicalScroll( viewport ) / ( step() * visibleCount() )
					)
				)
			);
		}

		function update() {
			measurePageCount();
			var page = currentPage();
			var atStart = isAtStart( viewport );
			var atEnd = isAtEnd( viewport );

			if ( previous ) {
				previous.disabled = ! loop && atStart;
			}
			if ( next ) {
				next.disabled = ! loop && atEnd;
			}
			if ( dots ) {
				Array.prototype.forEach.call(
					dots.children,
					function ( dot, index ) {
						dot.setAttribute(
							'aria-current',
							index === page ? 'true' : 'false'
						);
					}
				);
			}
			if ( status ) {
				status.textContent = (
					labels.status || 'گروه اسلاید %1$d از %2$d'
				)
					.replace( '%1$d', page + 1 )
					.replace( '%2$d', pageCount );
			}
		}

		function goTo( page ) {
			scrollToLogical( viewport, page * step() * visibleCount() );
		}

		function move( direction ) {
			measurePageCount();
			var target = currentPage() + direction;

			if ( target >= pageCount ) {
				target = loop ? 0 : pageCount - 1;
			}
			if ( target < 0 ) {
				target = loop ? pageCount - 1 : 0;
			}
			goTo( target );
		}

		function buildDots() {
			measurePageCount();
			if ( ! dots ) {
				update();

				return;
			}
			dots.replaceChildren();
			for ( var index = 0; index < pageCount; index += 1 ) {
				( function ( page ) {
					var dot = document.createElement( 'button' );

					dot.type = 'button';
					dot.setAttribute(
						'aria-label',
						(
							labels.goToGroup || 'رفتن به گروه اسلاید %d'
						).replace( '%d', page + 1 )
					);
					dot.addEventListener( 'click', function () {
						goTo( page );
						start();
					} );
					dots.appendChild( dot );
				} )( index );
			}
			update();
		}

		function stop() {
			if ( timer ) {
				window.clearInterval( timer );
				timer = null;
			}
		}

		function start() {
			stop();
			if (
				! autoplay ||
				paused ||
				document.hidden ||
				reducedMotion.matches
			) {
				return;
			}
			timer = window.setInterval( function () {
				move( 1 );
			}, interval );
		}

		if ( previous ) {
			previous.addEventListener( 'click', function () {
				move( -1 );
				start();
			} );
		}
		if ( next ) {
			next.addEventListener( 'click', function () {
				move( 1 );
				start();
			} );
		}

		viewport.addEventListener(
			'scroll',
			function () {
				window.requestAnimationFrame( update );
			},
			{ passive: true }
		);
		viewport.addEventListener( 'pointerdown', stop );
		viewport.addEventListener( 'pointerup', start );
		viewport.addEventListener( 'keydown', function ( event ) {
			// Arrow keys follow the reading direction: in RTL the left arrow
			// advances towards the next slide group.
			var forwardKey = isRightToLeft( viewport )
				? 'ArrowLeft'
				: 'ArrowRight';
			var backwardKey = isRightToLeft( viewport )
				? 'ArrowRight'
				: 'ArrowLeft';

			if ( backwardKey === event.key ) {
				event.preventDefault();
				move( -1 );
			} else if ( forwardKey === event.key ) {
				event.preventDefault();
				move( 1 );
			}
		} );

		if ( pauseOnHover ) {
			slider.addEventListener( 'mouseenter', function () {
				paused = true;
				stop();
			} );
			slider.addEventListener( 'mouseleave', function () {
				paused = false;
				start();
			} );
			slider.addEventListener( 'focusin', function () {
				paused = true;
				stop();
			} );
			slider.addEventListener( 'focusout', function () {
				paused = false;
				start();
			} );
		}

		document.addEventListener( 'visibilitychange', start );
		window.addEventListener( 'resize', buildDots );
		buildDots();
		start();
	}

	function initializeAll() {
		document
			.querySelectorAll( '[data-mw-slider]' )
			.forEach( function ( slider ) {
				if ( slider.getAttribute( 'data-mw-slider-ready' ) ) {
					return;
				}
				slider.setAttribute( 'data-mw-slider-ready', '1' );
				initialize( slider );
			} );
		initializeShelves();
	}

	/*
	 * SonicStream horizontal shelves: the scroll shelf renders floating
	 * prev/next arrows around a scroll-snap row. Scrolling advances by one
	 * card step; disabled states follow the row edges so the arrows mirror
	 * the slider arrows' behavior without paging.
	 */
	function initializeShelves() {
		document
			.querySelectorAll( '.mw-release-shelf--scroll' )
			.forEach( function ( shelf ) {
				if ( shelf.getAttribute( 'data-mw-shelf-ready' ) ) {
					return;
				}
				var viewport = shelf.querySelector(
					'[data-mw-shelf-viewport]'
				);
				var previous = shelf.querySelector(
					'[data-mw-shelf-previous]'
				);
				var next = shelf.querySelector( '[data-mw-shelf-next]' );

				if ( ! viewport || ( ! previous && ! next ) ) {
					return;
				}
				shelf.setAttribute( 'data-mw-shelf-ready', '1' );

				function step() {
					var first = viewport.firstElementChild;
					var second = first ? first.nextElementSibling : null;

					if ( second ) {
						return Math.abs( second.offsetLeft - first.offsetLeft );
					}
					if ( first ) {
						return first.offsetWidth || 1;
					}
					return viewport.clientWidth || 1;
				}

				function sync() {
					// Rows that fit entirely need no arrows at all.
					var scrollable = maxLogicalScroll( viewport ) > 2;

					shelf.classList.toggle(
						'mw-release-shelf--scrollable',
						scrollable
					);
					if ( previous ) {
						previous.disabled =
							! scrollable || isAtStart( viewport );
					}
					if ( next ) {
						next.disabled = ! scrollable || isAtEnd( viewport );
					}
				}

				function move( direction ) {
					// Advance by whole visible cards so the snap points line up,
					// but never less than one card.
					var visible = Math.max(
						1,
						Math.floor( viewport.clientWidth / step() )
					);
					var distance = visible * step();

					scrollToLogical(
						viewport,
						logicalScroll( viewport ) + direction * distance
					);
				}

				if ( previous ) {
					previous.addEventListener( 'click', function () {
						move( -1 );
					} );
				}
				if ( next ) {
					next.addEventListener( 'click', function () {
						move( 1 );
					} );
				}
				viewport.addEventListener(
					'scroll',
					function () {
						window.requestAnimationFrame( sync );
					},
					{ passive: true }
				);
				window.addEventListener( 'resize', sync );
				// Lazy-loaded artwork changes the row width after init.
				Array.prototype.forEach.call(
					viewport.querySelectorAll( 'img' ),
					function ( image ) {
						if ( ! image.complete ) {
							image.addEventListener( 'load', sync, {
								once: true,
							} );
						}
					}
				);
				sync();
			} );
	}

	// Swapped-in pages from the persistent player navigation reuse the same
	// initialization path through the `mw-page-rendered` event.
	document.addEventListener( 'DOMContentLoaded', initializeAll );
	document.addEventListener( 'mw-page-rendered', initializeAll );
} )();
