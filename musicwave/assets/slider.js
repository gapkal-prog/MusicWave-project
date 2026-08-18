(function () {
	'use strict';

	function initialize(slider) {
		var viewport = slider.querySelector('[data-mw-slider-viewport]');
		var slides = Array.prototype.slice.call(slider.querySelectorAll('.mw-release-slider__slide'));
		var previous = slider.querySelector('[data-mw-slider-previous]');
		var next = slider.querySelector('[data-mw-slider-next]');
		var dots = slider.querySelector('[data-mw-slider-dots]');
		var status = slider.querySelector('[data-mw-slider-status]');
		var autoplay = '1' === slider.getAttribute('data-autoplay');
		var loop = '1' === slider.getAttribute('data-loop');
		var pauseOnHover = '1' === slider.getAttribute('data-pause-hover');
		var interval = parseInt(slider.getAttribute('data-interval'), 10) || 5000;
		var timer = null;
		var paused = false;
		var pageCount = 1;
		var labels = window.musicwaveSlider || {};

		if (!viewport || slides.length < 2) {
			return;
		}

		function step() {
			var first = slides[0];
			var second = slides[1];
			return second ? Math.abs(second.offsetLeft - first.offsetLeft) : first.offsetWidth;
		}

		function visibleCount() {
			return Math.max(1, Math.round(viewport.clientWidth / step()));
		}

		function currentPage() {
			return Math.max(0, Math.min(pageCount - 1, Math.round(viewport.scrollLeft / (step() * visibleCount()))));
		}

		function update() {
			var page = currentPage();
			var atStart = viewport.scrollLeft <= 2;
			var atEnd = viewport.scrollLeft + viewport.clientWidth >= viewport.scrollWidth - 2;
			if (previous) {
				previous.disabled = !loop && atStart;
			}
			if (next) {
				next.disabled = !loop && atEnd;
			}
			if (dots) {
				Array.prototype.forEach.call(dots.children, function (dot, index) {
					dot.setAttribute('aria-current', index === page ? 'true' : 'false');
				});
			}
			if (status) {
				status.textContent = (labels.status || 'Slide group %1$d of %2$d')
					.replace('%1$d', page + 1)
					.replace('%2$d', pageCount);
			}
		}

		function goTo(page) {
			viewport.scrollTo({ left: page * step() * visibleCount(), behavior: 'smooth' });
		}

		function move(direction) {
			var target = currentPage() + direction;
			if (target >= pageCount) {
				target = loop ? 0 : pageCount - 1;
			}
			if (target < 0) {
				target = loop ? pageCount - 1 : 0;
			}
			goTo(target);
		}

		function buildDots() {
			if (!dots) {
				return;
			}
			pageCount = Math.max(1, Math.ceil(slides.length / visibleCount()));
			dots.innerHTML = '';
			for (var index = 0; index < pageCount; index += 1) {
				(function (page) {
					var dot = document.createElement('button');
					dot.type = 'button';
					dot.setAttribute('aria-label', (labels.goToGroup || 'Go to slide group %d').replace('%d', page + 1));
					dot.addEventListener('click', function () {
						goTo(page);
					});
					dots.appendChild(dot);
				}(index));
			}
			update();
		}

		function stop() {
			if (timer) {
				window.clearInterval(timer);
				timer = null;
			}
		}

		function start() {
			stop();
			if (!autoplay || paused || document.hidden || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
				return;
			}
			timer = window.setInterval(function () {
				move(1);
			}, interval);
		}

		if (previous) {
			previous.addEventListener('click', function () {
				move(-1);
				start();
			});
		}
		if (next) {
			next.addEventListener('click', function () {
				move(1);
				start();
			});
		}

		viewport.addEventListener('scroll', function () {
			window.requestAnimationFrame(update);
		}, { passive: true });
		viewport.addEventListener('pointerdown', stop);
		viewport.addEventListener('pointerup', start);

		if (pauseOnHover) {
			slider.addEventListener('mouseenter', function () {
				paused = true;
				stop();
			});
			slider.addEventListener('mouseleave', function () {
				paused = false;
				start();
			});
			slider.addEventListener('focusin', function () {
				paused = true;
				stop();
			});
			slider.addEventListener('focusout', function () {
				paused = false;
				start();
			});
		}

		document.addEventListener('visibilitychange', start);
		window.addEventListener('resize', buildDots);
		buildDots();
		start();
	}

	function initializeAll() {
		document.querySelectorAll('[data-mw-slider]').forEach(function (slider) {
			if (slider.getAttribute('data-mw-slider-ready')) {
				return;
			}
			slider.setAttribute('data-mw-slider-ready', '1');
			initialize(slider);
		});
	}

	// Swapped-in pages from the persistent player navigation reuse the same
	// initialization path through the `mw-page-rendered` event.
	document.addEventListener('DOMContentLoaded', initializeAll);
	document.addEventListener('mw-page-rendered', initializeAll);
}());
