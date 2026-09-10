/**
 * MusicWave — SonicStream Pro helpers (toast, waveform, micro-interactions)
 *
 * Small, dependency-free enhancer that ships on every route. Keeps the gl
 * global player independent but adds delightful touches:
 *  - Toast stack for copy/share/queue/playlist actions
 *  - Waveform bars for the global player (32 bars, per-track pseudo-random)
 *  - Heart burst re-trigger helper for library buttons
 *  - Skeleton shimmer coordination for async shelves
 *
 * No build step — plain script, enqueued in musicwave_enqueue_assets().
 */
(function () {
	'use strict';

	/* ------------------------------------------------------------------ *
	 * Toast stack
	 * ------------------------------------------------------------------ */
	function ensureStack() {
		var stack = document.querySelector('.mw-toast-stack');
		if (stack) return stack;
		stack = document.createElement('div');
		stack.className = 'mw-toast-stack';
		stack.setAttribute('aria-live', 'polite');
		stack.setAttribute('aria-relevant', 'additions');
		document.body.appendChild(stack);
		return stack;
	}

	function showToast(message, variant) {
		if (!message) return;
		var stack = ensureStack();
		var toast = document.createElement('div');
		toast.className = 'mw-toast mw-toast--' + (variant || 'info');
		toast.setAttribute('role', 'status');

		var iconMap = {
			success: '<svg class="mw-toast__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>',
			error: '<svg class="mw-toast__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>',
			info: '<svg class="mw-toast__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>'
		};

		toast.innerHTML = (iconMap[variant] || iconMap.info) + '<span>' + message + '</span>';
		stack.appendChild(toast);

		// Auto-dismiss after 3.2s with exit animation
		window.setTimeout(function () {
			toast.style.animation = 'mw-toast-out 260ms var(--mw-ease-smooth) forwards';
			window.setTimeout(function () {
				if (toast.parentNode) toast.parentNode.removeChild(toast);
				if (stack && !stack.children.length && stack.parentNode) {
					// Keep stack node for next toast; no need to remove
				}
			}, 260);
		}, 3200);
	}

	// Expose globally for other modules (release-actions, playlists, library, preview-player)
	window.mwToast = showToast;

	// Upgrade native share status announcements to toast as well
	document.addEventListener('click', function (event) {
		var btn = event.target.closest('[data-mw-share-button]');
		if (!btn) return;
		// The actual copy/share logic lives in release-actions.js; we just
		// observe its status element and mirror it as a toast for visibility.
		var status = btn.parentElement ? btn.parentElement.querySelector('[data-mw-share-status]') : null;
		if (!status) return;
		var observer = new MutationObserver(function () {
			var text = status.textContent && status.textContent.trim();
			if (text) {
				// Heuristic: success if contains copied keyword
				var isSuccess = /کپی|copy/i.test(text);
				showToast(text, isSuccess ? 'success' : 'info');
			}
		});
		observer.observe(status, { childList: true, characterData: true, subtree: true });
		// Disconnect after 4s to avoid leaks
		window.setTimeout(function () { observer.disconnect(); }, 4200);
	});

	/* ------------------------------------------------------------------ *
	 * Waveform for global player — 32 pseudo-random bars per track
	 * ------------------------------------------------------------------ */
	function buildWaveform(player) {
		if (!player) return;
		var wf = player.querySelector('.mw-global-player__waveform');
		if (!wf) {
			// Create waveform container if theme CSS expects it but PHP didn't render it yet
			var timeline = player.querySelector('.mw-global-player__timeline');
			if (!timeline) return;
			wf = document.createElement('div');
			wf.className = 'mw-global-player__waveform';
			wf.setAttribute('aria-hidden', 'true');
			timeline.parentNode.insertBefore(wf, timeline);
		}
		// Generate 32 bars with stable pseudo-random heights seeded by track title hash
		var title = (player.querySelector('.mw-global-player__title') || {}).textContent || '';
		var seed = 0;
		for (var i = 0; i < title.length; i++) seed = (seed * 31 + title.charCodeAt(i)) >>> 0;
		wf.innerHTML = '';
		for (var b = 0; b < 32; b++) {
			var bar = document.createElement('i');
			// Pseudo-random height between 0.35rem and 1.05rem
			seed = (seed * 1664525 + 1013904223) >>> 0;
			var h = 0.35 + (seed % 700) / 1000; // 0.35–1.05
			bar.style.setProperty('--h', h.toFixed(3) + 'rem');
			// Stagger heights visually: every 4th bar a bit taller
			if (b % 8 === 3) bar.style.setProperty('--h', Math.min(1.15, h + 0.15).toFixed(3) + 'rem');
			wf.appendChild(bar);
		}
		player.setAttribute('data-mw-waveform', 'on');
	}

	// Watch for global player activation and build waveform
	var globalPlayer = document.querySelector('.mw-global-player');
	if (globalPlayer) {
		buildWaveform(globalPlayer);
		var mo = new MutationObserver(function () {
			if (!globalPlayer.hidden && globalPlayer.getAttribute('data-mw-state')) {
				buildWaveform(globalPlayer);
			}
		});
		mo.observe(globalPlayer, { attributes: true, attributeFilter: ['data-mw-state', 'hidden'] });

		// Also rebuild when track title changes
		var titleEl = globalPlayer.querySelector('.mw-global-player__title');
		if (titleEl) {
			var titleMo = new MutationObserver(function () { buildWaveform(globalPlayer); });
			titleMo.observe(titleEl, { childList: true, characterData: true, subtree: true });
		}

		// Progress-driven active bars
		var progress = globalPlayer.querySelector('.mw-global-player__progress input[type="range"]');
		var audio = document.querySelector('audio[data-mw-player-audio]') || document.querySelector('.mw-global-player audio');
		function syncActiveBars() {
			if (!wf || !progress || !audio || !audio.duration) return;
			var pct = audio.currentTime / audio.duration;
			var bars = wf.querySelectorAll('i');
			var activeCount = Math.round(pct * bars.length);
			for (var i = 0; i < bars.length; i++) {
				if (i < activeCount) bars[i].classList.add('is-active');
				else bars[i].classList.remove('is-active');
			}
		}
		if (progress && audio) {
			audio.addEventListener('timeupdate', syncActiveBars);
			progress.addEventListener('input', syncActiveBars);
		}
	}

	/* ------------------------------------------------------------------ *
	 * Heart burst helper — re-trigger animation on every love toggle
	 * ------------------------------------------------------------------ */
	document.addEventListener('click', function (event) {
		var heart = event.target.closest('.mw-library-button--heart, .mw-library-button[data-mw-library-button]');
		if (!heart) return;
		// Let library.js toggle aria-pressed first, then trigger burst
		window.setTimeout(function () {
			if (heart.getAttribute('aria-pressed') === 'true') {
				heart.classList.remove('is-loved');
				// Force reflow
				void heart.offsetWidth;
				heart.classList.add('is-loved');
				var label = heart.getAttribute('data-mw-added-label') || heart.getAttribute('aria-label') || '';
				if (label) showToast(label, 'success');
			}
		}, 30);
	});

	/* ------------------------------------------------------------------ *
	 * Queue / Playlist actions — toast feedback when available
	 * ------------------------------------------------------------------ */
	document.addEventListener('click', function (event) {
		var queueBtn = event.target.closest('[data-mw-add-to-queue], .mw-add-to-queue__button');
		if (queueBtn) {
			window.setTimeout(function () { showToast('به صف پخش اضافه شد', 'success'); }, 200);
		}
		var playlistBtn = event.target.closest('[data-mw-playlist-add]');
		if (playlistBtn) {
			window.setTimeout(function () { showToast('به فهرست پخش اضافه شد', 'success'); }, 250);
		}
	});

	/* ------------------------------------------------------------------ *
	 * Reduce motion: disable waveform animation if user prefers
	 * ------------------------------------------------------------------ */
	if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
		var style = document.createElement('style');
		style.textContent = '.mw-waveform i, .mw-global-player__waveform i { animation: none !important; }';
		document.head.appendChild(style);
	}
})();
