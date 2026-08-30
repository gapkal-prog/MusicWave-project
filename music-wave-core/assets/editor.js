( function ( wp, document ) {
	'use strict';

	var el = wp.element.createElement;
	var render = wp.element.render;
	var useEffect = wp.element.useEffect;
	var useState = wp.element.useState;
	var Button = wp.components.Button;
	var Notice = wp.components.Notice;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var __ = wp.i18n.__;

	function numericValue( value ) {
		var numeric = parseInt( value || '0', 10 );
		return isFinite( numeric ) && numeric > 0 ? numeric : 0;
	}

	function humanDuration( seconds ) {
		var numeric = numericValue( seconds );
		return numeric
			? Math.floor( numeric / 60 ) +
					':' +
					( '0' + ( numeric % 60 ) ).slice( -2 )
			: '';
	}

	function humanFileSize( bytes ) {
		var numeric = numericValue( bytes );
		var units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
		var unit = 0;

		while ( numeric >= 1024 && unit < units.length - 1 ) {
			numeric /= 1024;
			unit += 1;
		}

		return numeric
			? ( numeric >= 10 || unit === 0
					? Math.round( numeric )
					: numeric.toFixed( 1 ) ) +
					' ' +
					units[ unit ]
			: '';
	}

	function extensionFromAsset( asset ) {
		var value = asset.name || asset.file_name || asset.asset_id || '';
		var match = value.toLowerCase().match( /\.([a-z0-9]+)(?:$|\?)/ );
		return match ? match[ 1 ] : '';
	}

	function qualitySummary( asset ) {
		var parts = [];
		var format = (
			asset.format ||
			extensionFromAsset( asset ) ||
			''
		).toUpperCase();
		var bitrate = numericValue( asset.bitrate );
		var duration = numericValue( asset.duration );
		var size = numericValue( asset.file_size || asset.size );

		if ( format ) {
			parts.push( format );
		}
		if ( bitrate ) {
			parts.push( bitrate + ' kbps' );
		}
		if ( duration ) {
			parts.push( humanDuration( duration ) );
		}
		if ( size ) {
			parts.push( humanFileSize( size ) );
		}

		return parts.join( ' · ' );
	}

	function normalizedKey( value ) {
		return (
			( value || 'download' )
				.toLowerCase()
				.replace( /[^a-z0-9_-]/g, '-' )
				.replace( /-+/g, '-' )
				.replace( /^-|-$/g, '' ) || 'download'
		);
	}

	function uniqueQualityKey( base, assets ) {
		var key = normalizedKey( base );
		var candidate = key;
		var suffix = 2;

		while (
			assets.some( function ( asset ) {
				return normalizedKey( asset.key ) === candidate;
			} )
		) {
			candidate = key + '-' + suffix;
			suffix += 1;
		}

		return candidate;
	}

	function qualityPresets() {
		return [
			{
				label: __( 'Custom / detected', 'music-wave-core' ),
				value: '',
				key: '',
				format: '',
				bitrate: 0,
			},
			{
				label: __( 'MP3 128 kbps', 'music-wave-core' ),
				value: 'mp3-128',
				key: 'mp3-128',
				format: 'mp3',
				bitrate: 128,
			},
			{
				label: __( 'MP3 192 kbps', 'music-wave-core' ),
				value: 'mp3-192',
				key: 'mp3-192',
				format: 'mp3',
				bitrate: 192,
			},
			{
				label: __( 'MP3 320 kbps', 'music-wave-core' ),
				value: 'mp3-320',
				key: 'mp3-320',
				format: 'mp3',
				bitrate: 320,
			},
			{
				label: __( 'AAC 256 kbps', 'music-wave-core' ),
				value: 'aac-256',
				key: 'aac-256',
				format: 'aac',
				bitrate: 256,
			},
			{
				label: __( 'M4A 256 kbps', 'music-wave-core' ),
				value: 'm4a-256',
				key: 'm4a-256',
				format: 'm4a',
				bitrate: 256,
			},
			{
				label: __( 'OGG 320 kbps', 'music-wave-core' ),
				value: 'ogg-320',
				key: 'ogg-320',
				format: 'ogg',
				bitrate: 320,
			},
			{
				label: __( 'FLAC lossless', 'music-wave-core' ),
				value: 'flac',
				key: 'flac',
				format: 'flac',
				bitrate: 0,
			},
			{
				label: __( 'WAV lossless', 'music-wave-core' ),
				value: 'wav',
				key: 'wav',
				format: 'wav',
				bitrate: 0,
			},
			{
				label: __( 'ZIP bundle', 'music-wave-core' ),
				value: 'zip',
				key: 'zip',
				format: 'zip',
				bitrate: 0,
			},
		];
	}

	function matchingQualityPreset( asset ) {
		var format = ( asset.format || '' ).toLowerCase();
		var bitrate = numericValue( asset.bitrate );
		var presets = qualityPresets().filter( function ( preset ) {
			return (
				preset.value &&
				preset.format === format &&
				preset.bitrate === bitrate
			);
		} );

		return presets.length ? presets[ 0 ].value : '';
	}

	function qualityFromAsset( asset, existingAssets ) {
		var format = (
			asset.format ||
			extensionFromAsset( asset ) ||
			'download'
		).toLowerCase();
		var bitrate = numericValue( asset.bitrate );
		var label =
			format === 'download'
				? __( 'Download', 'music-wave-core' )
				: format.toUpperCase();

		if ( bitrate ) {
			label += ' ' + bitrate + ' kbps';
		}

		return {
			key: uniqueQualityKey(
				format + ( bitrate ? '-' + bitrate : '' ),
				existingAssets
			),
			label,
			asset_id: asset.id || asset.asset_id || '',
			file_name: asset.name || asset.file_name || '',
			format: format === 'download' ? '' : format,
			bitrate,
			duration: numericValue( asset.duration ),
			file_size: numericValue( asset.file_size || asset.size ),
		};
	}

	function fileKeyFor( asset ) {
		return asset.file_key || 'main-download';
	}

	function fileLabelFor( asset ) {
		return asset.file_label || __( 'Main download', 'music-wave-core' );
	}

	function fileTitleFromAsset( asset ) {
		var name = asset.file_name || asset.name || '';
		var title = name
			.replace( /\.[a-z0-9]+$/i, '' )
			.replace( /[_-]+/g, ' ' )
			.replace( /\s+/g, ' ' )
			.trim();
		return title || __( 'New downloadable file', 'music-wave-core' );
	}

	function uniqueFileKey( base, assets ) {
		var key = normalizedKey( base );
		var candidate = key;
		var suffix = 2;
		var used = {};

		assets.forEach( function ( asset ) {
			used[ fileKeyFor( asset ) ] = true;
		} );
		while ( used[ candidate ] ) {
			candidate = key + '-' + suffix;
			suffix += 1;
		}

		return candidate;
	}

	function groupedDownloadFiles( assets ) {
		var groups = [];
		var indexes = {};

		assets.forEach( function ( asset, index ) {
			var key = fileKeyFor( asset );
			if ( typeof indexes[ key ] === 'undefined' ) {
				indexes[ key ] = groups.length;
				groups.push( {
					key,
					label: fileLabelFor( asset ),
					assets: [],
				} );
			}
			groups[ indexes[ key ] ].assets.push( {
				asset,
				index,
			} );
		} );

		return groups;
	}

	function updateNativeField( key, value ) {
		var input = document.getElementById( key );
		if ( input ) {
			input.value = value;
			input.dispatchEvent(
				new window.Event( 'change', { bubbles: true } )
			);
		}

		if ( wp.data && wp.data.select && wp.data.dispatch ) {
			var editor = wp.data.select( 'core/editor' );
			if ( ! editor || ! editor.getEditedPostAttribute ) {
				return;
			}
			var meta = editor.getEditedPostAttribute( 'meta' ) || {};
			var next = Object.assign( {}, meta );
			next[ key ] = value;
			wp.data.dispatch( 'core/editor' ).editPost( { meta: next } );
		}
	}

	function DeliveryManager( props ) {
		var releaseId = props.releaseId;
		var isCollection = props.isCollection;
		var privateAssetsState = useState( [] );
		var targetFileState = useState( '' );
		var assetSearchState = useState( '' );
		var assetsState = useState( [] );
		var assetPageState = useState( 1 );
		var assetHasMoreState = useState( false );
		var statusState = useState( '' );
		var privateAssets = privateAssetsState[ 0 ];
		var setPrivateAssets = privateAssetsState[ 1 ];
		var targetFileKey = targetFileState[ 0 ];
		var setTargetFileKey = targetFileState[ 1 ];
		var assetSearch = assetSearchState[ 0 ];
		var setAssetSearch = assetSearchState[ 1 ];
		var assets = assetsState[ 0 ];
		var setAssets = assetsState[ 1 ];
		var assetPage = assetPageState[ 0 ];
		var setAssetPage = assetPageState[ 1 ];
		var assetHasMore = assetHasMoreState[ 0 ];
		var setAssetHasMore = assetHasMoreState[ 1 ];
		var status = statusState[ 0 ];
		var setStatus = statusState[ 1 ];

		useEffect(
			function () {
				wp.apiFetch( {
					path:
						'/music-wave/v1/releases/' +
						releaseId +
						'/download-assets',
				} )
					.then( function ( response ) {
						setPrivateAssets( response.items || [] );
					} )
					.catch( function ( error ) {
						setStatus(
							error && error.message
								? error.message
								: __(
										'Protected files are unavailable.',
										'music-wave-core'
								  )
						);
					} );
			},
			[ releaseId ]
		);

		function updateReleaseDuration( duration ) {
			var field = document.getElementById( 'mw_duration' );
			if ( ! duration || ( field && numericValue( field.value ) ) ) {
				return;
			}

			updateNativeField( 'mw_duration', duration );
			wp.apiFetch( {
				path: '/wp/v2/mw_release/' + releaseId,
				method: 'POST',
				data: { meta: { mw_duration: duration } },
			} )
				.then( function () {
					setStatus(
						__(
							'Release duration was filled from the protected file. Review it before publishing.',
							'music-wave-core'
						)
					);
				} )
				.catch( function () {
					setStatus(
						__(
							'File details were read, but release duration could not be saved automatically.',
							'music-wave-core'
						)
					);
				} );
		}

		function maybeUpdateReleaseDuration( nextAssets, duration ) {
			if (
				! isCollection &&
				groupedDownloadFiles( nextAssets ).length === 1
			) {
				updateReleaseDuration( duration );
			}
		}

		function addQualityFromAsset( asset, targetKey ) {
			var quality = qualityFromAsset( asset, privateAssets );
			var targetGroup = groupedDownloadFiles( privateAssets ).filter(
				function ( group ) {
					return group.key === targetKey;
				}
			)[ 0 ];
			if ( ! quality.asset_id ) {
				return;
			}
			if ( ! targetGroup ) {
				addDownloadFileFromAsset( asset );
				return;
			}

			quality.file_key = targetKey;
			quality.file_label = targetGroup.label;
			var nextAssets = privateAssets.concat( [ quality ] );
			setPrivateAssets( nextAssets );
			maybeUpdateReleaseDuration( nextAssets, quality.duration );
			setStatus(
				__(
					'A quality was added to the selected downloadable file. Review it, then save all changes.',
					'music-wave-core'
				)
			);
		}

		function addDownloadFileFromAsset( asset ) {
			var quality = qualityFromAsset( asset, privateAssets );
			if ( ! quality.asset_id ) {
				return;
			}

			quality.file_key = uniqueFileKey(
				fileTitleFromAsset( quality ),
				privateAssets
			);
			quality.file_label = fileTitleFromAsset( quality );
			var nextAssets = privateAssets.concat( [ quality ] );
			setPrivateAssets( nextAssets );
			setTargetFileKey( quality.file_key );
			maybeUpdateReleaseDuration( nextAssets, quality.duration );
			setStatus(
				__(
					'A new downloadable file was added. Add more qualities to it when needed, then save all changes.',
					'music-wave-core'
				)
			);
		}

		function addSelectedAsset( asset ) {
			if ( targetFileKey ) {
				addQualityFromAsset( asset, targetFileKey );
				return;
			}

			addDownloadFileFromAsset( asset );
		}

		function addProviderAsset() {
			var number = privateAssets.length + 1;
			var fileKey =
				targetFileKey ||
				uniqueFileKey( 'download-file-' + number, privateAssets );
			var selectedGroup = targetFileKey
				? groupedDownloadFiles( privateAssets ).filter(
						function ( group ) {
							return group.key === targetFileKey;
						}
				  )[ 0 ]
				: null;
			var fileLabel = selectedGroup
				? selectedGroup.label
				: __( 'Download file', 'music-wave-core' ) + ' ' + number;
			if ( targetFileKey && ! selectedGroup ) {
				fileKey = uniqueFileKey(
					'download-file-' + number,
					privateAssets
				);
				setTargetFileKey( fileKey );
			}
			var nextAssets = privateAssets.concat( [
				{
					key: uniqueQualityKey(
						'download-' + number,
						privateAssets
					),
					label: __( 'Download', 'music-wave-core' ) + ' ' + number,
					asset_id: '',
					file_name: '',
					file_key: fileKey,
					file_label: fileLabel,
					format: '',
					bitrate: 0,
					duration: 0,
					file_size: 0,
				},
			] );
			setPrivateAssets( nextAssets );
			setTargetFileKey( fileKey );
			setStatus(
				targetFileKey
					? __(
							'Enter a secure provider asset ID for this file quality.',
							'music-wave-core'
					  )
					: __(
							'Enter a secure provider asset ID for the new downloadable file.',
							'music-wave-core'
					  )
			);
		}

		function uploadAsset( event ) {
			var files = event.target.files;
			if ( ! files || ! files.length ) {
				return;
			}

			var formData = new window.FormData();
			formData.append( 'file', files[ 0 ] );
			setStatus(
				__(
					'Uploading protected file and reading its metadata…',
					'music-wave-core'
				)
			);
			wp.apiFetch( {
				path: '/music-wave/v1/protected-assets',
				method: 'POST',
				body: formData,
			} )
				.then( function ( response ) {
					if ( response.asset && response.asset.id ) {
						addSelectedAsset( response.asset );
						setAssets( [ response.asset ].concat( assets ) );
					}
				} )
				.catch( function ( error ) {
					setStatus(
						error && error.message
							? error.message
							: __(
									'The protected file could not be uploaded.',
									'music-wave-core'
							  )
					);
				} );
			event.target.value = '';
		}

		function selectMediaLibraryAsset() {
			if ( ! wp.media ) {
				setStatus(
					__(
						'The WordPress Media Library is unavailable on this screen.',
						'music-wave-core'
					)
				);
				return;
			}

			var frame = wp.media( {
				title: __(
					'Select an audio file from Media Library',
					'music-wave-core'
				),
				button: {
					text: __( 'Copy to protected storage', 'music-wave-core' ),
				},
				library: { type: 'audio' },
				multiple: false,
			} );

			frame.on( 'select', function () {
				var attachment = frame
					.state()
					.get( 'selection' )
					.first()
					.toJSON();
				if ( ! attachment || ! attachment.id ) {
					return;
				}

				setStatus(
					__(
						'Copying the Media Library file to protected storage…',
						'music-wave-core'
					)
				);
				wp.apiFetch( {
					path: '/music-wave/v1/protected-assets/import',
					method: 'POST',
					data: { attachment_id: attachment.id },
				} )
					.then( function ( response ) {
						if ( response.asset && response.asset.id ) {
							addSelectedAsset( response.asset );
							setAssets( [ response.asset ].concat( assets ) );
						}
					} )
					.catch( function ( error ) {
						setStatus(
							error && error.message
								? error.message
								: __(
										'The Media Library file could not be copied to protected storage.',
										'music-wave-core'
								  )
						);
					} );
			} );
			frame.open();
		}

		function loadAssets( loadMore ) {
			var page = loadMore ? assetPage + 1 : 1;
			var path =
				'/music-wave/v1/protected-assets?page=' + page + '&per_page=50';
			if ( assetSearch.trim() ) {
				path += '&search=' + encodeURIComponent( assetSearch.trim() );
			}

			setStatus( '' );
			wp.apiFetch( { path } )
				.then( function ( response ) {
					var items = response.items || [];
					setAssets( loadMore ? assets.concat( items ) : items );
					setAssetPage( page );
					setAssetHasMore( !! response.has_more );
				} )
				.catch( function ( error ) {
					setStatus(
						error && error.message
							? error.message
							: __(
									'Protected files are unavailable.',
									'music-wave-core'
							  )
					);
				} );
		}

		function setQualityValue( index, key, value ) {
			var next = privateAssets.slice();
			next[ index ] = Object.assign( {}, next[ index ], {} );
			next[ index ][ key ] = value;
			setPrivateAssets( next );
		}

		function setDownloadFileLabel( fileKey, value ) {
			var label =
				value || __( 'Untitled downloadable file', 'music-wave-core' );
			var next = privateAssets.map( function ( asset ) {
				if ( fileKeyFor( asset ) !== fileKey ) {
					return asset;
				}
				return Object.assign( {}, asset, {
					file_key: fileKey,
					file_label: label,
				} );
			} );
			setPrivateAssets( next );
		}

		function applyPreset( index, presetValue ) {
			var presets = qualityPresets().filter( function ( preset ) {
				return preset.value === presetValue;
			} );
			if ( ! presets.length || ! presets[ 0 ].value ) {
				return;
			}

			var preset = presets[ 0 ];
			var next = privateAssets.slice();
			var others = next.filter( function ( _, itemIndex ) {
				return itemIndex !== index;
			} );
			next[ index ] = Object.assign( {}, next[ index ], {
				key: uniqueQualityKey( preset.key, others ),
				label: preset.label,
				format: preset.format,
				bitrate: preset.bitrate,
			} );
			setPrivateAssets( next );
		}

		function removeQuality( index ) {
			var next = privateAssets.filter( function ( _, itemIndex ) {
				return itemIndex !== index;
			} );
			setPrivateAssets( next );
			if (
				targetFileKey &&
				! next.some( function ( asset ) {
					return fileKeyFor( asset ) === targetFileKey;
				} )
			) {
				setTargetFileKey( '' );
			}
		}

		function removeDownloadFile( fileKey ) {
			var next = privateAssets.filter( function ( asset ) {
				return fileKeyFor( asset ) !== fileKey;
			} );
			setPrivateAssets( next );
			if ( targetFileKey === fileKey ) {
				setTargetFileKey( '' );
			}
		}

		function validQualities() {
			var keys = {};
			return ! privateAssets.some( function ( asset ) {
				var key = normalizedKey( asset.key );
				if (
					! asset.key ||
					! asset.label ||
					! asset.asset_id ||
					keys[ key ] ||
					/^https?:\/\//i.test( asset.asset_id )
				) {
					return true;
				}
				keys[ key ] = true;
				return false;
			} );
		}

		function normalizedDownloadFiles() {
			return privateAssets.map( function ( asset ) {
				return Object.assign( {}, asset, {
					file_key: fileKeyFor( asset ),
					file_label: fileLabelFor( asset ),
				} );
			} );
		}

		function saveQualities() {
			if ( ! validQualities() ) {
				setStatus(
					__(
						'Each quality needs a unique technical key, customer label, and secure non-public asset ID.',
						'music-wave-core'
					)
				);
				return;
			}

			setStatus(
				__(
					'Saving protected files and download qualities…',
					'music-wave-core'
				)
			);
			wp.apiFetch( {
				path:
					'/music-wave/v1/releases/' + releaseId + '/download-assets',
				method: 'POST',
				data: { assets: normalizedDownloadFiles() },
			} )
				.then( function ( response ) {
					setPrivateAssets( response.items || [] );
					setStatus(
						__(
							'Protected files and download qualities saved.',
							'music-wave-core'
						)
					);
				} )
				.catch( function ( error ) {
					setStatus(
						error && error.message
							? error.message
							: __(
									'The protected files could not be saved.',
									'music-wave-core'
							  )
					);
				} );
		}

		function qualityRow( asset, index ) {
			return el(
				'div',
				{
					className: 'mw-editor-quality',
					key: asset.key + '-' + index,
				},
				el(
					'div',
					{ className: 'mw-editor-quality__heading' },
					el(
						'strong',
						{},
						asset.label ||
							__( 'New download quality', 'music-wave-core' )
					),
					el(
						Button,
						{
							isDestructive: true,
							isSmall: true,
							onClick() {
								removeQuality( index );
							},
						},
						__( 'Remove', 'music-wave-core' )
					)
				),
				el( SelectControl, {
					label: __( 'Quality preset', 'music-wave-core' ),
					help: __(
						'Fills the technical key, customer label, format, and bitrate.',
						'music-wave-core'
					),
					value: matchingQualityPreset( asset ),
					options: qualityPresets().map( function ( preset ) {
						return { label: preset.label, value: preset.value };
					} ),
					onChange( value ) {
						applyPreset( index, value );
					},
				} ),
				el(
					'div',
					{ className: 'mw-editor-grid' },
					el( TextControl, {
						label: __( 'Technical key', 'music-wave-core' ),
						value: asset.key || '',
						onChange( value ) {
							setQualityValue( index, 'key', value );
						},
					} ),
					el( TextControl, {
						label: __( 'Customer label', 'music-wave-core' ),
						value: asset.label || '',
						onChange( value ) {
							setQualityValue( index, 'label', value );
						},
					} ),
					el( TextControl, {
						label: __( 'Format', 'music-wave-core' ),
						value: asset.format || '',
						onChange( value ) {
							setQualityValue( index, 'format', value );
						},
					} ),
					el( TextControl, {
						label: __( 'Bitrate (kbps)', 'music-wave-core' ),
						type: 'number',
						min: 0,
						value: asset.bitrate || '',
						onChange( value ) {
							setQualityValue(
								index,
								'bitrate',
								numericValue( value )
							);
						},
					} ),
					el( TextControl, {
						label: __(
							'File duration in seconds',
							'music-wave-core'
						),
						type: 'number',
						min: 0,
						value: asset.duration || '',
						onChange( value ) {
							setQualityValue(
								index,
								'duration',
								numericValue( value )
							);
						},
					} ),
					el( TextControl, {
						label: __( 'File size in bytes', 'music-wave-core' ),
						type: 'number',
						min: 0,
						value: asset.file_size || '',
						onChange( value ) {
							setQualityValue(
								index,
								'file_size',
								numericValue( value )
							);
						},
					} )
				),
				el( TextControl, {
					label: __(
						'Protected provider asset ID',
						'music-wave-core'
					),
					help: __(
						'Use an opaque ID such as local:album/track.flac. Public URLs are blocked.',
						'music-wave-core'
					),
					value: asset.asset_id || '',
					onChange( value ) {
						setQualityValue( index, 'asset_id', value );
					},
				} ),
				el( TextControl, {
					label: __( 'Stored file name', 'music-wave-core' ),
					help: qualitySummary( asset )
						? __( 'Detected:', 'music-wave-core' ) +
						  ' ' +
						  qualitySummary( asset )
						: __(
								'Private editor reference only.',
								'music-wave-core'
						  ),
					value: asset.file_name || '',
					onChange( value ) {
						setQualityValue( index, 'file_name', value );
					},
				} ),
				numericValue( asset.duration )
					? el(
							Button,
							{
								variant: 'tertiary',
								onClick() {
									updateNativeField(
										'mw_duration',
										numericValue( asset.duration )
									);
									setStatus(
										__(
											'Release duration was set from this file.',
											'music-wave-core'
										)
									);
								},
							},
							__( 'Use this file duration', 'music-wave-core' )
					  )
					: null
			);
		}

		function downloadFileGroup( group ) {
			return el(
				'section',
				{ className: 'mw-editor-download-file', key: group.key },
				el(
					'div',
					{ className: 'mw-editor-download-file__heading' },
					el(
						'div',
						{},
						el( 'strong', {}, group.label ),
						el(
							'span',
							{ className: 'description' },
							group.assets.length === 1
								? __( '1 download quality', 'music-wave-core' )
								: group.assets.length +
										' ' +
										__(
											'download qualities',
											'music-wave-core'
										)
						)
					),
					el(
						Button,
						{
							isDestructive: true,
							isSmall: true,
							onClick() {
								removeDownloadFile( group.key );
							},
						},
						__( 'Remove file', 'music-wave-core' )
					)
				),
				el( TextControl, {
					label: __( 'Downloadable file title', 'music-wave-core' ),
					help: __(
						'This title groups all versions of the same song, episode, or bonus file.',
						'music-wave-core'
					),
					value: group.label,
					onChange( value ) {
						setDownloadFileLabel( group.key, value );
					},
				} ),
				group.assets.map( function ( entry ) {
					return qualityRow( entry.asset, entry.index );
				} )
			);
		}

		return el(
			'section',
			{ className: 'mw-release-manager' },
			el(
				'h2',
				{},
				__( 'Files and download qualities', 'music-wave-core' )
			),
			el(
				'p',
				{ className: 'description' },
				__(
					'Create a downloadable file for every song, episode, or bundle. Then add all available qualities to that file. This keeps albums and podcasts organized without uploading the same audio again.',
					'music-wave-core'
				)
			),
			groupedDownloadFiles( privateAssets ).map( downloadFileGroup ),
			el(
				'div',
				{ className: 'mw-editor-actions' },
				privateAssets.length
					? el( SelectControl, {
							label: __( 'Add next file to', 'music-wave-core' ),
							value: targetFileKey,
							options: [
								{
									label: __(
										'Create a new downloadable file',
										'music-wave-core'
									),
									value: '',
								},
							].concat(
								groupedDownloadFiles( privateAssets ).map(
									function ( group ) {
										return {
											label: group.label,
											value: group.key,
										};
									}
								)
							),
							onChange: setTargetFileKey,
					  } )
					: null,
				el(
					Button,
					{ variant: 'secondary', onClick: addProviderAsset },
					targetFileKey
						? __( 'Add manual quality', 'music-wave-core' )
						: __(
								'Add manual downloadable file',
								'music-wave-core'
						  )
				),
				el(
					'label',
					{
						className:
							'components-button is-secondary mw-editor-upload',
					},
					targetFileKey
						? __( 'Upload a quality', 'music-wave-core' )
						: __( 'Upload a new file', 'music-wave-core' ),
					el( 'input', {
						type: 'file',
						accept: '.mp3,.m4a,.aac,.ogg,.wav,.flac,.zip',
						onChange: uploadAsset,
					} )
				),
				el(
					Button,
					{ variant: 'secondary', onClick: selectMediaLibraryAsset },
					targetFileKey
						? __(
								'Choose a quality from Media Library',
								'music-wave-core'
						  )
						: __(
								'Choose a file from Media Library',
								'music-wave-core'
						  )
				),
				el(
					Button,
					{ variant: 'primary', onClick: saveQualities },
					__( 'Save downloadable files', 'music-wave-core' )
				)
			),
			el(
				'div',
				{ className: 'mw-editor-browser' },
				el( TextControl, {
					label: __(
						'Find files already in protected storage',
						'music-wave-core'
					),
					value: assetSearch,
					onChange: setAssetSearch,
				} ),
				el(
					Button,
					{
						variant: 'secondary',
						onClick() {
							loadAssets( false );
						},
					},
					__( 'Browse protected files', 'music-wave-core' )
				)
			),
			assets.map( function ( asset ) {
				return el(
					'div',
					{ className: 'mw-editor-stored-asset', key: asset.id },
					el(
						'div',
						{},
						el( 'strong', {}, asset.name || asset.id ),
						qualitySummary( asset )
							? el(
									'span',
									{ className: 'description' },
									qualitySummary( asset )
							  )
							: null
					),
					el(
						Button,
						{
							variant: 'secondary',
							onClick() {
								addDownloadFileFromAsset( asset );
							},
						},
						__( 'Add as new downloadable file', 'music-wave-core' )
					),
					privateAssets.length
						? el( SelectControl, {
								label: __(
									'Use as a quality for',
									'music-wave-core'
								),
								hideLabelFromVision: true,
								value: '',
								options: [
									{
										label: __(
											'Add as a quality for…',
											'music-wave-core'
										),
										value: '',
									},
								].concat(
									groupedDownloadFiles( privateAssets ).map(
										function ( group ) {
											return {
												label: group.label,
												value: group.key,
											};
										}
									)
								),
								onChange( value ) {
									if ( value !== '' ) {
										addQualityFromAsset( asset, value );
									}
								},
						  } )
						: null
				);
			} ),
			assetHasMore
				? el(
						Button,
						{
							variant: 'tertiary',
							onClick() {
								loadAssets( true );
							},
						},
						__( 'Load more files', 'music-wave-core' )
				  )
				: null,
			status
				? el( Notice, { status: 'info', isDismissible: false }, status )
				: null
		);
	}

	function CollectionManager( props ) {
		var releaseId = props.releaseId;
		var role = props.role;
		var searchState = useState( '' );
		var resultsState = useState( [] );
		var itemsState = useState( [] );
		var titlesState = useState( {} );
		var statusState = useState( '' );
		var search = searchState[ 0 ];
		var setSearch = searchState[ 1 ];
		var results = resultsState[ 0 ];
		var setResults = resultsState[ 1 ];
		var items = itemsState[ 0 ];
		var setItems = itemsState[ 1 ];
		var titles = titlesState[ 0 ];
		var setTitles = titlesState[ 1 ];
		var status = statusState[ 0 ];
		var setStatus = statusState[ 1 ];
		var itemLabel =
			role === 'episode'
				? __( 'Episode', 'music-wave-core' )
				: __( 'Track / single', 'music-wave-core' );

		useEffect(
			function () {
				wp.apiFetch( {
					path: '/wp/v2/mw_release/' + releaseId + '?context=edit',
				} )
					.then( function ( release ) {
						var saved =
							release.meta &&
							Array.isArray( release.meta.mw_collection_items )
								? release.meta.mw_collection_items
								: [];
						setItems( saved );
					} )
					.catch( function () {
						setStatus(
							__(
								'Collection items could not be loaded.',
								'music-wave-core'
							)
						);
					} );
			},
			[ releaseId ]
		);

		useEffect(
			function () {
				var ids = items
					.map( function ( item ) {
						return item.release_id;
					} )
					.filter( function ( id ) {
						return ! titles[ id ];
					} );
				if ( ! ids.length ) {
					return;
				}

				wp.apiFetch( {
					path:
						'/wp/v2/mw_release?context=edit&include=' +
						ids.join( ',' ) +
						'&per_page=100',
				} ).then( function ( releases ) {
					var next = Object.assign( {}, titles );
					releases.forEach( function ( release ) {
						next[ release.id ] =
							release.title && release.title.rendered
								? release.title.rendered
								: '#' + release.id;
					} );
					setTitles( next );
				} );
			},
			[ items ]
		);

		useEffect(
			function () {
				var query = search.trim();
				if ( query.length < 2 ) {
					setResults( [] );
					return undefined;
				}

				var timer = window.setTimeout( searchCandidates, 250 );
				return function () {
					window.clearTimeout( timer );
				};
			},
			[ search ]
		);

		function normalize( itemsToSave ) {
			return itemsToSave.map( function ( item, index ) {
				return {
					release_id: item.release_id,
					position: index + 1,
					disc: item.disc || null,
					role,
				};
			} );
		}

		function saveItems( itemsToSave ) {
			var normalized = normalize( itemsToSave );
			setStatus( __( 'Saving collection items…', 'music-wave-core' ) );
			wp.apiFetch( {
				path: '/wp/v2/mw_release/' + releaseId,
				method: 'POST',
				data: { meta: { mw_collection_items: normalized } },
			} )
				.then( function ( release ) {
					var saved =
						release.meta &&
						Array.isArray( release.meta.mw_collection_items )
							? release.meta.mw_collection_items
							: normalized;
					setItems( saved );
					setStatus(
						__( 'Collection items saved.', 'music-wave-core' )
					);
				} )
				.catch( function ( error ) {
					setStatus(
						error && error.message
							? error.message
							: __(
									'The collection items could not be saved.',
									'music-wave-core'
							  )
					);
				} );
		}

		function searchCandidates() {
			if ( search.trim().length < 2 ) {
				setResults( [] );
				return;
			}

			wp.apiFetch( {
				path:
					'/music-wave/v1/collection-candidates?collection_id=' +
					releaseId +
					'&role=' +
					role +
					'&search=' +
					encodeURIComponent( search.trim() ),
			} )
				.then( function ( response ) {
					setResults( response.items || [] );
				} )
				.catch( function ( error ) {
					setResults( [] );
					setStatus(
						error && error.message
							? error.message
							: __(
									'No compatible releases could be found.',
									'music-wave-core'
							  )
					);
				} );
		}

		function addItem( item ) {
			if (
				items.some( function ( existing ) {
					return existing.release_id === item.id;
				} )
			) {
				return;
			}

			var nextTitles = Object.assign( {}, titles );
			nextTitles[ item.id ] = item.title;
			setTitles( nextTitles );
			saveItems(
				items.concat( [ { release_id: item.id, role, disc: null } ] )
			);
		}

		function move( index, offset ) {
			var next = items.slice();
			var target = index + offset;
			if ( target < 0 || target >= next.length ) {
				return;
			}

			var moved = next[ index ];
			next[ index ] = next[ target ];
			next[ target ] = moved;
			saveItems( next );
		}

		return el(
			'section',
			{ className: 'mw-release-manager mw-release-manager--collection' },
			el(
				'h2',
				{},
				role === 'episode'
					? __( 'Podcast episodes', 'music-wave-core' )
					: __( 'Tracks and singles', 'music-wave-core' )
			),
			el(
				'p',
				{ className: 'description' },
				__(
					'Search by release title or the protected file name already attached to a Track, Single, or Episode. Add it once to reuse its preview and secure downloads.',
					'music-wave-core'
				)
			),
			el(
				'div',
				{ className: 'mw-editor-browser' },
				el( TextControl, {
					label: __(
						'Search release or file name',
						'music-wave-core'
					),
					help: __(
						'Results update while typing. Enter at least 2 characters.',
						'music-wave-core'
					),
					value: search,
					onChange: setSearch,
					onKeyDown( event ) {
						if ( 'Enter' === event.key ) {
							event.preventDefault();
							searchCandidates();
						}
					},
				} ),
				el(
					Button,
					{ variant: 'secondary', onClick: searchCandidates },
					__( 'Search', 'music-wave-core' )
				)
			),
			results.map( function ( item ) {
				var details = [];
				if ( item.number ) {
					details.push( itemLabel + ' ' + item.number );
				}
				if ( item.duration ) {
					details.push( humanDuration( item.duration ) );
				}
				if ( item.file_names && item.file_names.length ) {
					details.push(
						__( 'File:', 'music-wave-core' ) +
							' ' +
							item.file_names.join( ', ' )
					);
				} else if ( item.matched_post ) {
					details.push(
						__( 'Matched release title', 'music-wave-core' )
					);
				}
				return el(
					'div',
					{ className: 'mw-editor-stored-asset', key: item.id },
					el(
						'div',
						{},
						el( 'strong', {}, item.title ),
						details.length
							? el(
									'span',
									{ className: 'description' },
									details.join( ' · ' )
							  )
							: null
					),
					el(
						Button,
						{
							variant: 'secondary',
							disabled: items.some( function ( existing ) {
								return existing.release_id === item.id;
							} ),
							onClick() {
								addItem( item );
							},
						},
						__( 'Add to collection', 'music-wave-core' )
					)
				);
			} ),
			items.length
				? el(
						'ol',
						{ className: 'mw-editor-collection-list' },
						items.map( function ( item, index ) {
							return el(
								'li',
								{ key: item.release_id },
								el(
									'span',
									{},
									index +
										1 +
										'. ' +
										( titles[ item.release_id ] ||
											'#' + item.release_id )
								),
								el(
									'span',
									{
										className:
											'mw-editor-collection-list__actions',
									},
									el(
										Button,
										{
											isSmall: true,
											disabled: index === 0,
											onClick() {
												move( index, -1 );
											},
										},
										'↑'
									),
									el(
										Button,
										{
											isSmall: true,
											disabled:
												index === items.length - 1,
											onClick() {
												move( index, 1 );
											},
										},
										'↓'
									),
									el(
										Button,
										{
											isSmall: true,
											isDestructive: true,
											onClick() {
												saveItems(
													items.filter(
														function (
															_,
															itemIndex
														) {
															return (
																itemIndex !==
																index
															);
														}
													)
												);
											},
										},
										__( 'Remove', 'music-wave-core' )
									)
								)
							);
						} )
				  )
				: null,
			status
				? el( Notice, { status: 'info', isDismissible: false }, status )
				: null
		);
	}

	function mountManagers() {
		var deliveryRoot = document.getElementById(
			'music-wave-delivery-manager'
		);
		if ( deliveryRoot ) {
			render(
				el( DeliveryManager, {
					releaseId: numericValue(
						deliveryRoot.getAttribute( 'data-release-id' )
					),
					isCollection:
						deliveryRoot.getAttribute( 'data-is-collection' ) ===
						'1',
				} ),
				deliveryRoot
			);
		}

		var collectionRoot = document.getElementById(
			'music-wave-collection-manager'
		);
		if ( collectionRoot ) {
			render(
				el( CollectionManager, {
					releaseId: numericValue(
						collectionRoot.getAttribute( 'data-release-id' )
					),
					role: collectionRoot.getAttribute( 'data-role' ) || 'track',
				} ),
				collectionRoot
			);
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', mountManagers );
	} else {
		mountManagers();
	}
} )( window.wp, document );
