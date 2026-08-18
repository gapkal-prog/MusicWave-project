(function () {
	'use strict';

	var storageKey = 'musicwave-theme';
	var themes = ['system', 'light', 'dark'];

	function savedTheme() {
		try {
			var saved = window.localStorage.getItem(storageKey);
			return themes.indexOf(saved) !== -1 ? saved : 'system';
		} catch (error) {
			return 'system';
		}
	}

	function applyTheme(theme) {
		if ('system' === theme) {
			document.documentElement.removeAttribute('data-mw-theme');
			return;
		}

		document.documentElement.setAttribute('data-mw-theme', theme);
	}

	function storeTheme(theme) {
		try {
			if ('system' === theme) {
				window.localStorage.removeItem(storageKey);
			} else {
				window.localStorage.setItem(storageKey, theme);
			}
		} catch (error) {}
	}

	function updateButton(button, theme) {
		var labels = window.musicwaveThemePreference && window.musicwaveThemePreference.labels ? window.musicwaveThemePreference.labels : {};
		button.setAttribute('data-mw-theme-value', theme);
		button.setAttribute('aria-label', labels[theme] || theme);
		button.setAttribute('title', labels[theme] || theme);
		button.textContent = 'system' === theme ? '◐' : ('light' === theme ? '☼' : '◒');
	}

	function nextTheme(theme) {
		return themes[(themes.indexOf(theme) + 1) % themes.length];
	}

	function initialize(button) {
		var theme = savedTheme();
		applyTheme(theme);
		updateButton(button, theme);
		button.addEventListener('click', function () {
			theme = nextTheme(theme);
			storeTheme(theme);
			applyTheme(theme);
			updateButton(button, theme);
		});
	}

	function initializeAll() {
		var button = document.querySelector('.mw-theme-toggle');
		if (button && !button.getAttribute('data-mw-theme-ready')) {
			button.setAttribute('data-mw-theme-ready', '1');
			initialize(button);
		}
	}

	// Swapped-in pages from the persistent player navigation reuse the same
	// initialization path through the `mw-page-rendered` event.
	document.addEventListener('DOMContentLoaded', initializeAll);
	document.addEventListener('mw-page-rendered', initializeAll);
}());
