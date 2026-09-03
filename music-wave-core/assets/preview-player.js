( function () {
	'use strict';

	var selector = '[data-mw-preview-player]';
	var settings = window.musicWavePreviewPlayer || {};
	var labels = settings.labels || {};
	var playerController = null;

	function trackFromButton( button ) {
		return {
			releaseId: 0,
			rootId: 0,
			url: button.getAttribute( 'data-preview-url' ) || '',
			title: button.getAttribute( 'data-preview-title' ) || '',
			artist: button.getAttribute( 'data-preview-artist' ) || '',
			image: button.getAttribute( 'data-preview-image' ) || '',
			link: button.getAttribute( 'data-preview-link' ) || '',
			previewLimit:
				parseInt( button.getAttribute( 'data-preview-limit' ), 10 ) ||
				30,
			full: false,
			quality: '',
			limited: false,
		};
	}

	function trackFromApi( item, meta ) {
		var full = Boolean( item.full && item.quality );
		return {
			releaseId: parseInt( item.releaseId, 10 ) || 0,
			rootId: parseInt( item.rootId, 10 ) || 0,
			url: full ? '' : item.previewUrl || '',
			title: item.title || '',
			artist: item.artist || '',
			image:
				item.image ||
				( meta && meta.release ? meta.release.image : '' ),
			link: item.link || '',
			previewLimit: full ? 0 : parseInt( item.previewLimit, 10 ) || 30,
			full,
			quality: full ? item.quality : '',
			limited: false,
		};
	}

	function formatTime( seconds ) {
		seconds = Math.max( 0, Math.floor( seconds || 0 ) );
		return (
			Math.floor( seconds / 60 ) +
			':' +
			String( seconds % 60 ).padStart( 2, '0' )
		);
	}

	function restHeaders() {
		return settings.restNonce ? { 'X-WP-Nonce': settings.restNonce } : {};
	}

	function fetchReleaseQueue( releaseId ) {
		if ( ! window.wp || ! window.wp.apiFetch ) {
			return Promise.reject( { message: labels.error } );
		}
		return window.wp.apiFetch( {
			path: '/music-wave/v1/releases/' + releaseId + '/playback-queue',
			method: 'GET',
			headers: restHeaders(),
		} );
	}

	function fetchPlaylistQueue( playlistId, share ) {
		if ( ! window.wp || ! window.wp.apiFetch ) {
			return Promise.reject( { message: labels.error } );
		}
		var path = '/music-wave/v1/playlists/' + playlistId + '/playback-queue';
		if ( share ) {
			path += '?share=' + encodeURIComponent( share );
		} else {
			try {
				var currentUrl = new URL( window.location.href );
				var urlShare = currentUrl.searchParams.get( 'mw-share' );
				if ( urlShare ) {
					path += '?share=' + encodeURIComponent( urlShare );
				}
			} catch ( e ) {}
		}
		return window.wp.apiFetch( {
			path,
			method: 'GET',
			headers: restHeaders(),
		} );
	}

	function fetchStreamToken( track ) {
		return window.wp.apiFetch( {
			path:
				'/music-wave/v1/releases/' +
				track.releaseId +
				'/download-token?quality=' +
				encodeURIComponent( track.quality ) +
				'&purpose=stream',
			method: 'POST',
			headers: restHeaders(),
		} );
	}

	function streamUrl( track, response ) {
		var url = settings.restUrl + 'music-wave/v1/streams/' + track.releaseId;
		url +=
			'?token=' +
			encodeURIComponent( response.token ) +
			'&nonce=' +
			encodeURIComponent( response.nonce );
		url += '&_wpnonce=' + encodeURIComponent( response.rest_nonce || '' );
		return url;
	}

	function errorMessage( error, fallback ) {
		if (
			error &&
			( error.status === 401 ||
				error.code === 'mw_authentication_required' )
		) {
			return labels.sessionError || fallback;
		}
		return error && error.message ? error.message : fallback;
	}

	function initialize( player ) {
		if ( player.getAttribute( 'data-mw-ready' ) ) {
			return;
		}
		player.setAttribute( 'data-mw-ready', '1' );
		var audio = player.querySelector( 'audio' );
		var title = player.querySelector( '.mw-global-player__title' );
		var artist = player.querySelector( '.mw-global-player__artist' );
		var art = player.querySelector( '.mw-global-player__art' );
		var toggle = player.querySelector( '.mw-global-player__toggle' );
		var previous = player.querySelector( '.mw-global-player__previous' );
		var next = player.querySelector( '.mw-global-player__next' );
		var close = player.querySelector( '.mw-global-player__close' );
		var progress = player.querySelector(
			'.mw-global-player__progress input'
		);
		var time = player.querySelector( '.mw-global-player__time' );
		var volume = player.querySelector( 'input.mw-global-player__volume' );
		var queueToggle = player.querySelector(
			'.mw-global-player__queue-toggle'
		);
		var queuePanel = player.querySelector( '[data-mw-queue]' );
		var queueList = player.querySelector( '.mw-global-player__queue-list' );
		var queueClose = player.querySelector(
			'.mw-global-player__queue-close'
		);
		var notice = player.querySelector( '.mw-global-player__notice' );
		var noticeMessage = player.querySelector(
			'.mw-global-player__notice-message'
		);
		var noticeCta = player.querySelector( '.mw-global-player__notice-cta' );
		var noticeLogin = player.querySelector(
			'.mw-global-player__notice-login'
		);
		var noticeClose = player.querySelector(
			'.mw-global-player__notice-close'
		);
		var queue = [];
		var queueMeta = null;
		var current = -1;
		var busy = false;

		function pageQueue() {
			var tracks = [];
			document
				.querySelectorAll( '.mw-preview-button[data-preview-url]' )
				.forEach( function ( button ) {
					var track = trackFromButton( button );
					if (
						track.url &&
						! tracks.some( function ( item ) {
							return item.url === track.url;
						} )
					) {
						tracks.push( track );
					}
				} );
			return tracks;
		}

		function currentTrack() {
			return current >= 0 && queue[ current ] ? queue[ current ] : null;
		}

		function playGlyph() {
			return audio.paused ? '▶' : '❚❚';
		}

		function setLoading( loading ) {
			busy = loading;
			toggle.textContent = loading ? '…' : playGlyph();
		}

		function updateButtons() {
			var track = currentTrack();
			var playing = Boolean( track ) && ! audio.paused;
			document
				.querySelectorAll( '.mw-preview-button' )
				.forEach( function ( button ) {
					var active =
						playing &&
						track.url !== '' &&
						button.getAttribute( 'data-preview-url' ) === track.url;
					button.setAttribute(
						'aria-pressed',
						active ? 'true' : 'false'
					);
					var icon = button.querySelector(
						'.mw-preview-button__icon'
					);
					if ( icon ) {
						icon.textContent = active ? '❚❚' : '▶';
					}
				} );
			document
				.querySelectorAll( '.mw-card-play[data-mw-release-id]' )
				.forEach( function ( button ) {
					var releaseId =
						parseInt(
							button.getAttribute( 'data-mw-release-id' ),
							10
						) || 0;
					var active =
						playing &&
						releaseId > 0 &&
						( track.rootId === releaseId ||
							track.releaseId === releaseId );
					button.setAttribute(
						'aria-pressed',
						active ? 'true' : 'false'
					);
					var icon = button.querySelector( '.mw-card-play__icon' );
					if ( icon ) {
						icon.textContent = active ? '❚❚' : '▶';
					}
				} );
			// Playlist Play all buttons: active when current queue is that playlist
			var playlistId =
				queueMeta && queueMeta.playlist
					? parseInt( queueMeta.playlist.id, 10 )
					: 0;
			document
				.querySelectorAll( '[data-mw-playlist-play]' )
				.forEach( function ( button ) {
					var btnId =
						parseInt(
							button.getAttribute( 'data-playlist-id' ) ||
								button.getAttribute( 'data-mw-playlist-id' ),
							10
						) || 0;
					var active =
						playing && playlistId > 0 && btnId === playlistId;
					button.setAttribute(
						'aria-pressed',
						active ? 'true' : 'false'
					);
					var icon = button.querySelector(
						'.mw-playlists__play-icon'
					);
					if ( icon ) {
						icon.textContent = active ? '❚❚' : '▶';
					}
				} );
		}

		function renderQueuePanel() {
			if ( ! queueToggle || ! queuePanel || ! queueList ) {
				return;
			}
			queueToggle.hidden = queue.length < 2;
			queueList.replaceChildren();
			queue.forEach( function ( track, index ) {
				var item = document.createElement( 'li' );
				var button = document.createElement( 'button' );
				button.type = 'button';
				button.className = 'mw-global-player__queue-item';
				if ( index === current ) {
					button.setAttribute( 'aria-current', 'true' );
				}
				var position = document.createElement( 'span' );
				position.className = 'mw-global-player__queue-position';
				position.textContent = String( index + 1 );
				var name = document.createElement( 'span' );
				name.className = 'mw-global-player__queue-title';
				name.textContent = track.title;
				button.appendChild( position );
				button.appendChild( name );
				if ( ! track.full ) {
					var badge = document.createElement( 'span' );
					badge.className = 'mw-global-player__queue-badge';
					badge.textContent = labels.previewBadge || 'پیش‌نمایش';
					button.appendChild( badge );
				}
				button.addEventListener( 'click', function () {
					select( index, true );
				} );
				item.appendChild( button );
				queueList.appendChild( item );
			} );
		}

		function setQueueOpen( open ) {
			if ( ! queuePanel || ! queueToggle ) {
				return;
			}
			queuePanel.hidden = ! open;
			queueToggle.setAttribute(
				'aria-expanded',
				open ? 'true' : 'false'
			);
		}

		function hideNotice() {
			if ( notice ) {
				notice.hidden = true;
			}
		}

		function showNotice( message, upsell ) {
			if ( ! notice || ! noticeMessage ) {
				return;
			}
			noticeMessage.textContent = message;
			if ( noticeCta ) {
				var hasCta = Boolean(
					upsell && upsell.ctaUrl && upsell.ctaLabel
				);
				noticeCta.hidden = ! hasCta;
				if ( hasCta ) {
					noticeCta.href = upsell.ctaUrl;
					noticeCta.textContent = upsell.ctaLabel;
				}
			}
			if ( noticeLogin ) {
				var hasLogin = Boolean(
					upsell && upsell.loginUrl && upsell.loginLabel
				);
				noticeLogin.hidden = ! hasLogin;
				if ( hasLogin ) {
					noticeLogin.href = upsell.loginUrl;
					noticeLogin.textContent = upsell.loginLabel;
				}
			}
			notice.hidden = false;
		}

		function announcePreviewMode() {
			if ( ! queueMeta || ! queueMeta.previewOnly ) {
				return;
			}
			var upsell = queueMeta.upsell || {};
			var message = upsell.previewNote || labels.previewNote || '';
			if ( upsell.message ) {
				message = message
					? message + ' ' + upsell.message
					: upsell.message;
			}
			if ( message ) {
				showNotice( message, upsell );
			}
		}

		function mediaSession( track ) {
			if (
				! ( 'mediaSession' in navigator ) ||
				! window.MediaMetadata ||
				! track
			) {
				return;
			}
			navigator.mediaSession.metadata = new MediaMetadata( {
				title: track.title,
				artist: track.artist,
				artwork: track.image ? [ { src: track.image } ] : [],
			} );
			var handlers = {
				play() {
					audio.play();
				},
				pause() {
					audio.pause();
				},
				previoustrack() {
					select( current - 1, true );
				},
				nexttrack() {
					select( current + 1, true );
				},
				seekto( details ) {
					if (
						audio.duration &&
						'number' === typeof details.seekTime
					) {
						audio.currentTime = details.seekTime;
					}
				},
				seekbackward() {
					seekBy( -10 );
				},
				seekforward() {
					seekBy( 10 );
				},
			};
			Object.keys( handlers ).forEach( function ( action ) {
				try {
					navigator.mediaSession.setActionHandler(
						action,
						handlers[ action ]
					);
				} catch ( e ) {}
			} );
		}

		function seekBy( delta ) {
			if ( ! audio.duration ) {
				return;
			}
			audio.currentTime = Math.min(
				audio.duration,
				Math.max( 0, audio.currentTime + delta )
			);
		}

		function render() {
			var track = currentTrack();
			if ( ! track ) {
				return;
			}
			title.textContent = track.title;
			artist.textContent = track.artist;
			if ( track.image ) {
				art.src = track.image;
				art.hidden = false;
			} else {
				art.removeAttribute( 'src' );
				art.hidden = true;
			}
			// Now-playing signal for the theme equalizer (CSS-only consumer).
			player.setAttribute(
				'data-mw-state',
				audio.paused ? 'paused' : 'playing'
			);
			player.hidden = false;
			if ( ! busy ) {
				toggle.textContent = playGlyph();
			}
			toggle.setAttribute(
				'aria-label',
				audio.paused
					? labels.play || 'پخش پیش‌نمایش'
					: labels.pause || 'توقف پیش‌نمایش'
			);
			previous.disabled = current <= 0;
			next.disabled = current >= queue.length - 1;
			updateButtons();
			renderQueuePanel();
		}

		function loadSource( track ) {
			if ( ! track.full ) {
				return Promise.resolve( track.url );
			}
			return fetchStreamToken( track ).then( function ( response ) {
				return streamUrl( track, response );
			} );
		}

		function select( index, autoplay ) {
			if ( index < 0 || index >= queue.length ) {
				return;
			}
			current = index;
			var track = queue[ current ];
			audio.pause();
			try {
				audio.currentTime = 0;
			} catch ( e ) {}
			hideNotice();
			setLoading( true );
			render();
			persistState( true );
			loadSource( track )
				.then( function ( url ) {
					if ( currentTrack() !== track ) {
						return;
					}
					setLoading( false );
					audio.src = url;
					audio.load();
					mediaSession( track );
					if ( autoplay ) {
						audio.play().catch( function () {
							render();
						} );
					}
				} )
				.catch( function ( error ) {
					if ( currentTrack() !== track ) {
						return;
					}
					setLoading( false );
					render();
					showNotice(
						errorMessage( error, labels.streamError ),
						queueMeta ? queueMeta.upsell : null
					);
				} );
		}

		function startPreviewButton( button ) {
			var clicked = trackFromButton( button );
			var tracks = pageQueue();
			var selected = tracks.findIndex( function ( track ) {
				return track.url === clicked.url;
			} );
			var sameQueue =
				queue.length > 0 &&
				queue.every( function ( track ) {
					return ! track.full && track.url;
				} );
			queue = tracks.length ? tracks : [ clicked ];
			queueMeta = null;
			if ( selected < 0 ) {
				queue.push( clicked );
				selected = queue.length - 1;
			}
			if (
				current >= 0 &&
				queue[ current ] &&
				queue[ current ].url === clicked.url &&
				sameQueue
			) {
				if ( audio.paused ) {
					audio.play().catch( function () {} );
				} else {
					audio.pause();
				}
				render();
				return;
			}
			select( selected, true );
		}

		function releasePlaying( releaseId ) {
			var track = currentTrack();
			return (
				Boolean( track ) &&
				( track.rootId === releaseId || track.releaseId === releaseId )
			);
		}

		function startRelease( button, forcedReleaseId ) {
			var releaseId =
				forcedReleaseId ||
				parseInt( button.getAttribute( 'data-mw-release-id' ), 10 ) ||
				0;
			if ( releaseId < 1 ) {
				return;
			}
			if ( releasePlaying( releaseId ) ) {
				if ( audio.paused ) {
					audio.play().catch( function () {} );
				} else {
					audio.pause();
				}
				return;
			}
			button.classList.add( 'is-loading' );
			fetchReleaseQueue( releaseId )
				.then( function ( data ) {
					var tracks = Array.isArray( data.tracks )
						? data.tracks
						: [];
					queue = tracks
						.map( function ( item ) {
							return trackFromApi( item, data );
						} )
						.filter( function ( track ) {
							return track.full || track.url;
						} );
					queueMeta = data;
					if ( ! queue.length ) {
						throw { message: labels.error };
					}
					select( 0, true );
					announcePreviewMode();
				} )
				.catch( function ( error ) {
					showNotice( errorMessage( error, labels.error ), null );
				} )
				.finally( function () {
					button.classList.remove( 'is-loading' );
				} );
		}

		function startPlaylist( button ) {
			var playlistId =
				parseInt(
					button.getAttribute( 'data-playlist-id' ) ||
						button.getAttribute( 'data-mw-playlist-id' ),
					10
				) || 0;
			if ( playlistId < 1 ) {
				return;
			}
			var share =
				button.getAttribute( 'data-mw-share' ) ||
				button.getAttribute( 'data-share' ) ||
				'';
			// If button is already marking the playing queue, toggle pause.
			var playlistPlaying =
				queue.length > 0 &&
				queueMeta &&
				queueMeta.playlist &&
				parseInt( queueMeta.playlist.id, 10 ) === playlistId &&
				! audio.paused;
			if ( playlistPlaying ) {
				audio.pause();
				return;
			}
			if (
				queue.length > 0 &&
				queueMeta &&
				queueMeta.playlist &&
				parseInt( queueMeta.playlist.id, 10 ) === playlistId &&
				audio.paused
			) {
				audio.play().catch( function () {} );
				return;
			}
			button.classList.add( 'is-loading' );
			button.setAttribute( 'aria-busy', 'true' );
			fetchPlaylistQueue( playlistId, share )
				.then( function ( data ) {
					var tracks = Array.isArray( data.tracks )
						? data.tracks
						: [];
					queue = tracks
						.map( function ( item ) {
							return trackFromApi( item, data );
						} )
						.filter( function ( track ) {
							return track.full || track.url;
						} );
					queueMeta = data;
					if ( ! queue.length ) {
						throw { message: labels.noPlayable || labels.error };
					}
					// Update all playlist buttons pressed state
					document
						.querySelectorAll( '[data-mw-playlist-play]' )
						.forEach( function ( btn ) {
							btn.setAttribute(
								'aria-pressed',
								parseInt(
									btn.getAttribute( 'data-playlist-id' ),
									10
								) === playlistId
									? 'true'
									: 'false'
							);
						} );
					select( 0, true );
					announcePreviewMode();
				} )
				.catch( function ( error ) {
					showNotice(
						errorMessage( error, labels.error ),
						queueMeta ? queueMeta.upsell : null
					);
					if ( error && error.message ) {
						showNotice( error.message, null );
					}
				} )
				.finally( function () {
					button.classList.remove( 'is-loading' );
					button.removeAttribute( 'aria-busy' );
					updateButtons();
				} );
		}

		document.addEventListener( 'click', function ( event ) {
			var playlistButton = event.target.closest(
				'[data-mw-playlist-play]'
			);
			if ( playlistButton && ! playlistButton.disabled ) {
				event.preventDefault();
				event.stopPropagation();
				startPlaylist( playlistButton );
				return;
			}
			var previewButton = event.target.closest( '.mw-preview-button' );
			if ( previewButton ) {
				event.preventDefault();
				startPreviewButton( previewButton );
				return;
			}
			var cardButton = event.target.closest(
				'.mw-card-play[data-mw-release-id]'
			);
			if ( cardButton ) {
				event.preventDefault();
				event.stopPropagation();
				startRelease( cardButton );
			}
		} );
		toggle.addEventListener( 'click', function () {
			if ( busy ) {
				return;
			}
			if ( audio.paused ) {
				audio.play().catch( function () {} );
			} else {
				audio.pause();
			}
		} );
		previous.addEventListener( 'click', function () {
			select( current - 1, true );
		} );
		next.addEventListener( 'click', function () {
			select( current + 1, true );
		} );
		if ( queueToggle ) {
			queueToggle.addEventListener( 'click', function () {
				setQueueOpen( queuePanel.hidden );
			} );
		}
		if ( queueClose ) {
			queueClose.addEventListener( 'click', function () {
				setQueueOpen( false );
			} );
		}
		if ( noticeClose ) {
			noticeClose.addEventListener( 'click', hideNotice );
		}
		close.addEventListener( 'click', function () {
			audio.pause();
			audio.removeAttribute( 'src' );
			current = -1;
			queue = [];
			queueMeta = null;
			player.hidden = true;
			player.removeAttribute( 'data-mw-state' );
			setQueueOpen( false );
			hideNotice();
			clearState();
			updateButtons();
		} );
		var VOLUME_KEY = 'mw-player-volume';

		function applyVolume( raw ) {
			var value = Math.min( 1, Math.max( 0, parseFloat( raw ) || 0 ) );
			audio.volume = value;
			if ( volume ) {
				volume.value = String( Math.round( value * 100 ) );
			}

			return value;
		}

		if ( volume ) {
			volume.addEventListener( 'input', function () {
				var value = applyVolume( volume.value );
				try {
					window.localStorage.setItem( VOLUME_KEY, String( value ) );
				} catch ( e ) {}
			} );
			try {
				var savedVolume = window.localStorage.getItem( VOLUME_KEY );
				if ( null !== savedVolume && '' !== savedVolume ) {
					applyVolume( savedVolume );
				}
			} catch ( e ) {}
		}

		/* -------------------------------------------------------------- *
		 * Session persistence — the Spotify/Apple Music behavior.         *
		 *                                                                 *
		 * The queue, current track, and exact position are written to     *
		 * sessionStorage continuously, so a hard reload (F5), a link      *
		 * opened where soft navigation is excluded, or a new visit to     *
		 * the site brings the player bar back with everything intact:     *
		 * track, artwork, queue panel, and resume position. Playback      *
		 * resumes automatically where the browser allows it; otherwise    *
		 * the bar sits paused at the exact spot until one tap.            *
		 * -------------------------------------------------------------- */

		var STATE_KEY = 'mw-player-state';
		var STATE_TTL = 30 * 60 * 1000;
		var PERSIST_MIN_INTERVAL = 3000;
		var lastPersistAt = 0;

		function persistState( force ) {
			var track = currentTrack();
			if ( ! track ) {
				return;
			}
			var now = Date.now();
			if ( ! force && now - lastPersistAt < PERSIST_MIN_INTERVAL ) {
				return;
			}
			lastPersistAt = now;
			try {
				window.sessionStorage.setItem(
					STATE_KEY,
					JSON.stringify( {
						v: 1,
						at: now,
						index: current,
						position: audio.currentTime || 0,
						playing: ! audio.paused,
						queueOpen: Boolean( queuePanel ) && ! queuePanel.hidden,
						queue,
						meta: queueMeta,
					} )
				);
			} catch ( e ) {}
		}

		function clearState() {
			try {
				window.sessionStorage.removeItem( STATE_KEY );
			} catch ( e ) {}
		}

		function readState() {
			try {
				var raw = window.sessionStorage.getItem( STATE_KEY );
				if ( ! raw ) {
					return null;
				}
				var data = JSON.parse( raw );
				if (
					! data ||
					data.v !== 1 ||
					! Array.isArray( data.queue ) ||
					! data.queue.length
				) {
					return null;
				}
				if ( Date.now() - ( data.at || 0 ) > STATE_TTL ) {
					clearState();

					return null;
				}
				var index = parseInt( data.index, 10 ) || 0;
				if ( index < 0 || index >= data.queue.length ) {
					return null;
				}

				return data;
			} catch ( e ) {
				return null;
			}
		}

		function restoreSession() {
			var data = readState();
			if ( ! data || currentTrack() ) {
				return;
			}
			queue = data.queue.filter( function ( item ) {
				return item && ( item.full || item.url );
			} );
			queueMeta = data.meta || null;
			if ( ! queue.length ) {
				clearState();

				return;
			}
			current = Math.min(
				parseInt( data.index, 10 ) || 0,
				queue.length - 1
			);
			var position = parseFloat( data.position ) || 0;
			var shouldResume = data.playing === true;
			var track = queue[ current ];
			render();
			if ( data.queueOpen ) {
				setQueueOpen( true );
			}
			announcePreviewMode();
			setLoading( true );

			loadSource( track )
				.then( function ( url ) {
					if ( currentTrack() !== track ) {
						return;
					}
					setLoading( false );
					audio.src = url;
					audio.load();
					mediaSession( track );

					var resume = function () {
						audio.removeEventListener( 'loadedmetadata', resume );
						if (
							position > 0 &&
							audio.duration &&
							position < audio.duration - 1
						) {
							try {
								audio.currentTime = position;
							} catch ( e ) {}
						}
						progress.value = audio.duration
							? String(
									( audio.currentTime / audio.duration ) * 100
							  )
							: '0';
						progress.style.setProperty(
							'--mw-progress',
							progress.value + '%'
						);
						time.textContent =
							formatTime( audio.currentTime ) +
							( audio.duration
								? ' / ' + formatTime( audio.duration )
								: '' );
						if ( shouldResume ) {
							audio.play().catch( function () {
								// Autoplay blocked after reload: stay paused
								// at the restored position until one tap.
								render();
							} );
						} else {
							render();
						}
					};
					if ( audio.readyState >= 1 ) {
						resume();
					} else {
						audio.addEventListener( 'loadedmetadata', resume );
					}
				} )
				.catch( function () {
					setLoading( false );
					render();
				} );
		}

		audio.addEventListener( 'play', function () {
			persistState( true );
		} );
		audio.addEventListener( 'pause', function () {
			persistState( true );
		} );
		audio.addEventListener( 'ended', function () {
			persistState( true );
		} );
		audio.addEventListener( 'timeupdate', function () {
			persistState( false );
		} );
		window.addEventListener( 'pagehide', function () {
			persistState( true );
		} );
		document.addEventListener( 'visibilitychange', function () {
			if ( document.hidden ) {
				persistState( true );
			}
		} );
		restoreSession();
		audio.addEventListener( 'play', render );
		audio.addEventListener( 'pause', render );
		audio.addEventListener( 'ended', function () {
			if ( current < queue.length - 1 ) {
				select( current + 1, true );
			} else {
				audio.currentTime = 0;
				render();
			}
		} );
		audio.addEventListener( 'error', function () {
			if ( ! currentTrack() || ! audio.src ) {
				return;
			}
			setLoading( false );
			showNotice(
				labels.streamError || labels.error,
				queueMeta ? queueMeta.upsell : null
			);
			render();
		} );
		audio.addEventListener( 'timeupdate', function () {
			var duration = audio.duration || 0;
			var track = currentTrack();
			if (
				track &&
				track.previewLimit > 0 &&
				audio.currentTime >= track.previewLimit
			) {
				audio.pause();
				audio.currentTime = 0;
				track.limited = true;
				if (
					queueMeta &&
					queueMeta.upsell &&
					( queueMeta.upsell.message || queueMeta.upsell.previewNote )
				) {
					var upsell = queueMeta.upsell;
					showNotice( upsell.message || upsell.previewNote, upsell );
				}
				render();
				return;
			}
			progress.value = duration
				? String( ( audio.currentTime / duration ) * 100 )
				: '0';
			// Feeds the webkit slider gradient stop in the theme stylesheet.
			progress.style.setProperty( '--mw-progress', progress.value + '%' );
			time.textContent =
				formatTime( audio.currentTime ) +
				( duration ? ' / ' + formatTime( duration ) : '' );
		} );
		progress.addEventListener( 'input', function () {
			progress.style.setProperty( '--mw-progress', progress.value + '%' );
			if ( audio.duration ) {
				audio.currentTime =
					( parseFloat( progress.value ) / 100 ) * audio.duration;
			}
		} );

		playerController = {
			playRelease( trigger, releaseId ) {
				startRelease( trigger, releaseId );
			},
			playPlaylist( trigger, playlistId, share ) {
				if ( trigger ) {
					trigger.setAttribute(
						'data-playlist-id',
						String( playlistId )
					);
					if ( share ) {
						trigger.setAttribute( 'data-mw-share', share );
					}
				}
				startPlaylist(
					trigger || {
						getAttribute( name ) {
							if (
								'data-playlist-id' === name ||
								'data-mw-playlist-id' === name
							) {
								return String( playlistId );
							}
							if (
								'data-mw-share' === name ||
								'data-share' === name
							) {
								return share ? String( share ) : null;
							}
							return null;
						},
						classList: {
							add() {},
							remove() {},
						},
						setAttribute() {},
					}
				);
			},
			togglePlayback() {
				if ( ! currentTrack() || busy ) {
					return;
				}
				if ( audio.paused ) {
					audio.play().catch( function () {} );
				} else {
					audio.pause();
				}
			},
			isActive() {
				return Boolean( currentTrack() );
			},
			seekBy,
			sync: updateButtons,
		};
		// Expose for playlists.js progressive enhancement.
		window._mwPreviewController = playerController;
	}

	/* Keyboard shortcuts: Space or K toggles playback, arrow keys seek.
	 * Only while a track is loaded, and never while typing in a field. */
	document.addEventListener( 'keydown', function ( event ) {
		if (
			! playerController ||
			! playerController.isActive() ||
			event.defaultPrevented
		) {
			return;
		}
		var target = event.target;
		if (
			target &&
			target.closest &&
			target.closest(
				'input, textarea, select, button, a[href], [contenteditable="true"], [role="slider"]'
			)
		) {
			return;
		}
		if ( event.altKey || event.ctrlKey || event.metaKey ) {
			return;
		}
		var key = 'string' === typeof event.key ? event.key.toLowerCase() : '';
		if ( ' ' === event.key || 'k' === key || 'spacebar' === key ) {
			event.preventDefault();
			playerController.togglePlayback();
		} else if ( 'arrowleft' === key ) {
			playerController.seekBy( -5 );
		} else if ( 'arrowright' === key ) {
			playerController.seekBy( 5 );
		}
	} );

	// Secure download rows stream through the global player instead of their
	// legacy inline audio element, so intercept before download.js handles it.
	document.addEventListener(
		'click',
		function ( event ) {
			var button =
				event.target && event.target.closest
					? event.target.closest( '.mw-secure-play-button' )
					: null;
			if ( ! button || button.disabled || ! playerController ) {
				return;
			}
			event.preventDefault();
			event.stopPropagation();
			document
				.querySelectorAll( '.mw-secure-audio' )
				.forEach( function ( candidate ) {
					candidate.pause();
				} );
			playerController.playRelease(
				button,
				parseInt( button.getAttribute( 'data-release-id' ), 10 ) || 0
			);
		},
		true
	);

	/* ------------------------------------------------------------------ *
	 * Persistent navigation                                              *
	 *                                                                    *
	 * Same-site links are fetched in the background and their body is    *
	 * swapped in place, the way SPA-backed platforms (Spotify, Apple     *
	 * Music) keep the bar mounted. The player node itself is preserved,  *
	 * so audio keeps playing across page views and browser back/forward. *
	 * ------------------------------------------------------------------ */

	var navFileHref = /\.(mp3|m4a|aac|ogg|wav|flac|zip|rar|pdf)([?#]|$)/i;
	var navSwapped = false;
	// Native navigation is the default; the persistent body swap is opt-in
	// because it cannot safely reconcile every WordPress/WooCommerce page
	// lifecycle (PROJECT_PLAN.md Stage 4 deliverable 1).
	var navEnabled = settings.persistentNav === true;

	function navSupported() {
		return (
			navEnabled &&
			Boolean(
				window.fetch &&
					window.DOMParser &&
					window.URL &&
					window.CustomEvent
			) &&
			typeof document.body.replaceChildren === 'function'
		);
	}

	function navMergeState( extra ) {
		var state = {};
		if ( history.state && 'object' === typeof history.state ) {
			Object.keys( history.state ).forEach( function ( key ) {
				state[ key ] = history.state[ key ];
			} );
		}
		Object.keys( extra ).forEach( function ( key ) {
			state[ key ] = extra[ key ];
		} );
		return state;
	}

	function navExcluded( url ) {
		if (
			url.searchParams.has( 'add-to-cart' ) ||
			url.searchParams.has( 'wc-ajax' ) ||
			url.searchParams.has( 'remove_item' )
		) {
			return true;
		}
		var path = url.pathname;
		return (
			/\/wp-(admin|login|json)/.test( path ) ||
			/\/(cart|checkout)(\/|$)/.test( path ) ||
			navFileHref.test( path )
		);
	}

	function navShouldIntercept( link, event ) {
		if (
			! navSupported() ||
			event.defaultPrevented ||
			event.button !== 0 ||
			event.metaKey ||
			event.ctrlKey ||
			event.shiftKey ||
			event.altKey
		) {
			return false;
		}
		if (
			! link ||
			'_blank' === link.target ||
			link.hasAttribute( 'download' ) ||
			link.hasAttribute( 'data-mw-no-swap' )
		) {
			return false;
		}
		// The catalog block owns its own in-place pagination and history.
		if (
			link.closest(
				'.mw-catalog-results, .wp-block-query-pagination, form.mw-catalog-filters'
			)
		) {
			return false;
		}
		// The library panel and playlist toggles run their own lighter
		// section swaps; the full-page path would double-fetch them.
		if (
			link.closest( '[data-mw-library-panel], .mw-playlists__toggle' )
		) {
			return false;
		}
		// Never hijack the admin bar or editor-adjacent chrome.
		if ( link.closest( '#wpadminbar' ) ) {
			return false;
		}
		var href = link.getAttribute( 'href' );
		if (
			! href ||
			/^(#|mailto:|tel:|javascript:|blob:|data:)/i.test( href )
		) {
			return false;
		}
		var url;
		try {
			url = new window.URL( href, window.location.href );
		} catch ( error ) {
			return false;
		}
		if (
			url.origin !== window.location.origin ||
			! /^https?:$/.test( url.protocol )
		) {
			return false;
		}
		if ( navExcluded( url ) ) {
			return false;
		}
		return (
			url.pathname !== window.location.pathname ||
			url.search !== window.location.search
		);
	}

	function navScriptLoaded( src ) {
		var scripts = document.getElementsByTagName( 'script' );
		for ( var index = 0; index < scripts.length; index += 1 ) {
			if ( scripts[ index ].src === src ) {
				return true;
			}
		}
		return false;
	}

	function navActivateScripts( root ) {
		root.querySelectorAll( 'script' ).forEach( function ( existing ) {
			if ( existing.src && navScriptLoaded( existing.src ) ) {
				if ( existing.parentNode ) {
					existing.parentNode.removeChild( existing );
				}
				return;
			}
			var replacement = document.createElement( 'script' );
			var attributes = existing.attributes;
			for ( var index = 0; index < attributes.length; index += 1 ) {
				replacement.setAttribute(
					attributes[ index ].name,
					attributes[ index ].value
				);
			}
			replacement.textContent = existing.textContent;
			if ( existing.parentNode ) {
				existing.parentNode.replaceChild( replacement, existing );
			}
		} );
	}

	function navUpdateHead( fresh ) {
		if ( fresh.title ) {
			document.title = fresh.title;
		}
		var pairs = [
			[ 'link[rel="canonical"]', 'href' ],
			[ 'meta[name="description"]', 'content' ],
		];
		pairs.forEach( function ( pair ) {
			var freshNode = fresh.head.querySelector( pair[ 0 ] );
			var currentNode = document.head.querySelector( pair[ 0 ] );
			if ( freshNode && currentNode ) {
				currentNode.setAttribute(
					pair[ 1 ],
					freshNode.getAttribute( pair[ 1 ] ) || ''
				);
			}
		} );
	}

	function navRenderPage( fresh, url, scrollY ) {
		navSwapped = true;
		var playerNode = document.querySelector( selector );
		// The incoming page ships its own hidden player markup; drop it so the
		// live instance (and its playing audio) stays the single source.
		var freshPlayer = fresh.querySelector( selector );
		if ( freshPlayer && freshPlayer.parentNode ) {
			freshPlayer.parentNode.removeChild( freshPlayer );
		}
		// Keep the live admin bar: replacing it would orphan the event
		// listeners bound by WordPress admin-bar scripts.
		var adminBar = document.getElementById( 'wpadminbar' );
		var freshAdminBar = fresh.getElementById( 'wpadminbar' );
		if ( freshAdminBar && freshAdminBar.parentNode ) {
			freshAdminBar.parentNode.removeChild( freshAdminBar );
		}
		navUpdateHead( fresh );
		var children = Array.prototype.slice.call( fresh.body.children );
		document.body.replaceChildren.apply( document.body, children );
		document.body.className = fresh.body.className;
		if ( adminBar ) {
			document.body.appendChild( adminBar );
		}
		if ( playerNode ) {
			document.body.appendChild( playerNode );
		}
		navActivateScripts( document.body );
		window.scrollTo( 0, scrollY || 0 );
		navAnnounceRoute();
		document.dispatchEvent(
			new window.CustomEvent( 'mw-page-rendered', {
				detail: { url },
			} )
		);
		if ( playerController && playerController.sync ) {
			playerController.sync();
		}
	}

	// Screen readers must hear soft navigations: announce the new title and
	// move focus to the main landmark (PROJECT_PLAN.md Stage 4 deliverable 6).
	function navAnnounceRoute() {
		var region = document.getElementById( 'mw-route-announcer' );
		if ( ! region ) {
			region = document.createElement( 'div' );
			region.id = 'mw-route-announcer';
			region.setAttribute( 'aria-live', 'polite' );
			region.setAttribute( 'role', 'status' );
			region.style.position = 'absolute';
			region.style.width = '1px';
			region.style.height = '1px';
			region.style.overflow = 'hidden';
			region.style.clipPath = 'inset(50%)';
		}
		document.body.appendChild( region );
		region.textContent = document.title;
		var main = document.querySelector( 'main' );
		if ( main ) {
			main.setAttribute( 'tabindex', '-1' );
			main.focus( { preventScroll: true } );
		}
	}

	var navAbort = null;

	function navNavigate( url, push, scrollY ) {
		if ( ! navSupported() ) {
			window.location.assign( url );

			return;
		}
		if ( push ) {
			history.replaceState(
				navMergeState( { mwScrollY: window.scrollY } ),
				''
			);
		}
		if ( navAbort ) {
			navAbort.abort();
		}
		var controller = new window.AbortController();
		navAbort = controller;
		var fallback = function () {
			if ( ! controller.signal.aborted ) {
				window.location.assign( url );
			}
		};
		var request;
		if ( ! navPrefetchCache.has( url ) || navPrefetchStale( url ) ) {
			request = window.fetch( url, {
				credentials: 'same-origin',
				redirect: 'follow',
				headers: { 'X-MW-Nav': '1' },
				signal: controller.signal,
			} );
		} else {
			// Response bodies can only be read once: consume the prefetch.
			request = navPrefetchCache.get( url ).promise;
			navPrefetchCache.delete( url );
		}
		request
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'پیمایش ناموفق بود.' );
				}

				return response.text();
			} )
			.then( function ( html ) {
				if ( controller !== navAbort ) {
					return;
				}
				var fresh = new window.DOMParser().parseFromString(
					html,
					'text/html'
				);
				if ( ! fresh.body || ! fresh.body.children.length ) {
					fallback();

					return;
				}
				try {
					navRenderPage( fresh, url, push ? 0 : scrollY );
				} catch ( error ) {
					fallback();

					return;
				}
				if ( push ) {
					history.pushState( { mwNav: true }, '', url );
				}
			} )
			.catch( function ( error ) {
				if ( error && 'AbortError' === error.name ) {
					return;
				}
				fallback();
			} );
	}

	/* Hover/focus prefetch: the next page is usually fetched while the
	 * visitor is still deciding, so taps feel instant. Capped and skipped
	 * for visitors with data-saver on. */
	var navPrefetchCache = new window.Map();
	var PREFETCH_TTL = 30000;
	var PREFETCH_LIMIT = 12;

	function navPrefetchStale( url ) {
		var entry = navPrefetchCache.get( url );

		return ! entry || Date.now() - entry.created > PREFETCH_TTL;
	}

	function navPrefetch( link ) {
		if ( ! navSupported() || ! window.Map ) {
			return;
		}
		var connection = navigator.connection || {};
		if ( connection.saveData || navPrefetchCache.size >= PREFETCH_LIMIT ) {
			return;
		}
		if (
			! navShouldIntercept( link, { defaultPrevented: false, button: 0 } )
		) {
			return;
		}
		var url = new window.URL(
			link.getAttribute( 'href' ),
			window.location.href
		).href;
		if ( ! navPrefetchStale( url ) ) {
			return;
		}
		navPrefetchCache.set( url, {
			created: Date.now(),
			promise: window.fetch( url, {
				credentials: 'same-origin',
				headers: { 'X-MW-Nav': '1' },
			} ),
		} );
	}

	document.addEventListener(
		'pointerover',
		function ( event ) {
			var link =
				event.target && event.target.closest
					? event.target.closest( 'a[href]' )
					: null;
			if ( link ) {
				navPrefetch( link );
			}
		},
		{ passive: true }
	);
	document.addEventListener( 'focusin', function ( event ) {
		var link =
			event.target && event.target.closest
				? event.target.closest( 'a[href]' )
				: null;
		if ( link ) {
			navPrefetch( link );
		}
	} );

	document.addEventListener(
		'click',
		function ( event ) {
			var link =
				event.target && event.target.closest
					? event.target.closest( 'a[href]' )
					: null;
			if ( ! navShouldIntercept( link, event ) ) {
				return;
			}
			event.preventDefault();
			navNavigate(
				new window.URL(
					link.getAttribute( 'href' ),
					window.location.href
				).href,
				true,
				0
			);
		},
		true
	);

	window.addEventListener( 'popstate', function ( event ) {
		if ( ! navSwapped ) {
			return;
		}
		if ( event.state && event.state.mwNav ) {
			navNavigate(
				window.location.href,
				false,
				event.state.mwScrollY || 0
			);
			return;
		}
		// History entries owned by in-place components stay in place; those
		// controllers restore themselves on their own popstate listeners.
		if (
			event.state &&
			event.state.mwCatalog &&
			document.querySelector( 'form.mw-catalog-filters' )
		) {
			return;
		}
		if (
			event.state &&
			event.state.mwLibraryNav &&
			document.querySelector( '[data-mw-library-panel]' )
		) {
			return;
		}
		// Anything unrecognized after a swap falls back to a clean reload.
		window.location.reload();
	} );

	document.addEventListener( 'DOMContentLoaded', function () {
		var player = document.querySelector( selector );
		if ( player ) {
			initialize( player );
		}
		if ( navSupported() ) {
			history.replaceState( navMergeState( { mwNav: true } ), '' );
		}
	} );
} )();
