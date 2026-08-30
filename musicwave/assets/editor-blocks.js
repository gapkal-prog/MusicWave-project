( function (
	blocks,
	element,
	blockEditor,
	components,
	i18n,
	serverSideRender,
	presentationBlocks
) {
	'use strict';

	if (
		! blocks ||
		! element ||
		! blockEditor ||
		! components ||
		! i18n ||
		! serverSideRender ||
		! presentationBlocks
	) {
		return;
	}

	var createElement = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;
	var inheritedToggleOptions = [
		{ label: __( 'Use global setting', 'musicwave' ), value: 'inherit' },
		{ label: __( 'Enabled', 'musicwave' ), value: 'enabled' },
		{ label: __( 'Disabled', 'musicwave' ), value: 'disabled' },
	];

	function queryFilteringPanel( props ) {
		function update( key, value ) {
			var attributes = {};
			attributes[ key ] = value;
			props.setAttributes( attributes );
		}

		// Help texts are deliberately verbose so a non-technical buyer never needs to open documentation.
		return createElement(
			components.PanelBody,
			{
				title: __( 'Query and filtering', 'musicwave' ),
				initialOpen: false,
			},
			createElement( components.SelectControl, {
				label: __( 'Order by', 'musicwave' ),
				help: __(
					'How releases are sorted before they are displayed.',
					'musicwave'
				),
				value: props.attributes.orderBy || 'date',
				options: [
					{
						label: __( 'Published date', 'musicwave' ),
						value: 'date',
					},
					{
						label: __( 'Modified date', 'musicwave' ),
						value: 'modified',
					},
					{ label: __( 'Title', 'musicwave' ), value: 'title' },
					{ label: __( 'Random', 'musicwave' ), value: 'rand' },
					{ label: __( 'Most viewed', 'musicwave' ), value: 'views' },
				],
				onChange( value ) {
					update( 'orderBy', value );
				},
			} ),
			createElement( components.SelectControl, {
				label: __( 'Direction', 'musicwave' ),
				value: props.attributes.order || 'DESC',
				options: [
					{ label: __( 'Descending', 'musicwave' ), value: 'DESC' },
					{ label: __( 'Ascending', 'musicwave' ), value: 'ASC' },
				],
				onChange( value ) {
					update( 'order', value );
				},
			} ),
			createElement( components.SelectControl, {
				label: __( 'Filter taxonomy', 'musicwave' ),
				help: __(
					'Limit the shelf/slider to one taxonomy, or leave on All releases.',
					'musicwave'
				),
				value: props.attributes.taxonomy || '',
				options: [
					{ label: __( 'All releases', 'musicwave' ), value: '' },
					{ label: __( 'Artist', 'musicwave' ), value: 'mw_artist' },
					{ label: __( 'Genre', 'musicwave' ), value: 'mw_genre' },
					{ label: __( 'Mood', 'musicwave' ), value: 'mw_mood' },
					{
						label: __( 'Release type', 'musicwave' ),
						value: 'mw_release_type',
					},
					{ label: __( 'Label', 'musicwave' ), value: 'mw_label' },
				],
				onChange( value ) {
					update( 'taxonomy', value );
				},
			} ),
			createElement( components.TextControl, {
				label: __( 'Term slug', 'musicwave' ),
				help: __(
					'Optional. Copy the slug from WordPress → MusicWave → Artists / Genres / Moods list (the slug column). Leave empty to include every term. Example: pop or lo-fi.',
					'musicwave'
				),
				placeholder: __( 'e.g. pop, lo-fi, hip-hop', 'musicwave' ),
				value: props.attributes.termSlug || '',
				onChange( value ) {
					update( 'termSlug', value );
				},
			} ),
			createElement( components.TextControl, {
				label: __( 'Release type (slug)', 'musicwave' ),
				help: __(
					'Optional. Filter by a release-type slug such as album, single, ep, podcast_show, podcast_episode. Leave empty to include all types. Found under MusicWave → Release Types.',
					'musicwave'
				),
				placeholder: __(
					'e.g. album, single, podcast_show',
					'musicwave'
				),
				value: props.attributes.contentType || '',
				onChange( value ) {
					update( 'contentType', value );
				},
			} ),
			createElement( components.TextControl, {
				label: __( 'Curated release IDs', 'musicwave' ),
				help: __(
					'Optional. Paste comma-separated release IDs to fix an exact order (e.g. 12, 45, 78). Find the ID by hovering a release title in MusicWave → Releases — the ID shows in the link preview. Overrides all other filters.',
					'musicwave'
				),
				placeholder: __( 'e.g. 12, 45, 78', 'musicwave' ),
				value: props.attributes.releaseIds || '',
				onChange( value ) {
					update( 'releaseIds', value );
				},
			} )
		);
	}

	function sliderInspector( props ) {
		function select( key, label ) {
			return createElement( components.SelectControl, {
				key,
				label,
				value: props.attributes[ key ] || 'inherit',
				options: inheritedToggleOptions,
				onChange( value ) {
					var update = {};
					update[ key ] = value;
					props.setAttributes( update );
				},
			} );
		}

		return createElement(
			blockEditor.InspectorControls,
			null,
			createElement(
				components.PanelBody,
				{
					title: __( 'Slider content', 'musicwave' ),
					initialOpen: true,
				},
				createElement( components.TextControl, {
					label: __( 'Eyebrow', 'musicwave' ),
					value: props.attributes.eyebrow || '',
					onChange( value ) {
						props.setAttributes( { eyebrow: value } );
					},
				} ),
				createElement( components.TextControl, {
					label: __( 'Heading', 'musicwave' ),
					value: props.attributes.title || '',
					onChange( value ) {
						props.setAttributes( { title: value } );
					},
				} ),
				createElement( components.RangeControl, {
					label: __( 'Releases loaded', 'musicwave' ),
					value: props.attributes.itemsToShow || 6,
					min: 3,
					max: 12,
					onChange( value ) {
						props.setAttributes( { itemsToShow: value || 6 } );
					},
				} ),
				createElement( components.ToggleControl, {
					label: __( 'Show excerpts', 'musicwave' ),
					checked: !! props.attributes.showExcerpt,
					onChange( value ) {
						props.setAttributes( { showExcerpt: !! value } );
					},
				} ),
				createElement( components.ToggleControl, {
					label: __( 'Show artist', 'musicwave' ),
					checked: false !== props.attributes.showArtist,
					onChange( value ) {
						props.setAttributes( { showArtist: !! value } );
					},
				} ),
				createElement( components.ToggleControl, {
					label: __( 'Show date', 'musicwave' ),
					checked: !! props.attributes.showDate,
					onChange( value ) {
						props.setAttributes( { showDate: !! value } );
					},
				} ),
				createElement( components.ToggleControl, {
					label: __( 'Show view count', 'musicwave' ),
					checked: !! props.attributes.showViews,
					onChange( value ) {
						props.setAttributes( { showViews: !! value } );
					},
				} )
			),
			queryFilteringPanel( props ),
			createElement(
				components.PanelBody,
				{
					title: __( 'Slider behavior', 'musicwave' ),
					initialOpen: true,
				},
				select( 'enabled', __( 'Slider visibility', 'musicwave' ) ),
				select( 'autoplay', __( 'Autoplay', 'musicwave' ) ),
				select( 'loop', __( 'Loop', 'musicwave' ) ),
				select(
					'pauseOnHover',
					__( 'Pause on hover or focus', 'musicwave' )
				),
				select( 'showArrows', __( 'Navigation arrows', 'musicwave' ) ),
				select( 'showDots', __( 'Pagination dots', 'musicwave' ) ),
				createElement( components.RangeControl, {
					label: __(
						'Autoplay interval (milliseconds)',
						'musicwave'
					),
					value: props.attributes.interval || 5000,
					min: 2000,
					max: 20000,
					step: 500,
					onChange( value ) {
						props.setAttributes( { interval: value || 5000 } );
					},
				} )
			)
		);
	}

	function shelfInspector( props ) {
		function update( key, value ) {
			var attributes = {};
			attributes[ key ] = value;
			props.setAttributes( attributes );
		}

		function toggle( key, label ) {
			return createElement( components.ToggleControl, {
				key,
				label,
				checked: false !== props.attributes[ key ],
				onChange( value ) {
					update( key, !! value );
				},
			} );
		}

		return createElement(
			blockEditor.InspectorControls,
			null,
			createElement(
				components.PanelBody,
				{
					title: __( 'Shelf content', 'musicwave' ),
					initialOpen: true,
				},
				createElement( components.SelectControl, {
					label: __( 'Content source', 'musicwave' ),
					help: __(
						'Switch the shelf between catalog releases and community public playlists.',
						'musicwave'
					),
					value: props.attributes.source || 'releases',
					options: [
						{
							label: __( 'Releases', 'musicwave' ),
							value: 'releases',
						},
						{
							label: __( 'Public playlists', 'musicwave' ),
							value: 'playlists',
						},
					],
					onChange( value ) {
						update( 'source', value );
					},
				} ),
				createElement( components.TextControl, {
					label: __( 'Eyebrow', 'musicwave' ),
					value: props.attributes.eyebrow || '',
					onChange( value ) {
						update( 'eyebrow', value );
					},
				} ),
				createElement( components.TextControl, {
					label: __( 'Heading', 'musicwave' ),
					value: props.attributes.title || '',
					onChange( value ) {
						update( 'title', value );
					},
				} ),
				createElement( components.TextareaControl, {
					label: __( 'Description', 'musicwave' ),
					value: props.attributes.description || '',
					onChange( value ) {
						update( 'description', value );
					},
				} ),
				createElement( components.RangeControl, {
					label:
						'playlists' ===
						( props.attributes.source || 'releases' )
							? __( 'Playlists to show', 'musicwave' )
							: __( 'Releases to show', 'musicwave' ),
					value: props.attributes.itemsToShow || 8,
					min: 1,
					max: 24,
					onChange( value ) {
						update( 'itemsToShow', value || 8 );
					},
				} )
			),
			'playlists' === ( props.attributes.source || 'releases' )
				? createElement(
						components.PanelBody,
						{
							title: __( 'Playlist query', 'musicwave' ),
							initialOpen: true,
						},
						createElement( components.SelectControl, {
							label: __( 'Order playlists by', 'musicwave' ),
							value:
								props.attributes.playlistOrderBy ||
								'updated_at',
							options: [
								{
									label: __(
										'Recently updated',
										'musicwave'
									),
									value: 'updated_at',
								},
								{
									label: __( 'Newest first', 'musicwave' ),
									value: 'created_at',
								},
								{
									label: __( 'Title', 'musicwave' ),
									value: 'title',
								},
							],
							onChange( value ) {
								update( 'playlistOrderBy', value );
							},
						} ),
						createElement( components.TextControl, {
							label: __( 'Playlist search filter', 'musicwave' ),
							help: __(
								'Optional. Only show public playlists whose title matches this text.',
								'musicwave'
							),
							value: props.attributes.playlistSearch || '',
							onChange( value ) {
								update( 'playlistSearch', value );
							},
						} )
				  )
				: queryFilteringPanel( props ),
			createElement(
				components.PanelBody,
				{
					title: __( 'Layout and artwork', 'musicwave' ),
					initialOpen: true,
				},
				createElement( components.SelectControl, {
					label: __( 'Layout', 'musicwave' ),
					value: props.attributes.layout || 'grid',
					options: [
						{
							label: __( 'Responsive grid', 'musicwave' ),
							value: 'grid',
						},
						{
							label: __( 'Horizontal shelf', 'musicwave' ),
							value: 'scroll',
						},
						{
							label: __( 'Compact list', 'musicwave' ),
							value: 'list',
						},
						{
							label: __( 'Editorial feature', 'musicwave' ),
							value: 'feature',
						},
					],
					onChange( value ) {
						update( 'layout', value );
					},
				} ),
				createElement( components.RangeControl, {
					label: __( 'Desktop columns', 'musicwave' ),
					value: props.attributes.columns || 4,
					min: 2,
					max: 6,
					onChange( value ) {
						update( 'columns', value || 4 );
					},
				} ),
				createElement( components.SelectControl, {
					label: __( 'Artwork shape', 'musicwave' ),
					value: props.attributes.imageShape || 'square',
					options: [
						{ label: __( 'Square', 'musicwave' ), value: 'square' },
						{
							label: __( 'Landscape', 'musicwave' ),
							value: 'landscape',
						},
						{
							label: __( 'Portrait', 'musicwave' ),
							value: 'portrait',
						},
						{ label: __( 'Circle', 'musicwave' ), value: 'circle' },
					],
					onChange( value ) {
						update( 'imageShape', value );
					},
				} ),
				toggle( 'showArtwork', __( 'Show artwork', 'musicwave' ) ),
				toggle(
					'showPlayButton',
					__( 'Show play affordance', 'musicwave' )
				),
				toggle( 'showArtist', __( 'Show artist', 'musicwave' ) ),
				toggle( 'showDate', __( 'Show date', 'musicwave' ) ),
				toggle( 'showExcerpt', __( 'Show excerpt', 'musicwave' ) ),
				toggle( 'showAction', __( 'Show action link', 'musicwave' ) ),
				createElement( components.TextControl, {
					label: __( 'Action label', 'musicwave' ),
					value: props.attributes.actionLabel || '',
					onChange( value ) {
						update( 'actionLabel', value );
					},
				} )
			),
			'feature' === ( props.attributes.layout || 'grid' )
				? createElement(
						components.PanelBody,
						{
							title: __( 'Editorial feature', 'musicwave' ),
							initialOpen: true,
						},
						createElement( components.RangeControl, {
							label: __(
								'Featured release ID (0 = first result)',
								'musicwave'
							),
							value: props.attributes.featuredReleaseId || 0,
							min: 0,
							max: 99999,
							onChange( value ) {
								update( 'featuredReleaseId', value || 0 );
							},
						} ),
						createElement( components.SelectControl, {
							label: __( 'Featured body', 'musicwave' ),
							value: props.attributes.featuredSource || 'excerpt',
							options: [
								{
									label: __( 'Use excerpt', 'musicwave' ),
									value: 'excerpt',
								},
								{
									label: __(
										'Use description field',
										'musicwave'
									),
									value: 'custom',
								},
								{
									label: __( 'No body text', 'musicwave' ),
									value: 'none',
								},
							],
							onChange( value ) {
								update( 'featuredSource', value );
							},
						} ),
						createElement( components.RangeControl, {
							label: __( 'Overlay darkness', 'musicwave' ),
							value:
								props.attributes.overlay !== undefined
									? props.attributes.overlay
									: 50,
							min: 0,
							max: 100,
							onChange( value ) {
								update(
									'overlay',
									value !== undefined ? value : 50
								);
							},
						} ),
						createElement( components.ToggleControl, {
							label: __( 'Show rank numbers', 'musicwave' ),
							checked: !! props.attributes.showRank,
							onChange( value ) {
								update( 'showRank', !! value );
							},
						} ),
						createElement( components.ToggleControl, {
							label: __( 'Show view count', 'musicwave' ),
							checked: !! props.attributes.showViews,
							onChange( value ) {
								update( 'showViews', !! value );
							},
						} ),
						createElement( components.RangeControl, {
							label: __( 'Side column width (px)', 'musicwave' ),
							value: props.attributes.sideColumnWidth || 340,
							min: 200,
							max: 560,
							onChange( value ) {
								update( 'sideColumnWidth', value || 340 );
							},
						} )
				  )
				: null,
			'feature' === ( props.attributes.layout || 'grid' )
				? createElement(
						components.PanelBody,
						{
							title: __( 'Hero title', 'musicwave' ),
							initialOpen: false,
						},
						createElement( components.TextControl, {
							label: __( 'Title color (hex)', 'musicwave' ),
							help: __( 'e.g. #ffffff', 'musicwave' ),
							value: props.attributes.heroTitleColor || '',
							onChange( value ) {
								update( 'heroTitleColor', value );
							},
						} ),
						createElement( components.RangeControl, {
							label: __(
								'Title font size (px, 0 = default)',
								'musicwave'
							),
							value: props.attributes.heroTitleSize || 0,
							min: 0,
							max: 96,
							onChange( value ) {
								update( 'heroTitleSize', value || 0 );
							},
						} ),
						createElement( components.TextControl, {
							label: __( 'Body text color (hex)', 'musicwave' ),
							help: __( 'e.g. #cccccc', 'musicwave' ),
							value: props.attributes.heroTextColor || '',
							onChange( value ) {
								update( 'heroTextColor', value );
							},
						} )
				  )
				: null,
			'feature' === ( props.attributes.layout || 'grid' )
				? createElement(
						components.PanelBody,
						{
							title: __( 'Call-to-action button', 'musicwave' ),
							initialOpen: false,
						},
						createElement( components.TextControl, {
							label: __( 'Button label', 'musicwave' ),
							value: props.attributes.ctaLabel || '',
							onChange( value ) {
								update( 'ctaLabel', value );
							},
						} ),
						createElement( components.SelectControl, {
							label: __( 'Button style', 'musicwave' ),
							value: props.attributes.ctaStyle || 'solid',
							options: [
								{
									label: __( 'Solid fill', 'musicwave' ),
									value: 'solid',
								},
								{
									label: __( 'Outline', 'musicwave' ),
									value: 'outline',
								},
								{
									label: __( 'Ghost', 'musicwave' ),
									value: 'ghost',
								},
							],
							onChange( value ) {
								update( 'ctaStyle', value );
							},
						} ),
						createElement( components.TextControl, {
							label: __( 'Button background (hex)', 'musicwave' ),
							help: __(
								'Leave empty to use theme accent color.',
								'musicwave'
							),
							value: props.attributes.ctaBgColor || '',
							onChange( value ) {
								update( 'ctaBgColor', value );
							},
						} ),
						createElement( components.TextControl, {
							label: __( 'Button text color (hex)', 'musicwave' ),
							help: __(
								'Leave empty to use theme default.',
								'musicwave'
							),
							value: props.attributes.ctaTextColor || '',
							onChange( value ) {
								update( 'ctaTextColor', value );
							},
						} ),
						createElement( components.RangeControl, {
							label: __(
								'Button corner radius (px)',
								'musicwave'
							),
							value:
								props.attributes.ctaRadius !== undefined
									? props.attributes.ctaRadius
									: 999,
							min: 0,
							max: 999,
							onChange( value ) {
								update(
									'ctaRadius',
									value !== undefined ? value : 999
								);
							},
						} )
				  )
				: null,
			createElement(
				components.PanelBody,
				{
					title: __( 'Section link', 'musicwave' ),
					initialOpen: false,
				},
				createElement( blockEditor.URLInput, {
					label: __( 'View-all link', 'musicwave' ),
					value: props.attributes.sectionUrl || '',
					onChange( value ) {
						update( 'sectionUrl', value );
					},
				} ),
				createElement( components.TextControl, {
					label: __( 'View-all label', 'musicwave' ),
					value: props.attributes.sectionLinkLabel || '',
					onChange( value ) {
						update( 'sectionLinkLabel', value );
					},
				} )
			)
		);
	}

	presentationBlocks.forEach( function ( block ) {
		if ( blocks.getBlockType( block.name ) ) {
			return;
		}

		blocks.registerBlockType( block.name, {
			apiVersion: 3,
			title: block.title,
			description: block.description,
			category: 'widgets',
			icon: block.icon,
			attributes: block.attributes || {},
			supports: block.supports || {},
			edit( props ) {
				var preview = createElement( serverSideRender, {
					block: block.name,
					attributes: props.attributes,
					EmptyResponsePlaceholder() {
						return createElement( components.Placeholder, {
							icon: block.icon,
							label: block.title,
							instructions: __(
								'Add published releases or enable this block to display its live preview.',
								'musicwave'
							),
						} );
					},
				} );

				if ( 'musicwave/release-slider' === block.name ) {
					return createElement(
						Fragment,
						null,
						sliderInspector( props ),
						preview
					);
				}

				if ( 'musicwave/release-shelf' === block.name ) {
					return createElement(
						Fragment,
						null,
						shelfInspector( props ),
						preview
					);
				}

				return preview;
			},
			save() {
				return null;
			},
		} );
	} );
} )(
	window.wp && window.wp.blocks,
	window.wp && window.wp.element,
	window.wp && window.wp.blockEditor,
	window.wp && window.wp.components,
	window.wp && window.wp.i18n,
	window.wp && window.wp.serverSideRender,
	window.musicwavePresentationBlocks || []
);
