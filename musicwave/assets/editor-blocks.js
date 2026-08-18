(function (blocks, element, blockEditor, components, i18n, serverSideRender, presentationBlocks) {
	'use strict';

	if (!blocks || !element || !blockEditor || !components || !i18n || !serverSideRender || !presentationBlocks) {
		return;
	}

	var createElement = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;
	var inheritedToggleOptions = [
		{ label: __('Use global setting', 'musicwave'), value: 'inherit' },
		{ label: __('Enabled', 'musicwave'), value: 'enabled' },
		{ label: __('Disabled', 'musicwave'), value: 'disabled' }
	];

	function queryFilteringPanel(props) {
		function update(key, value) {
			var attributes = {};
			attributes[key] = value;
			props.setAttributes(attributes);
		}

		return createElement(
			components.PanelBody,
			{ title: __('Query and filtering', 'musicwave'), initialOpen: false },
			createElement(components.SelectControl, {
				label: __('Order by', 'musicwave'),
				value: props.attributes.orderBy || 'date',
				options: [
					{ label: __('Published date', 'musicwave'), value: 'date' },
					{ label: __('Modified date', 'musicwave'), value: 'modified' },
					{ label: __('Title', 'musicwave'), value: 'title' },
					{ label: __('Random', 'musicwave'), value: 'rand' },
					{ label: __('Most viewed', 'musicwave'), value: 'views' }
				],
				onChange: function (value) { update('orderBy', value); }
			}),
			createElement(components.SelectControl, {
				label: __('Direction', 'musicwave'),
				value: props.attributes.order || 'DESC',
				options: [
					{ label: __('Descending', 'musicwave'), value: 'DESC' },
					{ label: __('Ascending', 'musicwave'), value: 'ASC' }
				],
				onChange: function (value) { update('order', value); }
			}),
			createElement(components.SelectControl, {
				label: __('Filter taxonomy', 'musicwave'),
				value: props.attributes.taxonomy || '',
				options: [
					{ label: __('All releases', 'musicwave'), value: '' },
					{ label: __('Artist', 'musicwave'), value: 'mw_artist' },
					{ label: __('Genre', 'musicwave'), value: 'mw_genre' },
					{ label: __('Mood', 'musicwave'), value: 'mw_mood' },
					{ label: __('Release type', 'musicwave'), value: 'mw_release_type' },
					{ label: __('Label', 'musicwave'), value: 'mw_label' }
				],
				onChange: function (value) { update('taxonomy', value); }
			}),
			createElement(components.TextControl, {
				label: __('Term slug', 'musicwave'),
				help: __('Leave empty to include every term.', 'musicwave'),
				value: props.attributes.termSlug || '',
				onChange: function (value) { update('termSlug', value); }
			}),
			createElement(components.TextControl, {
				label: __('Release type (slug)', 'musicwave'),
				help: __('Optional. Filter by a release type term slug, e.g. album, single, podcast_show.', 'musicwave'),
				value: props.attributes.contentType || '',
				onChange: function (value) { update('contentType', value); }
			}),
			createElement(components.TextControl, {
				label: __('Curated release IDs', 'musicwave'),
				help: __('Optional. Comma-separated release IDs override the query and preserve this exact order.', 'musicwave'),
				value: props.attributes.releaseIds || '',
				onChange: function (value) { update('releaseIds', value); }
			})
		);
	}

	function sliderInspector(props) {
		function select(key, label) {
			return createElement(components.SelectControl, {
				key: key,
				label: label,
				value: props.attributes[key] || 'inherit',
				options: inheritedToggleOptions,
				onChange: function (value) {
					var update = {};
					update[key] = value;
					props.setAttributes(update);
				}
			});
		}

		return createElement(
			blockEditor.InspectorControls,
			null,
			createElement(
				components.PanelBody,
				{ title: __('Slider content', 'musicwave'), initialOpen: true },
				createElement(components.TextControl, {
					label: __('Eyebrow', 'musicwave'),
					value: props.attributes.eyebrow || '',
					onChange: function (value) { props.setAttributes({ eyebrow: value }); }
				}),
				createElement(components.TextControl, {
					label: __('Heading', 'musicwave'),
					value: props.attributes.title || '',
					onChange: function (value) { props.setAttributes({ title: value }); }
				}),
				createElement(components.RangeControl, {
					label: __('Releases loaded', 'musicwave'),
					value: props.attributes.itemsToShow || 6,
					min: 3,
					max: 12,
					onChange: function (value) { props.setAttributes({ itemsToShow: value || 6 }); }
				}),
				createElement(components.ToggleControl, {
					label: __('Show excerpts', 'musicwave'),
					checked: !!props.attributes.showExcerpt,
					onChange: function (value) { props.setAttributes({ showExcerpt: !!value }); }
				}),
				createElement(components.ToggleControl, {
					label: __('Show artist', 'musicwave'),
					checked: false !== props.attributes.showArtist,
					onChange: function (value) { props.setAttributes({ showArtist: !!value }); }
				}),
				createElement(components.ToggleControl, {
					label: __('Show date', 'musicwave'),
					checked: !!props.attributes.showDate,
					onChange: function (value) { props.setAttributes({ showDate: !!value }); }
				}),
				createElement(components.ToggleControl, {
					label: __('Show view count', 'musicwave'),
					checked: !!props.attributes.showViews,
					onChange: function (value) { props.setAttributes({ showViews: !!value }); }
				})
			),
			queryFilteringPanel(props),
			createElement(
				components.PanelBody,
				{ title: __('Slider behavior', 'musicwave'), initialOpen: true },
				select('enabled', __('Slider visibility', 'musicwave')),
				select('autoplay', __('Autoplay', 'musicwave')),
				select('loop', __('Loop', 'musicwave')),
				select('pauseOnHover', __('Pause on hover or focus', 'musicwave')),
				select('showArrows', __('Navigation arrows', 'musicwave')),
				select('showDots', __('Pagination dots', 'musicwave')),
				createElement(components.RangeControl, {
					label: __('Autoplay interval (milliseconds)', 'musicwave'),
					value: props.attributes.interval || 5000,
					min: 2000,
					max: 20000,
					step: 500,
					onChange: function (value) { props.setAttributes({ interval: value || 5000 }); }
				})
			)
		);
	}

	function shelfInspector(props) {
		function update(key, value) {
			var attributes = {};
			attributes[key] = value;
			props.setAttributes(attributes);
		}

		function toggle(key, label) {
			return createElement(components.ToggleControl, {
				key: key,
				label: label,
				checked: false !== props.attributes[key],
				onChange: function (value) { update(key, !!value); }
			});
		}

		return createElement(
			blockEditor.InspectorControls,
			null,
			createElement(
				components.PanelBody,
				{ title: __('Shelf content', 'musicwave'), initialOpen: true },
				createElement(components.TextControl, {
					label: __('Eyebrow', 'musicwave'),
					value: props.attributes.eyebrow || '',
					onChange: function (value) { update('eyebrow', value); }
				}),
				createElement(components.TextControl, {
					label: __('Heading', 'musicwave'),
					value: props.attributes.title || '',
					onChange: function (value) { update('title', value); }
				}),
				createElement(components.TextareaControl, {
					label: __('Description', 'musicwave'),
					value: props.attributes.description || '',
					onChange: function (value) { update('description', value); }
				}),
				createElement(components.RangeControl, {
					label: __('Releases to show', 'musicwave'),
					value: props.attributes.itemsToShow || 8,
					min: 1,
					max: 24,
					onChange: function (value) { update('itemsToShow', value || 8); }
				})
			),
			queryFilteringPanel(props),
			createElement(
				components.PanelBody,
				{ title: __('Layout and artwork', 'musicwave'), initialOpen: true },
				createElement(components.SelectControl, {
					label: __('Layout', 'musicwave'),
					value: props.attributes.layout || 'grid',
					options: [
						{ label: __('Responsive grid', 'musicwave'), value: 'grid' },
						{ label: __('Horizontal shelf', 'musicwave'), value: 'scroll' },
						{ label: __('Compact list', 'musicwave'), value: 'list' },
						{ label: __('Editorial feature', 'musicwave'), value: 'feature' }
					],
					onChange: function (value) { update('layout', value); }
				}),
				createElement(components.RangeControl, {
					label: __('Desktop columns', 'musicwave'),
					value: props.attributes.columns || 4,
					min: 2,
					max: 6,
					onChange: function (value) { update('columns', value || 4); }
				}),
				createElement(components.SelectControl, {
					label: __('Artwork shape', 'musicwave'),
					value: props.attributes.imageShape || 'square',
					options: [
						{ label: __('Square', 'musicwave'), value: 'square' },
						{ label: __('Landscape', 'musicwave'), value: 'landscape' },
						{ label: __('Portrait', 'musicwave'), value: 'portrait' },
						{ label: __('Circle', 'musicwave'), value: 'circle' }
					],
					onChange: function (value) { update('imageShape', value); }
				}),
				toggle('showArtwork', __('Show artwork', 'musicwave')),
				toggle('showPlayButton', __('Show play affordance', 'musicwave')),
				toggle('showArtist', __('Show artist', 'musicwave')),
				toggle('showDate', __('Show date', 'musicwave')),
				toggle('showExcerpt', __('Show excerpt', 'musicwave')),
				toggle('showAction', __('Show action link', 'musicwave')),
				createElement(components.TextControl, {
					label: __('Action label', 'musicwave'),
					value: props.attributes.actionLabel || '',
					onChange: function (value) { update('actionLabel', value); }
				})
			),
			('feature' === (props.attributes.layout || 'grid')) ? createElement(
				components.PanelBody,
				{ title: __('Editorial feature', 'musicwave'), initialOpen: true },
				createElement(components.RangeControl, {
					label: __('Featured release ID (0 = first result)', 'musicwave'),
					value: props.attributes.featuredReleaseId || 0,
					min: 0,
					max: 99999,
					onChange: function (value) { update('featuredReleaseId', value || 0); }
				}),
				createElement(components.SelectControl, {
					label: __('Featured body', 'musicwave'),
					value: props.attributes.featuredSource || 'excerpt',
					options: [
						{ label: __('Use excerpt', 'musicwave'), value: 'excerpt' },
						{ label: __('Use description field', 'musicwave'), value: 'custom' },
						{ label: __('No body text', 'musicwave'), value: 'none' }
					],
					onChange: function (value) { update('featuredSource', value); }
				}),
				createElement(components.RangeControl, {
					label: __('Overlay darkness', 'musicwave'),
					value: props.attributes.overlay !== undefined ? props.attributes.overlay : 50,
					min: 0,
					max: 100,
					onChange: function (value) { update('overlay', value !== undefined ? value : 50); }
				}),
				createElement(components.ToggleControl, {
					label: __('Show rank numbers', 'musicwave'),
					checked: !!props.attributes.showRank,
					onChange: function (value) { update('showRank', !!value); }
				}),
				createElement(components.ToggleControl, {
					label: __('Show view count', 'musicwave'),
					checked: !!props.attributes.showViews,
					onChange: function (value) { update('showViews', !!value); }
				}),
				createElement(components.RangeControl, {
					label: __('Side column width (px)', 'musicwave'),
					value: props.attributes.sideColumnWidth || 340,
					min: 200,
					max: 560,
					onChange: function (value) { update('sideColumnWidth', value || 340); }
				})
			) : null,
			('feature' === (props.attributes.layout || 'grid')) ? createElement(
				components.PanelBody,
				{ title: __('Hero title', 'musicwave'), initialOpen: false },
				createElement(components.TextControl, {
					label: __('Title color (hex)', 'musicwave'),
					help: __('e.g. #ffffff', 'musicwave'),
					value: props.attributes.heroTitleColor || '',
					onChange: function (value) { update('heroTitleColor', value); }
				}),
				createElement(components.RangeControl, {
					label: __('Title font size (px, 0 = default)', 'musicwave'),
					value: props.attributes.heroTitleSize || 0,
					min: 0,
					max: 96,
					onChange: function (value) { update('heroTitleSize', value || 0); }
				}),
				createElement(components.TextControl, {
					label: __('Body text color (hex)', 'musicwave'),
					help: __('e.g. #cccccc', 'musicwave'),
					value: props.attributes.heroTextColor || '',
					onChange: function (value) { update('heroTextColor', value); }
				})
			) : null,
			('feature' === (props.attributes.layout || 'grid')) ? createElement(
				components.PanelBody,
				{ title: __('Call-to-action button', 'musicwave'), initialOpen: false },
				createElement(components.TextControl, {
					label: __('Button label', 'musicwave'),
					value: props.attributes.ctaLabel || '',
					onChange: function (value) { update('ctaLabel', value); }
				}),
				createElement(components.SelectControl, {
					label: __('Button style', 'musicwave'),
					value: props.attributes.ctaStyle || 'solid',
					options: [
						{ label: __('Solid fill', 'musicwave'), value: 'solid' },
						{ label: __('Outline', 'musicwave'), value: 'outline' },
						{ label: __('Ghost', 'musicwave'), value: 'ghost' }
					],
					onChange: function (value) { update('ctaStyle', value); }
				}),
				createElement(components.TextControl, {
					label: __('Button background (hex)', 'musicwave'),
					help: __('Leave empty to use theme accent color.', 'musicwave'),
					value: props.attributes.ctaBgColor || '',
					onChange: function (value) { update('ctaBgColor', value); }
				}),
				createElement(components.TextControl, {
					label: __('Button text color (hex)', 'musicwave'),
					help: __('Leave empty to use theme default.', 'musicwave'),
					value: props.attributes.ctaTextColor || '',
					onChange: function (value) { update('ctaTextColor', value); }
				}),
				createElement(components.RangeControl, {
					label: __('Button corner radius (px)', 'musicwave'),
					value: props.attributes.ctaRadius !== undefined ? props.attributes.ctaRadius : 999,
					min: 0,
					max: 999,
					onChange: function (value) { update('ctaRadius', value !== undefined ? value : 999); }
				})
			) : null,
			createElement(
				components.PanelBody,
				{ title: __('Section link', 'musicwave'), initialOpen: false },
				createElement(blockEditor.URLInput, {
					label: __('View-all link', 'musicwave'),
					value: props.attributes.sectionUrl || '',
					onChange: function (value) { update('sectionUrl', value); }
				}),
				createElement(components.TextControl, {
					label: __('View-all label', 'musicwave'),
					value: props.attributes.sectionLinkLabel || '',
					onChange: function (value) { update('sectionLinkLabel', value); }
				})
			)
		);
	}

	presentationBlocks.forEach(function (block) {
		if (blocks.getBlockType(block.name)) {
			return;
		}

		blocks.registerBlockType(block.name, {
			apiVersion: 3,
			title: block.title,
			description: block.description,
			category: 'widgets',
			icon: block.icon,
			attributes: block.attributes || {},
			supports: block.supports || {},
			edit: function (props) {
				var preview = createElement(serverSideRender, {
					block: block.name,
					attributes: props.attributes,
					EmptyResponsePlaceholder: function () {
						return createElement(components.Placeholder, {
							icon: block.icon,
							label: block.title,
							instructions: __('Add published releases or enable this block to display its live preview.', 'musicwave')
						});
					}
				});

				if ('musicwave/release-slider' === block.name) {
					return createElement(Fragment, null, sliderInspector(props), preview);
				}

				if ('musicwave/release-shelf' === block.name) {
					return createElement(Fragment, null, shelfInspector(props), preview);
				}

				return preview;
			},
			save: function () {
				return null;
			}
		});
	});
})(
	window.wp && window.wp.blocks,
	window.wp && window.wp.element,
	window.wp && window.wp.blockEditor,
	window.wp && window.wp.components,
	window.wp && window.wp.i18n,
	window.wp && window.wp.serverSideRender,
	window.musicwavePresentationBlocks || []
);
