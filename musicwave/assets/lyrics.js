(function () {
	'use strict';

	var SCALE_KEY = 'musicwave-lyrics-scale';
	var MIN_SCALE = 0.85;
	var MAX_SCALE = 1.45;
	var STEP = 0.1;
	var bound = false;
	var frame = 0;
	var lastActive = {};

	function playerEl() {
		return document.querySelector(
			'[data-mw-preview-player] audio, [data-mw-global-player] audio, .mw-global-player audio, audio[data-mw-player]'
		);
	}

	function currentTime(offsetMs) {
		var player = playerEl();
		if (!player || Number.isNaN(player.currentTime)) {
			return 0;
		}
		return player.currentTime + offsetMs / 1000;
	}

	function isPlaying() {
		var player = playerEl();
		return !!(player && !player.paused && !player.ended);
	}

	function seekTo(seconds) {
		var player = playerEl();
		if (!player) {
			return;
		}
		try {
			player.currentTime = Math.max(0, seconds);
			if (player.paused) {
				var play = player.play();
				if (play && typeof play.catch === 'function') {
					play.catch(function () {});
				}
			}
		} catch (error) {}
	}

	function savedScale() {
		try {
			var raw = parseFloat(window.localStorage.getItem(SCALE_KEY) || '1');
			if (Number.isNaN(raw)) {
				return 1;
			}
			return Math.min(MAX_SCALE, Math.max(MIN_SCALE, raw));
		} catch (error) {
			return 1;
		}
	}

	function applyScale(root, value) {
		root.style.setProperty('--mw-lyrics-scale', String(value));
		try {
			window.localStorage.setItem(SCALE_KEY, String(value));
		} catch (error) {}
	}

	function lineOffset(stage, line) {
		return line.getBoundingClientRect().top - stage.getBoundingClientRect().top + stage.scrollTop;
	}

	function isLineInView(stage, line) {
		var stageBox = stage.getBoundingClientRect();
		var lineBox = line.getBoundingClientRect();
		var pad = Math.max(48, stage.clientHeight * 0.22);
		return lineBox.top >= stageBox.top + pad && lineBox.bottom <= stageBox.bottom - pad;
	}

	function scrollLine(root, line, force) {
		if ('0' === root.getAttribute('data-mw-autoscroll')) {
			return;
		}
		var stage = root.querySelector('[data-mw-lyrics-stage]');
		if (!stage || !line || root.classList.contains('mw-lyrics--plain')) {
			return;
		}
		if (!force && isLineInView(stage, line)) {
			return;
		}
		var top = lineOffset(stage, line) - stage.clientHeight / 2 + line.offsetHeight / 2;
		var max = Math.max(0, stage.scrollHeight - stage.clientHeight);
		var next = Math.min(max, Math.max(0, top));
		if (Math.abs(stage.scrollTop - next) < 2) {
			return;
		}
		root.setAttribute('data-mw-lyrics-scrolling', '1');
		if (typeof stage.scrollTo === 'function') {
			stage.scrollTo({ top: next, behavior: force ? 'smooth' : 'auto' });
		} else {
			stage.scrollTop = next;
		}
		window.setTimeout(function () {
			root.removeAttribute('data-mw-lyrics-scrolling');
		}, force ? 280 : 80);
	}

	function sync(root) {
		var lines = root.querySelectorAll('[data-mw-lyric-time]');
		if (!lines.length) {
			return;
		}
		var offset = parseFloat(root.getAttribute('data-mw-lyric-offset') || '0');
		var time = currentTime(Number.isNaN(offset) ? 0 : offset);
		var playing = isPlaying();
		var active = -1;
		for (var i = 0; i < lines.length; i += 1) {
			var stamp = parseFloat(lines[i].getAttribute('data-mw-lyric-time') || '-1');
			if (stamp >= 0 && stamp <= time) {
				active = i;
			}
		}
		root.classList.toggle('is-playing', playing);
		root.classList.toggle('is-paused', !playing && active >= 0);
		root.classList.toggle('is-idle', active < 0);
		for (var j = 0; j < lines.length; j += 1) {
			var on = j === active;
			var dist = active < 0 ? 0 : Math.abs(j - active);
			lines[j].classList.toggle('is-active', on);
			lines[j].classList.toggle('is-passed', j < active);
			lines[j].classList.toggle('is-upcoming', j > active && dist === 1);
			lines[j].style.setProperty('--mw-lyric-distance', String(Math.min(6, dist)));
			if (on) {
				lines[j].setAttribute('aria-current', 'true');
			} else {
				lines[j].removeAttribute('aria-current');
			}
		}
		var key = root.getAttribute('data-release-id') || 'lyrics';
		var changed = active !== lastActive[key];
		lastActive[key] = active;
		if (active >= 0 && (playing || changed)) {
			scrollLine(root, lines[active], changed);
		}
	}

	function allRoots() {
		return document.querySelectorAll('[data-mw-lyrics]');
	}

	function tick() {
		var playing = isPlaying();
		allRoots().forEach(sync);
		if (playing) {
			frame = window.requestAnimationFrame(tick);
		} else {
			frame = 0;
		}
	}

	function ensureTick() {
		if (!frame) {
			frame = window.requestAnimationFrame(tick);
		}
	}

	function onStageInteract(root) {
		var autoscroll = root.querySelector('[data-mw-lyrics-autoscroll]');
		root.setAttribute('data-mw-autoscroll', '0');
		if (autoscroll) {
			autoscroll.classList.remove('is-active');
			autoscroll.setAttribute('aria-pressed', 'false');
		}
	}

	function bindRoot(root) {
		if (root.getAttribute('data-mw-lyrics-bound')) {
			return;
		}
		root.setAttribute('data-mw-lyrics-bound', '1');
		applyScale(root, savedScale());
		root.setAttribute('data-mw-autoscroll', '1');

		root.addEventListener('click', function (event) {
			var scaleBtn = event.target.closest('[data-mw-lyrics-scale]');
			if (scaleBtn) {
				var dir = parseFloat(scaleBtn.getAttribute('data-mw-lyrics-scale') || '0');
				var next = Math.min(MAX_SCALE, Math.max(MIN_SCALE, savedScale() + dir * STEP));
				applyScale(root, Math.round(next * 100) / 100);
				return;
			}
			var autoBtn = event.target.closest('[data-mw-lyrics-autoscroll]');
			if (autoBtn) {
				var on = '0' === root.getAttribute('data-mw-autoscroll');
				root.setAttribute('data-mw-autoscroll', on ? '1' : '0');
				autoBtn.classList.toggle('is-active', on);
				autoBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
				if (on) {
					sync(root);
					var active = root.querySelector('.mw-lyrics__line.is-active');
					if (active) {
						scrollLine(root, active);
					}
				}
				return;
			}
			var modeBtn = event.target.closest('[data-mw-lyrics-mode]');
			if (modeBtn) {
				var mode = modeBtn.getAttribute('data-mw-lyrics-mode') || 'spotlight';
				root.classList.remove('mw-lyrics--spotlight', 'mw-lyrics--plain', 'mw-lyrics--karaoke');
				root.classList.add('mw-lyrics--' + mode);
				root.querySelectorAll('[data-mw-lyrics-mode]').forEach(function (item) {
					item.classList.toggle('is-active', item === modeBtn);
				});
				var current = root.querySelector('.mw-lyrics__line.is-active');
				if (current) {
					scrollLine(root, current, true);
				}
				return;
			}
			var line = event.target.closest('[data-mw-lyric-time]');
			if (!line || !root.contains(line)) {
				return;
			}
			var stamp = parseFloat(line.getAttribute('data-mw-lyric-time') || '-1');
			if (stamp < 0) {
				return;
			}
			var offset = parseFloat(root.getAttribute('data-mw-lyric-offset') || '0');
			root.setAttribute('data-mw-autoscroll', '1');
			var resume = root.querySelector('[data-mw-lyrics-autoscroll]');
			if (resume) {
				resume.classList.add('is-active');
				resume.setAttribute('aria-pressed', 'true');
			}
			seekTo(stamp - (Number.isNaN(offset) ? 0 : offset) / 1000);
			ensureTick();
		});

		var stage = root.querySelector('[data-mw-lyrics-stage]');
		if (stage) {
			var lock = function () {
				if (root.getAttribute('data-mw-lyrics-scrolling')) {
					return;
				}
				onStageInteract(root);
			};
			stage.addEventListener('wheel', lock, { passive: true });
			stage.addEventListener('touchmove', lock, { passive: true });
		}
	}

	function bind() {
		allRoots().forEach(bindRoot);
		ensureTick();
		if (bound) {
			return;
		}
		bound = true;
		document.addEventListener('timeupdate', function () {
			allRoots().forEach(sync);
		}, true);
		document.addEventListener('play', function () {
			lastActive = {};
			ensureTick();
		}, true);
		document.addEventListener('pause', function () {
			allRoots().forEach(sync);
		}, true);
		document.addEventListener('seeking', function () {
			lastActive = {};
			allRoots().forEach(sync);
		}, true);
		document.addEventListener('visibilitychange', function () {
			if (!document.hidden) {
				ensureTick();
			}
		});
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', bind);
	} else {
		bind();
	}
	document.addEventListener('mw-page-rendered', bind);
})();
