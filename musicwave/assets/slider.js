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
						viewport.scrollLeft / ( step() * visibleCount() )
					)
				)
			);
		}

		function update() {
			measurePageCount();
			var page = currentPage();
			var atStart = viewport.scrollLeft <= 2;
			var atEnd =
				viewport.scrollLeft + viewport.clientWidth >=
				viewport.scrollWidth - 2;

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
			viewport.scrollTo( {
				left: page * step() * visibleCount(),
				behavior: reducedMotion.matches ? 'auto' : 'smooth',
			} );
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
			if ( 'ArrowLeft' === event.key ) {
				event.preventDefault();
				move( -1 );
			} else if ( 'ArrowRight' === event.key ) {
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

				function atStart() {
					return viewport.scrollLeft <= 2;
				}

				function atEnd() {
					return (
						viewport.scrollLeft + viewport.clientWidth >=
						viewport.scrollWidth - 2
					);
				}

				function sync() {
					if ( previous ) {
						previous.disabled = atStart();
					}
					if ( next ) {
						next.disabled = atEnd();
					}
				}

				function move( direction ) {
					var max = viewport.scrollWidth - viewport.clientWidth;
					var target = viewport.scrollLeft + direction * step();
					// offsetLeft is layout-relative (always LTR-positive), so
					// clamp against the row edges instead of trusting sign.
					target = Math.max( 0, Math.min( max, target ) );
					viewport.scrollTo( {
						left: target,
						behavior: reducedMotion.matches ? 'auto' : 'smooth',
					} );
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
				sync();
			} );
	}

	// Swapped-in pages from the persistent player navigation reuse the same
	// initialization path through the `mw-page-rendered` event.
	document.addEventListener( 'DOMContentLoaded', initializeAll );
	document.addEventListener( 'mw-page-rendered', initializeAll );
} )();
