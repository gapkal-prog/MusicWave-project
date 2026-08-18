/**
 * Personal music library controller: toggle buttons and removal actions.
 *
 * @package ManaCore\MusicWave\Core
 */
(function () {
	'use strict';

	if (!window.wp || !window.wp.apiFetch) {
		return;
	}

	function settings() {
		return window.musicWaveLibrary || {};
	}

	function request(method, body) {
		var config = settings();
		var options = {
			path: '/music-wave/v1/library/items',
			method: method,
			headers: config.restNonce ? { 'X-WP-Nonce': config.restNonce } : {}
		};
		if (body) {
			options.data = body;
		}

		return window.wp.apiFetch(options);
	}

	function errorMessage(error) {
		var config = settings();
		if (error && (error.status === 401 || error.code === 'mw_authentication_required')) {
			return config.sessionError || config.errorMessage;
		}

		return error && error.message ? error.message : config.errorMessage;
	}

	function setButtonState(button, inLibrary) {
		var label = button.querySelector('.mw-library-button__label');
		var icon = button.querySelector('.mw-library-button__icon');
		var next = inLibrary
			? button.getAttribute('data-mw-library-label-added')
			: button.getAttribute('data-mw-library-label-add');

		button.setAttribute('data-mw-library-state', inLibrary ? 'in' : 'out');
		button.setAttribute('aria-pressed', inLibrary ? 'true' : 'false');
		if (label && next) {
			label.textContent = next;
		}
		if (icon) {
			icon.innerHTML = inLibrary ? '&#10003;' : '+';
		}
	}

	function syncButtons(type, id, inLibrary) {
		document.querySelectorAll('.mw-library-button[data-mw-library-id="' + id + '"][data-mw-library-type="' + type + '"]').forEach(function (button) {
			setButtonState(button, inLibrary);
		});
	}

	function updateCounts(counts) {
		if (!counts || typeof counts !== 'object') {
			return;
		}

		Object.keys(counts).forEach(function (key) {
			document.querySelectorAll('[data-mw-library-count="' + key + '"]').forEach(function (element) {
				element.textContent = String(counts[key]);
			});
		});
	}

	function showStatus(button, message) {
		var container = button.closest('.mw-release-meta__actions') || button.closest('.mw-artist-profile__library') || button.parentNode;
		var status = container ? container.querySelector('.mw-library-button__status') : null;
		if (status) {
			status.textContent = message;
			window.setTimeout(function () {
				status.textContent = '';
			}, 4000);
		}
	}

	function removeItemCard(type, id) {
		var card = document.querySelector('[data-mw-library-item="' + type + '-' + id + '"]');
		if (!card) {
			return;
		}

		card.classList.add('is-removing');
		window.setTimeout(function () {
			card.remove();
			var list = document.querySelector('[data-mw-library-items]');
			if (list && !list.querySelector('[data-mw-library-item]')) {
				var empty = document.querySelector('[data-mw-library-empty]');
				if (empty) {
					empty.hidden = false;
				}
				list.remove();
			}
		}, 180);
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest('.mw-library-button[data-mw-library-id]');
		if (!button || button.disabled) {
			return;
		}
		event.preventDefault();

		var type = button.getAttribute('data-mw-library-type');
		var id = parseInt(button.getAttribute('data-mw-library-id'), 10);
		if (!type || !id) {
			return;
		}

		var inLibrary = button.getAttribute('data-mw-library-state') === 'in';
		button.disabled = true;

		request(inLibrary ? 'DELETE' : 'POST', { type: type, id: id })
			.then(function (response) {
				var next = !inLibrary;
				if (response && typeof response.state === 'string') {
					next = response.state === 'in';
				}
				syncButtons(type, id, next);
				if (response && response.counts) {
					updateCounts(response.counts);
				}
			})
			.catch(function (error) {
				showStatus(button, errorMessage(error));
			})
			.finally(function () {
				button.disabled = false;
			});
	});

	document.addEventListener('click', function (event) {
		var removeButton = event.target.closest('.mw-library-remove');
		if (!removeButton || removeButton.disabled) {
			return;
		}
		event.preventDefault();

		var type = removeButton.getAttribute('data-mw-library-type');
		var id = parseInt(removeButton.getAttribute('data-mw-library-id'), 10);
		if (!type || !id) {
			return;
		}

		removeButton.disabled = true;
		request('DELETE', { type: type, id: id })
			.then(function (response) {
				removeItemCard(type, id);
				syncButtons(type, id, false);
				if (response && response.counts) {
					updateCounts(response.counts);
				}
			})
			.catch(function (error) {
				removeButton.disabled = false;
				window.alert(errorMessage(error));
			});
	});
}());
