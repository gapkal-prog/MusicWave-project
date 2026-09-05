( function () {
	'use strict';

	function filesFor( container ) {
		var raw = container.getAttribute( 'data-download-files' ) || '[]';
		try {
			var files = JSON.parse( raw );
			return Array.isArray( files ) ? files : [];
		} catch ( error ) {
			return [];
		}
	}

	function setQualities( container, fileKey ) {
		var files = filesFor( container );
		var file =
			files.filter( function ( candidate ) {
				return candidate && candidate.key === fileKey;
			} )[ 0 ] || files[ 0 ];
		var qualitySelect = container.querySelector( '.mw-download-quality' );
		var button = container.querySelector( '.mw-download-button' );

		if (
			! file ||
			! Array.isArray( file.qualities ) ||
			! qualitySelect ||
			! button
		) {
			return;
		}

		qualitySelect.innerHTML = '';
		file.qualities.forEach( function ( quality ) {
			if ( ! quality || ! quality.key ) {
				return;
			}
			var option = document.createElement( 'option' );
			option.value = quality.key;
			option.textContent = quality.label || quality.key;
			qualitySelect.appendChild( option );
		} );
		button.setAttribute(
			'data-download-quality',
			qualitySelect.value || ''
		);
	}

	// Innermost interactive scope a control belongs to: one panel row or one
	// inline track-row group. Falls back to the whole action container.
	function actionScope( element ) {
		return (
			element.closest( '.mw-download-file-row' ) ||
			element.closest( '.mw-download-inline' )
		);
	}

	function selectedQuality( button ) {
		var container = button.closest( '.mw-download-action' );
		var scope = actionScope( button ) || container;
		var qualitySelect = scope
			? scope.querySelector( '.mw-download-quality' )
			: null;

		return qualitySelect
			? qualitySelect.value
			: button.getAttribute( 'data-download-quality' ) || '';
	}

	function token( releaseId, quality, purpose ) {
		var path = '/music-wave/v1/releases/' + releaseId + '/download-token';
		var query = [];
		var settings = window.musicWaveDownload || {};
		if ( quality ) {
			query.push( 'quality=' + encodeURIComponent( quality ) );
		}
		if ( purpose ) {
			query.push( 'purpose=' + encodeURIComponent( purpose ) );
		}

		return window.wp.apiFetch( {
			path: path + ( query.length ? '?' + query.join( '&' ) : '' ),
			method: 'POST',
			headers: settings.restNonce
				? { 'X-WP-Nonce': settings.restNonce }
				: {},
		} );
	}

	function protectedUrl( route, releaseId, response ) {
		var url =
			window.musicWaveDownload.restUrl +
			'music-wave/v1/' +
			route +
			'/' +
			releaseId;
		url +=
			'?token=' +
			encodeURIComponent( response.token ) +
			'&nonce=' +
			encodeURIComponent( response.nonce );
		url += '&_wpnonce=' + encodeURIComponent( response.rest_nonce || '' );
		return url;
	}

	function setPlayButton( button, playing ) {
		var labels = window.musicWaveDownload || {};
		var icon = button.querySelector( '.mw-secure-play-button__icon' );
		var playLabel =
			button.getAttribute( 'data-play-label' ) ||
			labels.playLabel ||
			'پخش';
		var pauseLabel =
			button.getAttribute( 'data-pause-label' ) ||
			labels.pauseLabel ||
			'مکث';
		button.setAttribute( 'aria-pressed', playing ? 'true' : 'false' );
		// Icon-only affordance: the accessible name stays on aria-label.
		button.setAttribute( 'aria-label', playing ? pauseLabel : playLabel );
		if ( icon ) {
			icon.textContent = playing ? '❚❚' : '▶';
		}
	}

	function resetPlayButtons( except ) {
		document
			.querySelectorAll( '.mw-secure-play-button' )
			.forEach( function ( button ) {
				if ( button !== except ) {
					setPlayButton( button, false );
				}
			} );
	}

	function errorMessage( error, fallback ) {
		var labels = window.musicWaveDownload || {};
		if (
			error &&
			( error.status === 401 ||
				error.code === 'mw_authentication_required' )
		) {
			return labels.sessionError || fallback;
		}

		return error && error.message ? error.message : fallback;
	}

	document.addEventListener( 'change', function ( event ) {
		var fileSelect = event.target.closest( '.mw-download-file' );
		if ( fileSelect ) {
			var fileContainer = fileSelect.closest( '.mw-download-action' );
			if ( fileContainer ) {
				setQualities( fileContainer, fileSelect.value );
			}
			return;
		}

		var qualitySelect = event.target.closest(
			'.mw-download-file-row .mw-download-quality, .mw-download-inline .mw-download-quality'
		);
		if ( ! qualitySelect ) {
			return;
		}
		var scope = actionScope( qualitySelect );
		var playButton = scope
			? scope.querySelector( '.mw-secure-play-button' )
			: null;
		var container = qualitySelect.closest( '.mw-download-action' );
		var audio = container
			? container.querySelector( '.mw-secure-audio' )
			: null;
		var option = qualitySelect.options[ qualitySelect.selectedIndex ];
		if ( audio && ! audio.paused ) {
			audio.pause();
		}
		if ( playButton ) {
			playButton.disabled =
				! option || option.getAttribute( 'data-streamable' ) !== '1';
			setPlayButton( playButton, false );
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.mw-download-button' );
		if ( ! button || button.disabled ) {
			return;
		}

		var releaseId = parseInt(
			button.getAttribute( 'data-release-id' ),
			10
		);
		var container = button.closest( '.mw-download-action' );
		var status = container
			? container.querySelector( '.mw-download-status' )
			: null;
		var quality = selectedQuality( button );
		button.disabled = true;
		if ( status ) {
			status.textContent = '';
		}

		token( releaseId, quality, 'download' )
			.then( function ( response ) {
				window.location.assign(
					protectedUrl( 'downloads', releaseId, response )
				);
			} )
			.catch( function ( error ) {
				if ( status ) {
					status.textContent = errorMessage(
						error,
						window.musicWaveDownload.errorMessage
					);
				}
			} )
			.finally( function () {
				button.disabled = false;
			} );
	} );

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.mw-secure-play-button' );
		if ( ! button || button.disabled ) {
			return;
		}
		event.preventDefault();

		var container = button.closest( '.mw-download-action' );
		var audio = container
			? container.querySelector( '.mw-secure-audio' )
			: null;
		var status = container
			? container.querySelector( '.mw-download-status' )
			: null;
		var releaseId = parseInt(
			button.getAttribute( 'data-release-id' ),
			10
		);
		var quality = selectedQuality( button );
		if ( ! container || ! audio || ! status || ! releaseId || ! quality ) {
			return;
		}
		if (
			audio.getAttribute( 'data-active-quality' ) === quality &&
			! audio.paused
		) {
			audio.pause();
			return;
		}
		if (
			audio.getAttribute( 'data-active-quality' ) === quality &&
			audio.src
		) {
			audio.play().catch( function () {
				status.textContent = window.musicWaveDownload.streamError;
			} );
			return;
		}

		document
			.querySelectorAll( '.mw-secure-audio' )
			.forEach( function ( candidate ) {
				if ( candidate !== audio ) {
					candidate.pause();
				}
			} );
		button.disabled = true;
		status.textContent = '';
		token( releaseId, quality, 'stream' )
			.then( function ( response ) {
				audio.src = protectedUrl( 'streams', releaseId, response );
				audio.setAttribute( 'data-active-quality', quality );
				audio.setAttribute( 'data-active-button', '' );
				resetPlayButtons( button );
				return audio.play();
			} )
			.catch( function ( error ) {
				status.textContent = errorMessage(
					error,
					window.musicWaveDownload.streamError
				);
			} )
			.finally( function () {
				button.disabled = false;
			} );
	} );

	document.addEventListener(
		'play',
		function ( event ) {
			if ( ! event.target.matches( '.mw-secure-audio' ) ) {
				return;
			}
			var container = event.target.closest( '.mw-download-action' );
			var quality = event.target.getAttribute( 'data-active-quality' );
			var buttons = container
				? container.querySelectorAll( '.mw-secure-play-button' )
				: [];
			buttons.forEach( function ( button ) {
				setPlayButton( button, selectedQuality( button ) === quality );
			} );
		},
		true
	);

	document.addEventListener(
		'pause',
		function ( event ) {
			if ( ! event.target.matches( '.mw-secure-audio' ) ) {
				return;
			}
			var container = event.target.closest( '.mw-download-action' );
			if ( container ) {
				container
					.querySelectorAll( '.mw-secure-play-button' )
					.forEach( function ( button ) {
						setPlayButton( button, false );
					} );
			}
		},
		true
	);
} )();
