/**
 * Dashboard tab controller: reveals account sections below the quick links.
 *
 * @package ManaCore\MusicWave\Core
 */
(function () {
	'use strict';

	function tabButton(key) {
		return document.querySelector('[data-mw-dashboard-tab="' + key + '"]');
	}

	function panelFor(tab) {
		var key = tab.getAttribute('data-mw-dashboard-tab');
		return key ? document.querySelector('[data-mw-dashboard-panel="' + key + '"]') : null;
	}

	function setState(tab, open) {
		var panel = panelFor(tab);
		if (!panel) {
			return;
		}

		tab.classList.toggle('is-active', open);
		tab.setAttribute('aria-expanded', open ? 'true' : 'false');
		panel.hidden = !open;
		panel.classList.toggle('is-open', open);
	}

	function closeAll() {
		document.querySelectorAll('[data-mw-dashboard-tab]').forEach(function (tab) {
			setState(tab, false);
		});
	}

	function updateHash(openKey) {
		if (!window.history || !window.history.replaceState) {
			return;
		}

		var url = window.location.href.split('#')[0];
		window.history.replaceState(null, '', openKey ? url + '#mw-' + openKey : url);
	}

	function openOnly(tab) {
		var key = tab.getAttribute('data-mw-dashboard-tab');
		var wasOpen = tab.getAttribute('aria-expanded') === 'true';

		closeAll();
		if (!wasOpen) {
			setState(tab, true);
			updateHash(key);
		} else {
			updateHash('');
		}
	}

	document.addEventListener('click', function (event) {
		var tab = event.target.closest('[data-mw-dashboard-tab]');
		if (!tab || 'BUTTON' !== tab.tagName) {
			return;
		}
		event.preventDefault();
		openOnly(tab);
	});

	// Deep-link support: open the panel referenced by the URL hash.
	var hash = (window.location.hash || '').replace('#mw-', '');
	if (hash) {
		var linked = tabButton(hash);
		if (linked) {
			setState(linked, true);
		}
	}
}());
