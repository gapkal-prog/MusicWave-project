/**
 * MusicWave profile avatar picker — core media frame, no custom uploader.
 *
 * Enhances the MusicWave row on profile.php / user-edit.php: select opens
 * the standard media library (images only), remove clears the hidden field.
 * Without JavaScript the hidden field keeps its value and saving still works.
 */
( function ( $ ) {
	'use strict';

	function preview() {
		return $( '[data-mw-avatar-preview]' );
	}

	function input() {
		return $( '[data-mw-avatar-input]' );
	}

	function removeButton() {
		return $( '[data-mw-avatar-remove]' );
	}

	function fallback() {
		return preview().html();
	}

	$( function () {
		if ( ! input().length || ! window.wp || ! window.wp.media ) {
			return;
		}

		var original = fallback();
		var frame = null;

		$( '[data-mw-avatar-select]' ).on( 'click', function ( event ) {
			event.preventDefault();
			if ( frame ) {
				frame.open();
				return;
			}
			frame = window.wp.media( {
				title: window.musicWaveAvatar && window.musicWaveAvatar.title ? window.musicWaveAvatar.title : 'Select',
				button: { text: window.musicWaveAvatar && window.musicWaveAvatar.button ? window.musicWaveAvatar.button : 'Use' },
				library: { type: 'image' },
				multiple: false,
			} );
			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first();
				if ( ! attachment ) {
					return;
				}
				var id = attachment.get( 'id' );
				var url = attachment.get( 'url' );
				input().val( id );
				if ( url ) {
					preview().html(
						$( '<img />', {
							src: url,
							alt: '',
							class: 'musicwave-avatar-preview__img',
							width: 96,
							height: 96,
						} )
					);
				}
				removeButton().removeAttr( 'hidden' );
			} );
			frame.open();
		} );

		removeButton().on( 'click', function ( event ) {
			event.preventDefault();
			input().val( '' );
			preview().html( original );
			$( this ).attr( 'hidden', '' );
		} );
	} );
} )( window.jQuery );
