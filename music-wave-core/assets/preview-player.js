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
			lyricsLrc: '',
			lyricsOffset: 0,
			hasLyrics: false,
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
			lyricsLrc: item.lyricsLrc || '',
			lyricsOffset: parseInt( item.lyricsOffset, 10 ) || 0,
			hasLyrics: Boolean( item.hasLyrics ) || Boolean( item.lyricsLrc ),
		};
	}

	function parseLrc( raw ) {
		var lines = [];
		String( raw || '' )
			.split( /\r\n|\r|\n/ )
			.forEach( function ( row ) {
				row = row.trim();
				if ( ! row || /^\[[a-z]+:/i.test( row ) ) {
					return;
				}
				var stamps = [];
				var stampRe = /\[(\d{1,2}):(\d{2})(?:\.(\d{1,3}))?\]/g;
				var match;
				while ( ( match = stampRe.exec( row ) ) ) {
					stamps.push( match );
				}
				if ( stamps.length ) {
					var text = row
						.replace( /\[\d{1,2}:\d{2}(?:\.\d{1,3})?\]/g, '' )
						.trim();
					if ( ! text ) {
						return;
					}
					var parts = text.split( /\s*(?:\||\/\/)\s+/ );
					var primary = ( parts[ 0 ] || text ).trim();
					var translation = ( parts[ 1 ] || '' ).trim();
					stamps.forEach( function ( stamp ) {
						var ms = stamp[ 3 ]
							? parseFloat( '0.' + stamp[ 3 ] )
							: 0;
						lines.push( {
							time:
								parseInt( stamp[ 1 ], 10 ) * 60 +
								parseInt( stamp[ 2 ], 10 ) +
								ms,
							text: primary,
							translation,
						} );
					} );
					return;
				}
				lines.push( { time: -1, text: row, translation: '' } );
			} );
		lines.sort( function ( a, b ) {
			return a.time - b.time;
		} );
		return lines;
	}

	function formatLyricClock( seconds ) {
		if ( seconds < 0 ) {
			return '';
		}
		var minutes = Math.floor( seconds / 60 );
		var remain = Math.floor( seconds ) % 60;
		return (
			String( minutes ).padStart( 2, '0' ) +
			':' +
			String( remain ).padStart( 2, '0' )
		);
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
		var duration = player.querySelector( '.mw-global-player__duration' );
		var volume = player.querySelector( 'input.mw-global-player__volume' );
		var mute = player.querySelector( '.mw-global-player__mute' );
		var queueToggle = player.querySelector(
			'.mw-global-player__queue-toggle'
		);
		var queuePanel = player.querySelector( '[data-mw-queue]' );
		var queueList = player.querySelector( '.mw-global-player__queue-list' );
		var queueClose = player.querySelector(
			'.mw-global-player__queue-close'
		);
		var lyricsToggle = player.querySelector(
			'.mw-global-player__lyrics-toggle'
		);
		var lyricsPanel = player.querySelector( '[data-mw-player-lyrics]' );
		var lyricsClose = player.querySelector(
			'.mw-global-player__lyrics-close'
		);
		var lyricsRoot = player.querySelector( '[data-mw-lyrics]' );
		var lyricsStage = player.querySelector( '[data-mw-lyrics-stage]' );
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

		// The play/pause button ships one SVG per state (play, pause,
		// spinner); the stylesheet reveals the icon named by data-state.
		// Legacy markup without icons keeps the text glyph so the control
		// never renders empty.
		var buffering = false;

		function renderToggle() {
			var waiting = busy || buffering;
			var state = 'playing';
			if ( waiting ) {
				state = 'loading';
			} else if ( audio.paused ) {
				state = 'paused';
			}
			toggle.setAttribute( 'data-state', state );
			toggle.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
			if ( ! toggle.querySelector( 'svg' ) ) {
				toggle.textContent = waiting ? '…' : playGlyph();
			}
		}

		function setLoading( loading ) {
			busy = loading;
			renderToggle();
		}

		// Elapsed / total, mirrored into aria-valuetext so the range input
		// announces "0:42 / 3:10" instead of a bare percentage. The total is
		// the full stream length (the preview limit is explained by the
		// upsell notice, not by shortening the timeline).
		function renderTime() {
			var total = audio.duration || 0;
			var elapsed = formatTime( audio.currentTime );
			var length = total ? formatTime( total ) : '';
			if ( duration ) {
				time.textContent = elapsed;
				duration.textContent = length || '–:––';
			} else {
				time.textContent = elapsed + ( length ? ' / ' + length : '' );
			}
			progress.setAttribute(
				'aria-valuetext',
				length ? elapsed + ' / ' + length : elapsed
			);
		}

		// layout.css keeps page content and sticky rails clear of the bar by
		// reading --mw-player-offset from <html>. The stylesheet ships a rem
		// estimate; measuring the rendered footprint (height plus the gap to
		// the viewport edge) covers zoom, font size, and the docked phone
		// layout. Transforms from the entrance animation do not affect
		// offsetHeight or the computed inset, so this is safe mid-animation.
		function measureFootprint() {
			var root = document.documentElement;
			if ( player.hidden ) {
				root.style.removeProperty( '--mw-player-offset' );
				return;
			}
			var inset =
				parseFloat( window.getComputedStyle( player ).bottom ) || 0;
			var covered = player.offsetHeight + inset;
			if ( covered > 0 ) {
				root.style.setProperty(
					'--mw-player-offset',
					Math.ceil( covered ) + 'px'
				);
			}
		}

		function setPresence( active ) {
			document.documentElement.classList.toggle(
				'mw-has-player',
				active
			);
			if ( window.requestAnimationFrame ) {
				window.requestAnimationFrame( measureFootprint );
			} else {
				measureFootprint();
			}
		}

		if ( 'ResizeObserver' in window ) {
			new window.ResizeObserver( measureFootprint ).observe( player );
		} else {
			window.addEventListener( 'resize', measureFootprint );
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
			// Now-playing rows: flag the list item that owns the active
			// trigger so the theme can light up an equalizer (CSS-only).
			document
				.querySelectorAll(
					'.mw-collection-list__item, .mw-global-player__queue-item'
				)
				.forEach( function ( row ) {
					var trigger = row.querySelector(
						'.mw-preview-button[data-preview-url], ' +
							'.mw-card-play[data-mw-release-id]'
					);
					var active = false;
					if ( trigger && playing ) {
						if ( trigger.matches( '.mw-card-play' ) ) {
							var rowReleaseId =
								parseInt(
									trigger.getAttribute(
										'data-mw-release-id'
									),
									10
								) || 0;
							active =
								rowReleaseId > 0 &&
								( track.rootId === rowReleaseId ||
									track.releaseId === rowReleaseId );
						} else {
							active =
								track.url !== '' &&
								trigger.getAttribute( 'data-preview-url' ) ===
									track.url;
						}
					}
					if ( active ) {
						row.setAttribute( 'data-mw-playing', 'true' );
					} else {
						row.removeAttribute( 'data-mw-playing' );
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
			if ( open ) {
				setLyricsOpen( false );
			}
			queuePanel.hidden = ! open;
			queueToggle.setAttribute(
				'aria-expanded',
				open ? 'true' : 'false'
			);
		}

		function setLyricsOpen( open ) {
			if ( ! lyricsPanel || ! lyricsToggle ) {
				return;
			}
			if ( open ) {
				if ( queuePanel && queueToggle ) {
					queuePanel.hidden = true;
					queueToggle.setAttribute( 'aria-expanded', 'false' );
				}
				renderLyricsPanel();
			}
			lyricsPanel.hidden = ! open;
			lyricsToggle.setAttribute(
				'aria-expanded',
				open ? 'true' : 'false'
			);
		}

		function renderLyricsPanel() {
			if (
				! lyricsToggle ||
				! lyricsPanel ||
				! lyricsStage ||
				! lyricsRoot
			) {
				return;
			}
			var track = currentTrack();
			var raw = track && track.lyricsLrc ? String( track.lyricsLrc ) : '';
			var has = Boolean( track && ( track.hasLyrics || raw.trim() ) );
			lyricsToggle.hidden = ! has;
			if ( ! has ) {
				setLyricsOpen( false );
				lyricsStage.replaceChildren();
				return;
			}
			lyricsRoot.setAttribute(
				'data-mw-lyric-offset',
				String( track.lyricsOffset || 0 )
			);
			if ( track.releaseId ) {
				lyricsRoot.setAttribute(
					'data-release-id',
					String( track.releaseId )
				);
			}
			var lines = parseLrc( raw );
			lyricsStage.replaceChildren();
			lines.forEach( function ( line, index ) {
				var timed = line.time >= 0;
				var node = document.createElement( timed ? 'button' : 'p' );
				if ( timed ) {
					node.type = 'button';
					node.setAttribute(
						'data-mw-lyric-time',
						String( line.time )
					);
				}
				node.className = 'mw-lyrics__line';
				node.setAttribute( 'data-mw-lyric-index', String( index ) );
				var text = document.createElement( 'span' );
				text.className = 'mw-lyrics__text';
				text.textContent = line.text;
				node.appendChild( text );
				if ( line.translation ) {
					var sub = document.createElement( 'span' );
					sub.className = 'mw-lyrics__sub';
					sub.textContent = line.translation;
					node.appendChild( sub );
				}
				if ( timed ) {
					var stamp = document.createElement( 'span' );
					stamp.className = 'mw-lyrics__stamp';
					stamp.textContent = formatLyricClock( line.time );
					node.appendChild( stamp );
				}
				lyricsStage.appendChild( node );
			} );
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
			setPresence( true );
			renderToggle();
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
			renderLyricsPanel();
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
		if ( lyricsToggle ) {
			lyricsToggle.addEventListener( 'click', function () {
				setLyricsOpen( lyricsPanel.hidden );
			} );
		}
		if ( lyricsClose ) {
			lyricsClose.addEventListener( 'click', function () {
				setLyricsOpen( false );
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
			setPresence( false );
			setQueueOpen( false );
			setLyricsOpen( false );
			hideNotice();
			clearState();
			updateButtons();
		} );
		var VOLUME_KEY = 'mw-player-volume';
		var lastAudibleVolume = 1;

		function renderVolume() {
			var level = audio.muted || 0 === audio.volume ? 'muted' : 'audible';
			player.setAttribute( 'data-mw-volume', level );
			if ( volume ) {
				volume.style.setProperty(
					'--mw-volume',
					Math.round( ( audio.muted ? 0 : audio.volume ) * 100 ) + '%'
				);
			}
			if ( mute ) {
				mute.setAttribute(
					'aria-pressed',
					'muted' === level ? 'true' : 'false'
				);
				mute.setAttribute(
					'aria-label',
					'muted' === level
						? labels.unmute || 'باصدا'
						: labels.mute || 'بی‌صدا'
				);
			}
		}

		function applyVolume( raw ) {
			var value = Math.min( 1, Math.max( 0, parseFloat( raw ) || 0 ) );
			audio.volume = value;
			if ( value > 0 ) {
				lastAudibleVolume = value;
				audio.muted = false;
			}
			if ( volume ) {
				volume.value = String( Math.round( value * 100 ) );
			}
			renderVolume();

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
		if ( mute ) {
			mute.addEventListener( 'click', function () {
				if ( audio.muted || 0 === audio.volume ) {
					audio.muted = false;
					if ( 0 === audio.volume ) {
						applyVolume( lastAudibleVolume || 1 );
					}
				} else {
					audio.muted = true;
				}
				renderVolume();
			} );
		}
		audio.addEventListener( 'volumechange', renderVolume );
		renderVolume();

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
						lyricsOpen:
							Boolean( lyricsPanel ) && ! lyricsPanel.hidden,
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
			if ( data.lyricsOpen ) {
				setLyricsOpen( true );
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
						renderTime();
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
		// Network stalls surface as the spinner without blocking the toggle,
		// so a visitor can still pause a stream that is buffering.
		audio.addEventListener( 'waiting', function () {
			buffering = true;
			renderToggle();
		} );
		[ 'playing', 'canplay', 'pause', 'emptied', 'error' ].forEach(
			function ( name ) {
				audio.addEventListener( name, function () {
					buffering = false;
					renderToggle();
				} );
			}
		);
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
			var total = audio.duration || 0;
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
			progress.value = total
				? String( ( audio.currentTime / total ) * 100 )
				: '0';
			// Feeds the webkit slider gradient stop in the theme stylesheet.
			progress.style.setProperty( '--mw-progress', progress.value + '%' );
			renderTime();
		} );
		audio.addEventListener( 'loadedmetadata', renderTime );
		audio.addEventListener( 'durationchange', renderTime );
		progress.addEventListener( 'input', function () {
			progress.style.setProperty( '--mw-progress', progress.value + '%' );
			if ( audio.duration ) {
				audio.currentTime =
					( parseFloat( progress.value ) / 100 ) * audio.duration;
			}
			renderTime();
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
			sync() {
				// Soft navigation swaps <body>; the presence flag lives on
				// <html> so it survives, but re-assert it in case the
				// incoming page reset the element.
				setPresence( Boolean( currentTrack() ) );
				updateButtons();
			},
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
	// Shipped enabled through the `persistent_navigation` setting and the
	// `music_wave_persistent_navigation` filter; sites that prefer native
	// navigation everywhere switch it off there. Commerce, auth, and admin
	// routes are never intercepted regardless of the setting.
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
			typeof document.body.replaceChildren === 'function' &&
			// A page with router regions (enhanced query pagination, Woo
			// collections) is driven by the core Interactivity router,
			// whose own history handling must not be second-guessed.
			! document.querySelector( '[data-wp-router-region]' )
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
		// Links the core Interactivity API drives itself (enhanced query
		// pagination, WooCommerce actions) already have a client-side owner.
		if (
			link.hasAttribute( 'data-wp-on--click' ) ||
			link.hasAttribute( 'data-wp-on-async--click' ) ||
			link.closest( '[data-wp-router-region]' )
		) {
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

	/* Safety contract for the body swap.
	 *
	 * WordPress hydrates `data-wp-interactive` regions exactly once, on
	 * DOMContentLoaded. A region arriving through a swap would therefore be
	 * inert: a hamburger that never opens, a lightbox that never expands.
	 * The site header is the one such region every route shares, so its
	 * live, already-hydrated node is carried across navigations instead of
	 * being replaced (only its current-item markers are refreshed). Any
	 * other interactive region on the incoming page makes the router hand
	 * the click back to the browser — a native load, after which the player
	 * restores itself from sessionStorage. */

	var NAV_CURRENT_CLASSES = [
		'current-menu-item',
		'current-menu-ancestor',
		'current-menu-parent',
	];
	var NAV_ITEM_SELECTOR =
		'.wp-block-navigation-item, .wp-block-pages-list__item';

	function navChrome( doc ) {
		var header = doc.querySelector( '.mw-site-header' );
		if ( ! header ) {
			return null;
		}
		return header.closest( '.wp-block-template-part' ) || header;
	}

	// Two headers are the same chrome when their menus list the same
	// items. Labels rather than hrefs: login/redirect style links change
	// per page, and hrefs are re-synced after the swap anyway.
	function navChromeSignature( root ) {
		var header = root.querySelector( '.mw-site-header' ) || root;
		var items = root.querySelectorAll( NAV_ITEM_SELECTOR );
		var labels = [ root.className, header.className ];
		for ( var index = 0; index < items.length; index += 1 ) {
			labels.push( ( items[ index ].textContent || '' ).trim() );
		}
		return labels.join( '|' );
	}

	function navCanCarryChrome( live, fresh ) {
		return Boolean(
			live &&
				fresh &&
				navChromeSignature( live ) === navChromeSignature( fresh )
		);
	}

	function navHydrationSafe( fresh, replacedChrome ) {
		var regions = fresh.querySelectorAll( '[data-wp-interactive]' );
		for ( var index = 0; index < regions.length; index += 1 ) {
			if (
				! replacedChrome ||
				! replacedChrome.contains( regions[ index ] )
			) {
				return false;
			}
		}
		return true;
	}

	// The carried header still describes the previous route: mirror the
	// hrefs (login redirects and the like) and the current-item markers that
	// WordPress rendered for the new one. Only attributes outside the
	// Interactivity bindings are touched, so the hydrated state stays valid.
	function navSyncChromeState( live, fresh ) {
		var liveLinks = live.querySelectorAll( 'a[href]' );
		var freshLinks = fresh.querySelectorAll( 'a[href]' );
		if ( liveLinks.length === freshLinks.length ) {
			for ( var index = 0; index < liveLinks.length; index += 1 ) {
				liveLinks[ index ].setAttribute(
					'href',
					freshLinks[ index ].getAttribute( 'href' )
				);
				if ( freshLinks[ index ].hasAttribute( 'aria-current' ) ) {
					liveLinks[ index ].setAttribute(
						'aria-current',
						freshLinks[ index ].getAttribute( 'aria-current' )
					);
				} else {
					liveLinks[ index ].removeAttribute( 'aria-current' );
				}
			}
		}
		var liveItems = live.querySelectorAll( NAV_ITEM_SELECTOR );
		var freshItems = fresh.querySelectorAll( NAV_ITEM_SELECTOR );
		if ( liveItems.length !== freshItems.length ) {
			return;
		}
		for ( var item = 0; item < liveItems.length; item += 1 ) {
			for ( var name = 0; name < NAV_CURRENT_CLASSES.length; name += 1 ) {
				liveItems[ item ].classList.toggle(
					NAV_CURRENT_CLASSES[ name ],
					freshItems[ item ].classList.contains(
						NAV_CURRENT_CLASSES[ name ]
					)
				);
			}
		}
	}

	// Links inside the open mobile overlay are the main soft-navigation
	// path, and the overlay must end up closed the way a full load would
	// have closed it. Going through the hydrated close button keeps the
	// block's own state (aria-expanded, focus return, html.has-modal-open)
	// consistent; toggling classes by hand would desynchronize it.
	function navCloseOverlay( root ) {
		var open = root.querySelector(
			'.wp-block-navigation__responsive-container.is-menu-open'
		);
		var close = open
			? open.querySelector(
					'.wp-block-navigation__responsive-container-close'
			  )
			: null;
		if ( close ) {
			close.click();
		}
	}

	function navCopyAttributes( source, target ) {
		for ( var index = 0; index < source.attributes.length; index += 1 ) {
			target.setAttribute(
				source.attributes[ index ].name,
				source.attributes[ index ].value
			);
		}
	}

	function navHasStylesheet( href ) {
		var links = document.head.querySelectorAll( 'link[rel="stylesheet"]' );
		for ( var index = 0; index < links.length; index += 1 ) {
			if ( links[ index ].getAttribute( 'href' ) === href ) {
				return true;
			}
		}
		return false;
	}

	function navHasInlineStyle( css ) {
		var styles = document.head.getElementsByTagName( 'style' );
		for ( var index = 0; index < styles.length; index += 1 ) {
			if ( ! styles[ index ].id && styles[ index ].textContent === css ) {
				return true;
			}
		}
		return false;
	}

	var NAV_STYLE_TIMEOUT = 2000;

	// Block themes print per-block and per-layout CSS in <head> for exactly
	// the blocks a route renders, so a swapped-in page can reference rules
	// the first page never loaded. New stylesheets are appended (never
	// removed: the carried header still needs its own rules) and inline
	// blocks are merged by id, de-duplicated by content. Resolves once new
	// files have loaded, so the swap never paints unstyled.
	function navMergeStyles( fresh ) {
		var pending = [];
		// Block styles rendered late in the page are printed in <body>;
		// hoisting them next to the head styles lets the swap wait for
		// them as well instead of painting the new page unstyled.
		Array.prototype.forEach.call(
			fresh.body.querySelectorAll( 'link[rel="stylesheet"][href]' ),
			function ( late ) {
				fresh.head.appendChild( late );
			}
		);
		var nodes = fresh.head.querySelectorAll(
			'link[rel="stylesheet"][href], style'
		);
		Array.prototype.forEach.call( nodes, function ( node ) {
			if ( 'LINK' === node.tagName ) {
				var href = node.getAttribute( 'href' );
				if ( navHasStylesheet( href ) ) {
					return;
				}
				var link = document.createElement( 'link' );
				navCopyAttributes( node, link );
				pending.push(
					new Promise( function ( resolve ) {
						link.addEventListener( 'load', resolve );
						link.addEventListener( 'error', resolve );
						window.setTimeout( resolve, NAV_STYLE_TIMEOUT );
					} )
				);
				document.head.appendChild( link );
				return;
			}
			var css = node.textContent || '';
			if ( ! css.trim() ) {
				return;
			}
			var current = node.id ? document.getElementById( node.id ) : null;
			if ( current && 'STYLE' === current.tagName ) {
				if ( current.textContent.indexOf( css ) === -1 ) {
					current.appendChild(
						document.createTextNode( '\n' + css )
					);
				}
				return;
			}
			if ( ! node.id && navHasInlineStyle( css ) ) {
				return;
			}
			var style = document.createElement( 'style' );
			navCopyAttributes( node, style );
			style.textContent = css;
			document.head.appendChild( style );
		} );
		return Promise.all( pending );
	}

	var NAV_SCRIPT_TIMEOUT = 10000;

	function navIsExecutable( script ) {
		var type = ( script.getAttribute( 'type' ) || '' ).trim().toLowerCase();
		return (
			'' === type ||
			'text/javascript' === type ||
			'application/javascript' === type ||
			'module' === type
		);
	}

	// Snapshot of the files already executing in this document. Taken
	// before the swap: afterwards the fresh, inert script nodes are part of
	// the document too and would match themselves.
	function navLoadedScripts() {
		var loaded = {};
		var scripts = document.getElementsByTagName( 'script' );
		for ( var index = 0; index < scripts.length; index += 1 ) {
			if ( scripts[ index ].src ) {
				loaded[ scripts[ index ].src ] = true;
			}
		}
		return loaded;
	}

	// Scripts parsed out of a fetched document never execute, so each one is
	// re-created in place. External files load in document order (async is
	// off) and the chain waits for each before running the next, which keeps
	// WordPress dependency order — `wp-api-fetch` before the modules using
	// it, localized data before its consumer — exactly as on a full load.
	function navRunScripts( scripts, loaded ) {
		return scripts.reduce( function ( chain, existing ) {
			return chain.then( function () {
				if (
					! document.body.contains( existing ) ||
					! navIsExecutable( existing )
				) {
					return undefined;
				}
				if ( existing.src && loaded[ existing.src ] ) {
					existing.parentNode.removeChild( existing );
					return undefined;
				}
				var replacement = document.createElement( 'script' );
				navCopyAttributes( existing, replacement );
				if ( ! existing.src ) {
					replacement.textContent = existing.textContent;
					existing.parentNode.replaceChild( replacement, existing );
					return undefined;
				}
				loaded[ existing.src ] = true;
				replacement.async = false;
				return new Promise( function ( resolve ) {
					replacement.addEventListener( 'load', resolve );
					replacement.addEventListener( 'error', resolve );
					window.setTimeout( resolve, NAV_SCRIPT_TIMEOUT );
					existing.parentNode.replaceChild( replacement, existing );
				} );
			} );
		}, Promise.resolve() );
	}

	function navUpdateHead( fresh ) {
		if ( fresh.title ) {
			document.title = fresh.title;
		}
		// Language, direction, and the theme's colour-scheme hint live on
		// <html>, which the swap never replaces.
		[ 'lang', 'dir', 'data-mw-scheme' ].forEach( function ( name ) {
			var value = fresh.documentElement.getAttribute( name );
			if ( value ) {
				document.documentElement.setAttribute( name, value );
			}
		} );
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

	var navSequence = 0;

	function navRenderPage( fresh, url, scrollY ) {
		navSwapped = true;
		navSequence += 1;
		var sequence = navSequence;
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
		var loaded = navLoadedScripts();
		var scripts = Array.prototype.slice.call(
			fresh.body.querySelectorAll( 'script' )
		);
		var liveChrome = navChrome( document );
		var freshChrome = navChrome( fresh );
		var carry = navCanCarryChrome( liveChrome, freshChrome );
		var slot = null;
		if ( carry ) {
			navSyncChromeState( liveChrome, freshChrome );
			// The live header takes the fresh header's place after the
			// swap; a placeholder marks the position so the node itself
			// never has to be adopted into the parsed document.
			slot = fresh.createElement( 'div' );
			freshChrome.parentNode.replaceChild( slot, freshChrome );
		}
		navUpdateHead( fresh );
		var children = Array.prototype.slice.call( fresh.body.children );
		document.body.replaceChildren.apply( document.body, children );
		document.body.className = fresh.body.className;
		if ( slot ) {
			slot.parentNode.replaceChild( liveChrome, slot );
			navCloseOverlay( liveChrome );
		}
		if ( adminBar ) {
			document.body.appendChild( adminBar );
		}
		if ( playerNode ) {
			document.body.appendChild( playerNode );
		}
		window.scrollTo( 0, scrollY || 0 );
		navAnnounceRoute();
		if ( playerController && playerController.sync ) {
			playerController.sync();
		}
		// Re-entrant enhancers (sliders, dashboard, suggest) hear about the
		// page only once its scripts — including ones loading for the first
		// time — are in place; otherwise a freshly loaded module would miss
		// both DOMContentLoaded and this event.
		navRunScripts( scripts, loaded ).then( function () {
			if ( sequence !== navSequence ) {
				return;
			}
			document.dispatchEvent(
				new window.CustomEvent( 'mw-page-rendered', {
					detail: { url },
				} )
			);
		} );
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

	// History entries the router creates carry the Interactivity API's
	// session id: without it core treats them as foreign on popstate and
	// forces a reload instead of letting the soft restore run.
	function navRouteState( scrollY ) {
		var state = { mwNav: true, mwScrollY: scrollY };
		if ( history.state && history.state.wpInteractivityId ) {
			state.wpInteractivityId = history.state.wpInteractivityId;
		}
		return state;
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
					return undefined;
				}
				var fresh = new window.DOMParser().parseFromString(
					html,
					'text/html'
				);
				if ( ! fresh.body || ! fresh.body.children.length ) {
					fallback();

					return undefined;
				}
				var freshChrome = navChrome( fresh );
				var carry = navCanCarryChrome(
					navChrome( document ),
					freshChrome
				);
				if ( ! navHydrationSafe( fresh, carry ? freshChrome : null ) ) {
					fallback();

					return undefined;
				}

				return navMergeStyles( fresh ).then( function () {
					if ( controller !== navAbort ) {
						return;
					}
					try {
						navRenderPage( fresh, url, push ? 0 : scrollY );
					} catch ( error ) {
						fallback();

						return;
					}
					if ( push ) {
						history.pushState( navRouteState( 0 ), '', url );
					}
				} );
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
