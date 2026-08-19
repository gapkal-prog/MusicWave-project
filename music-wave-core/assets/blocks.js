/**
 * Editor counterparts for the PHP-rendered MusicWave blocks.
 *
 * @package ManaCore\MusicWave\Core
 */
(function (blocks, element, blockEditor, components, i18n, serverSideRender, dynamicBlocks) {
	'use strict';

	if (!blocks || !element || !blockEditor || !components || !i18n || !serverSideRender || !dynamicBlocks) {
		return;
	}

	var createElement = element.createElement;
	var Fragment = element.Fragment;
	var useSelect = window.wp && window.wp.data && window.wp.data.useSelect ? window.wp.data.useSelect : null;
	var __ = i18n.__;

	var fieldConfig = {
		'music-wave/release-meta': {
			releaseId: true,
			compact: true,
			groups: [
				{ title: __('Fields', 'music-wave-core'), controls: [
					['toggle', 'showCatalogNumber', __('Show catalog number', 'music-wave-core'), true],
					['toggle', 'showReleaseDate', __('Show release date', 'music-wave-core'), true],
					['toggle', 'showDuration', __('Show duration', 'music-wave-core'), true],
					['toggle', 'showBpm', __('Show BPM', 'music-wave-core'), true],
					['toggle', 'showKey', __('Show musical key', 'music-wave-core'), true],
					['toggle', 'showArtist', __('Show artist', 'music-wave-core'), true],
					['toggle', 'showGenre', __('Show genre', 'music-wave-core'), true],
					['toggle', 'showMood', __('Show mood', 'music-wave-core'), false],
					['toggle', 'showReleaseType', __('Show release type', 'music-wave-core'), false],
					['toggle', 'showLibraryButton', __('Show add-to-library button', 'music-wave-core'), true]
				], help: __('Fields without a value on the selected release are hidden automatically.', 'music-wave-core') },
				{ title: __('Layout', 'music-wave-core'), controls: [
					['select', 'layout', __('Metadata layout', 'music-wave-core'), [
						['grid', __('Grid (cards of label and value)', 'music-wave-core')],
						['inline', __('Inline (one flowing row)', 'music-wave-core')],
						['stack', __('Stacked rows (label beside value)', 'music-wave-core')]
					], __('Inline and Stacked rows are also available in the block Styles panel.', 'music-wave-core')],
					['toggle', 'showLabels', __('Show field labels', 'music-wave-core'), true, __('Hidden labels stay available to screen readers.', 'music-wave-core')],
					['toggle', 'linkTerms', __('Link artists, genres, moods, and types to their archives', 'music-wave-core'), false]
				] }
			]
		},
		'music-wave/access-panel': {
			releaseId: true,
			groups: [
				{ title: __('Display', 'music-wave-core'), controls: [
					['select', 'layout', __('Panel layout', 'music-wave-core'), [
						['banner', __('Banner (message and action in one row)', 'music-wave-core')],
						['stack', __('Stacked (action below the message)', 'music-wave-core')]
					]],
					['toggle', 'showWhenGranted', __('Show the granted state', 'music-wave-core'), true, __('When off, the block stays hidden for visitors who already have access.', 'music-wave-core')]
				] },
				{ title: __('Message overrides', 'music-wave-core'), controls: [
					['text', 'grantedMessage', __('Access granted message', 'music-wave-core'), ''],
					['text', 'restrictedMessage', __('Restricted message', 'music-wave-core'), ''],
					['text', 'purchaseMessage', __('Purchase message', 'music-wave-core'), ''],
					['text', 'purchaseCtaLabel', __('Purchase button label', 'music-wave-core'), ''],
					['text', 'membershipMessage', __('Membership message', 'music-wave-core'), ''],
					['text', 'membershipCtaLabel', __('Membership button label', 'music-wave-core'), ''],
					['text', 'membershipCtaUrl', __('Membership button URL', 'music-wave-core'), '']
				], help: __('Leave empty to use the global MusicWave message or setting.', 'music-wave-core') }
			]
		},
		'music-wave/release-credits': {
			releaseId: true,
			groups: [
				{ title: __('Content', 'music-wave-core'), controls: [
					['text', 'heading', __('Section heading', 'music-wave-core'), ''],
					['toggle', 'showHeading', __('Show section heading', 'music-wave-core'), true],
					['toggle', 'showRole', __('Show credit roles', 'music-wave-core'), true],
					['toggle', 'groupByRole', __('Group credits by role', 'music-wave-core'), false]
				] },
				{ title: __('Layout', 'music-wave-core'), controls: [
					['select', 'layout', __('Credits layout', 'music-wave-core'), [
						['list', __('List', 'music-wave-core')],
						['grid', __('Grid', 'music-wave-core')],
						['inline', __('Inline', 'music-wave-core')]
					]]
				] }
			]
		},
		'music-wave/collection-list': {
			releaseId: true,
			groups: [
				{ title: __('Content', 'music-wave-core'), controls: [
					['text', 'heading', __('Section heading', 'music-wave-core'), ''],
					['toggle', 'showHeading', __('Show section heading', 'music-wave-core'), true],
					['toggle', 'showPosition', __('Show track numbers', 'music-wave-core'), true],
					['toggle', 'showArtwork', __('Show track artwork', 'music-wave-core'), false],
					['toggle', 'showDuration', __('Show track duration', 'music-wave-core'), true],
					['toggle', 'showTotalDuration', __('Show total running time', 'music-wave-core'), false],
					['toggle', 'groupByDisc', __('Group tracks by disc', 'music-wave-core'), false, __('Applies when every track has a disc number and the collection spans multiple discs.', 'music-wave-core')]
				] },
				{ title: __('Track actions', 'music-wave-core'), controls: [
					['toggle', 'showPreview', __('Show preview buttons', 'music-wave-core'), true],
					['toggle', 'showDownload', __('Show secure download buttons', 'music-wave-core'), true]
				] }
			]
		},
		'music-wave/catalog-filters': {
			groups: [
				{ title: __('Filter fields', 'music-wave-core'), controls: [
					['toggle', 'showSearch', __('Show search field', 'music-wave-core'), true],
					['toggle', 'showArtistFilter', __('Show artist filter', 'music-wave-core'), true],
					['toggle', 'showGenreFilter', __('Show genre filter', 'music-wave-core'), true],
					['toggle', 'showMoodFilter', __('Show mood filter', 'music-wave-core'), true],
					['toggle', 'showTypeFilter', __('Show release type filter', 'music-wave-core'), true],
					['toggle', 'showSort', __('Show sort selector', 'music-wave-core'), true],
					['toggle', 'showReset', __('Show reset link', 'music-wave-core'), true]
				], help: __('Dropdowns without published terms are hidden automatically.', 'music-wave-core') },
				{ title: __('Layout and labels', 'music-wave-core'), controls: [
					['select', 'layout', __('Filters layout', 'music-wave-core'), [
						['inline', __('Inline bar', 'music-wave-core')],
						['stacked', __('Stacked full-width rows', 'music-wave-core')]
					], __('Stacked is also available in the block Styles panel.', 'music-wave-core')],
					['range', 'maxTerms', __('Options per filter', 'music-wave-core'), 10, 200, __('Limits how many terms each dropdown lists.', 'music-wave-core'), 50],
					['text', 'searchPlaceholder', __('Search placeholder', 'music-wave-core'), ''],
					['text', 'submitLabel', __('Apply button label', 'music-wave-core'), ''],
					['text', 'resetLabel', __('Reset link label', 'music-wave-core'), '']
				], help: __('Leave labels empty to use the translated defaults. The default sort follows the MusicWave archive setting.', 'music-wave-core') }
			]
		},
		'music-wave/catalog-results': {
			toggles: [
				['showCount', __('Show result count', 'music-wave-core'), true, __('Shows how many releases match the current filters.', 'music-wave-core')],
				['showChips', __('Show active filter chips', 'music-wave-core'), true, __('Each chip links back to the catalog without that filter.', 'music-wave-core')]
			]
		},
		'music-wave/preview-player': {
			releaseId: true,
			textFields: [['label', __('Button label', 'music-wave-core')]],
			groups: [
				{ title: __('Appearance', 'music-wave-core'), controls: [
					['select', 'style', __('Button style', 'music-wave-core'), [
						['solid', __('Solid', 'music-wave-core')],
						['outline', __('Outline', 'music-wave-core')],
						['ghost', __('Ghost', 'music-wave-core')]
					], __('The same choices are available in the block Styles panel.', 'music-wave-core')],
					['toggle', 'showIcon', __('Show play icon', 'music-wave-core'), true, __('The icon stays available to assistive technology when hidden.', 'music-wave-core')]
				] }
			]
		},
		'music-wave/download-button': {
			releaseId: true,
			compact: true,
			groups: [
				{ title: __('Header', 'music-wave-core'), controls: [
					['text', 'heading', __('Section heading', 'music-wave-core'), ''],
					['text', 'description', __('Section description', 'music-wave-core'), ''],
					['toggle', 'showHeading', __('Show heading', 'music-wave-core'), true],
					['toggle', 'showDescription', __('Show description', 'music-wave-core'), true]
				] },
				{ title: __('Download rows', 'music-wave-core'), controls: [
					['toggle', 'showQuality', __('Show quality selector', 'music-wave-core'), true, __('When hidden, each file downloads its first listed quality.', 'music-wave-core')],
					['toggle', 'showStream', __('Show secure play buttons', 'music-wave-core'), true],
					['text', 'downloadLabel', __('Download button label', 'music-wave-core'), ''],
					['text', 'playLabel', __('Play button label', 'music-wave-core'), ''],
					['text', 'loginLabel', __('Sign-in button label', 'music-wave-core'), '']
				], help: __('Leave a label empty to use the translated default.', 'music-wave-core') }
			]
		},
		'music-wave/related-releases': {
			releaseId: true,
			groups: [
				{ title: __('Sections', 'music-wave-core'), controls: [
					['select', 'sameArtistSection', __('Same-artist section', 'music-wave-core'), [
						['inherit', __('Default (global setting)', 'music-wave-core')],
						['enabled', __('Always show', 'music-wave-core')],
						['disabled', __('Hide', 'music-wave-core')]
					]],
					['select', 'similarSection', __('Similar releases section', 'music-wave-core'), [
						['inherit', __('Default (global setting)', 'music-wave-core')],
						['enabled', __('Always show', 'music-wave-core')],
						['disabled', __('Hide', 'music-wave-core')]
					]],
					['text', 'sameArtistHeading', __('Same-artist heading', 'music-wave-core'), ''],
					['text', 'similarHeading', __('Similar releases heading', 'music-wave-core'), ''],
					['toggle', 'showSectionLink', __('Show section link', 'music-wave-core'), false, __('Links the same-artist section to the artist archive and the similar section to the catalog.', 'music-wave-core')],
					['text', 'sectionLinkLabel', __('Section link label', 'music-wave-core'), '']
				] },
				{ title: __('Query', 'music-wave-core'), controls: [
					['range', 'itemsToShow', __('Items per section', 'music-wave-core'), 2, 12, __('Uses the global default until changed.', 'music-wave-core'), 4],
					['select', 'orderBy', __('Order by', 'music-wave-core'), [
						['date', __('Release date', 'music-wave-core')],
						['modified', __('Recently updated', 'music-wave-core')],
						['title', __('Title', 'music-wave-core')],
						['rand', __('Random', 'music-wave-core')]
					]],
					['select', 'order', __('Order', 'music-wave-core'), [
						['DESC', __('Descending', 'music-wave-core')],
						['ASC', __('Ascending', 'music-wave-core')]
					]],
					['toggle', 'matchGenre', __('Match shared genres', 'music-wave-core'), true],
					['toggle', 'matchMood', __('Match shared moods', 'music-wave-core'), true],
					['toggle', 'matchType', __('Match the release type', 'music-wave-core'), true]
				] },
				{ title: __('Layout', 'music-wave-core'), controls: [
					['select', 'layout', __('Layout', 'music-wave-core'), [
						['grid', __('Grid', 'music-wave-core')],
						['scroll', __('Horizontal scroll', 'music-wave-core')],
						['list', __('List', 'music-wave-core')]
					]],
					['range', 'columns', __('Grid columns', 'music-wave-core'), 2, 6, undefined, 4],
					['select', 'imageShape', __('Artwork shape', 'music-wave-core'), [
						['square', __('Square', 'music-wave-core')],
						['landscape', __('Landscape', 'music-wave-core')],
						['portrait', __('Portrait', 'music-wave-core')],
						['circle', __('Circle', 'music-wave-core')]
					]]
				] },
				{ title: __('Card content', 'music-wave-core'), controls: [
					['toggle', 'showArtwork', __('Show artwork', 'music-wave-core'), true],
					['toggle', 'showArtist', __('Show artist', 'music-wave-core'), true],
					['toggle', 'showDate', __('Show release date', 'music-wave-core'), false],
					['toggle', 'showExcerpt', __('Show excerpt', 'music-wave-core'), false],
					['toggle', 'showPreview', __('Show preview button', 'music-wave-core'), true],
					['toggle', 'showAction', __('Show action link', 'music-wave-core'), false],
					['text', 'actionLabel', __('Action link label', 'music-wave-core'), '']
				] }
			]
		},
		'music-wave/preview-button': {
			releaseId: true,
			compact: true,
			textFields: [['label', __('Button label', 'music-wave-core')]],
			groups: [
				{ title: __('Appearance', 'music-wave-core'), controls: [
					['select', 'style', __('Button style', 'music-wave-core'), [
						['solid', __('Solid', 'music-wave-core')],
						['outline', __('Outline', 'music-wave-core')],
						['ghost', __('Ghost', 'music-wave-core')]
					], __('The same choices are available in the block Styles panel.', 'music-wave-core')],
					['toggle', 'showIcon', __('Show play icon', 'music-wave-core'), true],
					['toggle', 'fullWidth', __('Full width button', 'music-wave-core'), false, __('Stretches the button to fill its container.', 'music-wave-core')]
				] }
			]
		},
		'music-wave/account-dashboard': {
			toggles: [
				['showStats', __('Show account statistics', 'music-wave-core'), true, __('Counts available downloads, saved releases, and followed artists.', 'music-wave-core')],
				['showQuickLinks', __('Show account shortcuts', 'music-wave-core'), true, __('WooCommerce and VIP sections appear only while their plugins are active.', 'music-wave-core')],
				['showLibrary', __('Show personal music library', 'music-wave-core'), true, __('Renders the secure library with entitled downloads.', 'music-wave-core')]
			]
		},
		'music-wave/music-library': {
			groups: [
				{ title: __('Header', 'music-wave-core'), controls: [
					['text', 'heading', __('Section heading', 'music-wave-core'), ''],
					['text', 'intro', __('Intro text', 'music-wave-core'), ''],
					['toggle', 'showHeading', __('Show heading', 'music-wave-core'), true],
					['toggle', 'showFilters', __('Show filter tabs', 'music-wave-core'), true, __('Tabs group saved items by release type and followed artists.', 'music-wave-core')],
					['toggle', 'showCounts', __('Show item counts', 'music-wave-core'), true],
					['text', 'emptyMessage', __('Empty state message', 'music-wave-core'), '']
				], help: __('Leave texts empty to use the translated defaults.', 'music-wave-core') },
				{ title: __('Layout', 'music-wave-core'), controls: [
					['select', 'layout', __('Library layout', 'music-wave-core'), [
						['list', __('List', 'music-wave-core')],
						['grid', __('Grid', 'music-wave-core')]
					]],
					['range', 'columns', __('Grid columns', 'music-wave-core'), 2, 6, undefined, 4],
					['range', 'itemsToShow', __('Items per page', 'music-wave-core'), 1, 100, undefined, 24]
				] },
				{ title: __('Item display', 'music-wave-core'), controls: [
					['toggle', 'showArtist', __('Show artist name', 'music-wave-core'), true],
					['toggle', 'showType', __('Show type badge', 'music-wave-core'), true, __('Displays the song, album, podcast, or artist label.', 'music-wave-core')],
					['toggle', 'showYear', __('Show release year', 'music-wave-core'), false],
					['toggle', 'showRemove', __('Show remove buttons', 'music-wave-core'), true]
				] }
			]
		},
		'music-wave/library-button': {
			releaseId: true,
			compact: true,
			groups: [
				{ title: __('Button', 'music-wave-core'), controls: [
					['text', 'label', __('Button label', 'music-wave-core'), ''],
					['text', 'addedLabel', __('Saved state label', 'music-wave-core'), ''],
					['select', 'style', __('Button style', 'music-wave-core'), [
						['solid', __('Solid', 'music-wave-core')],
						['outline', __('Outline', 'music-wave-core')],
						['ghost', __('Ghost', 'music-wave-core')]
					]]
				], help: __('Use the artist term ID to turn this into a follow-artist button for a fixed artist.', 'music-wave-core') },
				{ title: __('Artist target', 'music-wave-core'), controls: [
					['intText', 'termId', __('Artist term ID', 'music-wave-core'), '']
				], help: __('Leave 0 to target the selected release. Enter a numeric artist taxonomy ID to follow that artist instead.', 'music-wave-core') }
			]
		},
		'music-wave/artist-profile': {
			artistProfile: true
		}
	};

	var emptyStateCopy = {
		'music-wave/release-meta': __('Add catalog number, release date, duration, BPM, key, artist, or genre to the selected release.', 'music-wave-core'),
		'music-wave/access-panel': __('The public access mode intentionally has no panel. Choose a protected, purchase, or membership release to preview this block.', 'music-wave-core'),
		'music-wave/release-credits': __('Add at least one credit in the release details to display the credits section.', 'music-wave-core'),
		'music-wave/collection-list': __('Add tracks or episodes to the selected collection to display its ordered track list.', 'music-wave-core'),
		'music-wave/catalog-filters': __('Catalog filters are generated from your artist, genre, mood, and release-type terms on the public archive.', 'music-wave-core'),
		'music-wave/catalog-results': __('The result summary appears when the public catalog has an active search, filter, or sort option.', 'music-wave-core'),
		'music-wave/preview-player': __('Add a secure HTTPS preview URL to the selected release to display the player.', 'music-wave-core'),
		'music-wave/download-button': __('Add at least one protected download file and preview this block as an eligible signed-in user.', 'music-wave-core'),
		'music-wave/related-releases': __('Related sections appear when the selected release shares artists, genres, moods, or release types with other releases.', 'music-wave-core'),
		'music-wave/artist-profile': __('Artist profile content is displayed on an artist archive after an image, biography, or official URL is added to that artist.', 'music-wave-core'),
		'music-wave/preview-button': __('Add a secure HTTPS preview URL to the selected release to display the play button.', 'music-wave-core'),
		'music-wave/music-library': __('The personal library lists every song, album, podcast, and artist a signed-in visitor saves with the add-to-library button.', 'music-wave-core'),
		'music-wave/library-button': __('The button saves the selected release to a visitor\'s personal library, or follows an artist when an artist term ID is set.', 'music-wave-core')
	};

	function editorReleaseId(props) {
		var editorId = 0;

		if (useSelect) {
			editorId = useSelect(function (select) {
				var editor = select('core/editor');
				if (!editor || !editor.getCurrentPostType || !editor.getCurrentPostId) {
					return 0;
				}
				return 'mw_release' === editor.getCurrentPostType() ? editor.getCurrentPostId() : 0;
			}, []);
		}

		if (
			props.context &&
			'mw_release' === props.context.postType &&
			parseInt(props.context.postId, 10) > 0
		) {
			return parseInt(props.context.postId, 10);
		}

		return editorId;
	}

	function releaseOptions(releaseId) {
		if (!useSelect) {
			return [];
		}

		return useSelect(function (select) {
			var core = select('core');
			if (!core || !core.getEntityRecords) {
				return [];
			}

			var query = {
				per_page: 50,
				orderby: 'date',
				order: 'desc',
				status: ['publish', 'draft', 'pending', 'private']
			};
			if (releaseId > 0) {
				query.include = [releaseId];
			}

			var records = core.getEntityRecords('postType', 'mw_release', query) || [];
			return records.map(function (record) {
				var title = record && record.title && record.title.rendered ? record.title.rendered : '';
				return {
					label: title !== '' ? title.replace(/<[^>]*>/g, '') : ('#' + record.id),
					value: record.id
				};
			});
		}, [releaseId]);
	}

	function releaseSelect(props, options, contextualId) {
		var choices = [{
			label: contextualId > 0
				? __('Current release', 'music-wave-core')
				: __('Automatic / current release', 'music-wave-core'),
			value: 0
		}];

		options.forEach(function (option) {
			choices.push(option);
		});

		return createElement(components.SelectControl, {
			label: __('Preview release', 'music-wave-core'),
			value: props.attributes.releaseId || 0,
			options: choices,
			onChange: function (value) {
				props.setAttributes({ releaseId: parseInt(value, 10) || 0 });
			},
			help: __('Leave this automatic in release templates and Query Loops. Select a fixed release only when the block should always show that release.', 'music-wave-core')
		});
	}

	function artistProfileControls(props) {
		return [
			createElement(components.TextControl, {
				key: 'termId',
				label: __('Artist term ID', 'music-wave-core'),
				help: __('Enter a numeric artist taxonomy ID to pin this block to that artist on any page. Leave 0 to use the currently viewed artist archive.', 'music-wave-core'),
				value: String(props.attributes.termId || ''),
				onChange: function (value) {
					var id = parseInt(value, 10);
					props.setAttributes({ termId: isNaN(id) ? 0 : id });
				}
			}),
			createElement(components.SelectControl, {
				key: 'layout',
				label: __('Layout', 'music-wave-core'),
				value: props.attributes.layout || 'card',
				options: [
					{ label: __('Card', 'music-wave-core'), value: 'card' },
					{ label: __('List (side-by-side)', 'music-wave-core'), value: 'list' },
					{ label: __('Slider / featured', 'music-wave-core'), value: 'slider' }
				],
				onChange: function (value) {
					props.setAttributes({ layout: value });
				}
			}),
			createElement(components.SelectControl, {
				key: 'imageSize',
				label: __('Image size', 'music-wave-core'),
				value: props.attributes.imageSize || 'medium',
				options: [
					{ label: __('Thumbnail', 'music-wave-core'), value: 'thumbnail' },
					{ label: __('Medium', 'music-wave-core'), value: 'medium' },
					{ label: __('Large', 'music-wave-core'), value: 'large' },
					{ label: __('Full', 'music-wave-core'), value: 'full' }
				],
				onChange: function (value) {
					props.setAttributes({ imageSize: value });
				}
			}),
			createElement(components.SelectControl, {
				key: 'imageShape',
				label: __('Image shape', 'music-wave-core'),
				value: props.attributes.imageShape || 'rounded',
				options: [
					{ label: __('Rounded corners', 'music-wave-core'), value: 'rounded' },
					{ label: __('Square', 'music-wave-core'), value: 'square' },
					{ label: __('Circle', 'music-wave-core'), value: 'circle' }
				],
				onChange: function (value) {
					props.setAttributes({ imageShape: value });
				}
			}),
			createElement(components.ToggleControl, {
				key: 'showImage',
				label: __('Show artist image', 'music-wave-core'),
				checked: false !== props.attributes.showImage,
				onChange: function (value) {
					props.setAttributes({ showImage: !!value });
				}
			}),
			createElement(components.ToggleControl, {
				key: 'showTitle',
				label: __('Show artist name', 'music-wave-core'),
				checked: false !== props.attributes.showTitle,
				onChange: function (value) {
					props.setAttributes({ showTitle: !!value });
				}
			}),
			createElement(components.ToggleControl, {
				key: 'showBio',
				label: __('Show biography', 'music-wave-core'),
				checked: false !== props.attributes.showBio,
				onChange: function (value) {
					props.setAttributes({ showBio: !!value });
				}
			}),
			createElement(components.ToggleControl, {
				key: 'showLink',
				label: __('Show external link', 'music-wave-core'),
				checked: false !== props.attributes.showLink,
				onChange: function (value) {
					props.setAttributes({ showLink: !!value });
				}
			}),
			createElement(components.ToggleControl, {
				key: 'showLibraryButton',
				label: __('Show follow-artist button', 'music-wave-core'),
				help: __('Lets visitors save this artist to their personal music library.', 'music-wave-core'),
				checked: false !== props.attributes.showLibraryButton,
				onChange: function (value) {
					props.setAttributes({ showLibraryButton: !!value });
				}
			}),
			createElement(components.ToggleControl, {
				key: 'showReleaseCount',
				label: __('Show release count', 'music-wave-core'),
				help: __('Displays a badge with the number of published releases assigned to this artist.', 'music-wave-core'),
				checked: !!props.attributes.showReleaseCount,
				onChange: function (value) {
					props.setAttributes({ showReleaseCount: !!value });
				}
			}),
			createElement(components.RangeControl, {
				key: 'bioLength',
				label: __('Biography length (words)', 'music-wave-core'),
				help: __('0 keeps the full biography. Any other value shows a trimmed excerpt.', 'music-wave-core'),
				value: props.attributes.bioLength || 0,
				min: 0,
				max: 200,
				onChange: function (value) {
					props.setAttributes({ bioLength: parseInt(value, 10) || 0 });
				}
			}),
			createElement(components.TextControl, {
				key: 'ctaLabel',
				label: __('Link button label', 'music-wave-core'),
				help: __('Leave empty to use the default label.', 'music-wave-core'),
				value: props.attributes.ctaLabel || '',
				onChange: function (value) {
					props.setAttributes({ ctaLabel: value });
				}
			}),
			createElement('div', {
				key: 'accentColor',
				className: 'mw-block-editor-color-field'
			},
				createElement('p', { className: 'components-base-control__label' }, __('Accent color', 'music-wave-core')),
				createElement(components.ColorPalette, {
					value: props.attributes.accentColor || undefined,
					clearable: true,
					onChange: function (color) {
						props.setAttributes({ accentColor: color || '' });
					}
				}),
				createElement('p', { className: 'components-base-control__help' }, __('Overrides the theme accent for the name and link. Clear it to use the default.', 'music-wave-core'))
			)
		];
	}

	/**
	 * Build one inspector control from a declarative group descriptor.
	 *
	 * Descriptor formats:
	 * ['text', attr, label, default?, help?]             TextControl
	 * ['intText', attr, label]                           TextControl storing an integer
	 * ['toggle', attr, label, default, help?]            ToggleControl
	 * ['select', attr, label, [[value, label]], help?]   SelectControl
	 * ['range', attr, label, min, max, help?, fallback?]   RangeControl
	 */
	function buildGroupControl(descriptor, props) {
		var type = descriptor[0];
		var attr = descriptor[1];
		var label = descriptor[2];

		if ('text' === type) {
			return createElement(components.TextControl, {
				key: attr,
				label: label,
				value: props.attributes[attr] || '',
				help: descriptor[4] || undefined,
				onChange: function (value) {
					var update = {};
					update[attr] = value;
					props.setAttributes(update);
				}
			});
		}

		if ('intText' === type) {
			return createElement(components.TextControl, {
				key: attr,
				label: label,
				value: props.attributes[attr] ? String(props.attributes[attr]) : '',
				onChange: function (value) {
					var parsed = parseInt(value, 10);
					var update = {};
					update[attr] = isNaN(parsed) ? 0 : parsed;
					props.setAttributes(update);
				}
			});
		}

		if ('toggle' === type) {
			return createElement(components.ToggleControl, {
				key: attr,
				label: label,
				checked: props.attributes[attr] === undefined ? !!descriptor[3] : !!props.attributes[attr],
				help: descriptor[4] || undefined,
				onChange: function (value) {
					var update = {};
					update[attr] = !!value;
					props.setAttributes(update);
				}
			});
		}

		if ('select' === type) {
			return createElement(components.SelectControl, {
				key: attr,
				label: label,
				value: props.attributes[attr],
				options: (descriptor[3] || []).map(function (option) {
					return { value: option[0], label: option[1] };
				}),
				help: descriptor[4] || undefined,
				onChange: function (value) {
					var update = {};
					update[attr] = value;
					props.setAttributes(update);
				}
			});
		}

		if ('range' === type) {
			return createElement(components.RangeControl, {
				key: attr,
				label: label,
				value: props.attributes[attr] || descriptor[6] || descriptor[3],
				min: descriptor[3],
				max: descriptor[4],
				help: descriptor[5] || undefined,
				onChange: function (value) {
					var update = {};
					update[attr] = parseInt(value, 10) || descriptor[3];
					props.setAttributes(update);
				}
			});
		}

		return null;
	}

	function inspectorControls(props, blockName, options, contextualId) {
		var config = fieldConfig[blockName] || {};
		var controls = [];

		if (config.artistProfile) {
			controls = artistProfileControls(props);
		}

		if (config.releaseId) {
			controls.push(releaseSelect(props, options, contextualId));
		}

		if (config.compact) {
			controls.push(createElement(components.ToggleControl, {
				key: 'compact',
				label: __('Compact display', 'music-wave-core'),
				checked: !!props.attributes.compact,
				onChange: function (value) {
					props.setAttributes({ compact: !!value });
				},
				help: __('Use the denser layout for cards, lists, and sidebars.', 'music-wave-core')
			}));
		}

		(config.textFields || []).forEach(function (field) {
			controls.push(createElement(components.TextControl, {
				key: field[0],
				label: field[1],
				value: props.attributes[field[0]] || '',
				onChange: function (value) {
					var update = {};
					update[field[0]] = value;
					props.setAttributes(update);
				},
				help: __('Leave empty to inherit the translated MusicWave default label.', 'music-wave-core')
			}));
		});

		(config.toggles || []).forEach(function (field) {
			controls.push(createElement(components.ToggleControl, {
				key: field[0],
				label: field[1],
				checked: false !== props.attributes[field[0]],
				onChange: function (value) {
					var update = {};
					update[field[0]] = !!value;
					props.setAttributes(update);
				}
			}));
		});

		if (config.range) {
			controls.push(createElement(components.RangeControl, {
				key: config.range[0],
				label: config.range[1],
				value: props.attributes[config.range[0]] || 4,
				min: config.range[2],
				max: config.range[3],
				onChange: function (value) {
					var update = {};
					update[config.range[0]] = parseInt(value, 10) || 4;
					props.setAttributes(update);
				}
			}));
		}

		if (!controls.length && !config.groups) {
			return null;
		}

		var panels = [];
		if (controls.length) {
			panels.push(createElement(
				components.PanelBody,
				{ title: __('MusicWave block settings', 'music-wave-core'), initialOpen: true, key: 'music-wave-settings' },
				controls
			));
		}

		(config.groups || []).forEach(function (group, index) {
			var groupControls = (group.controls || [])
				.map(function (descriptor) {
					return buildGroupControl(descriptor, props);
				})
				.filter(function (control) {
					return null !== control;
				});
			if (group.help) {
				groupControls.push(createElement(
					'p',
					{ className: 'components-base-control__help', key: 'group-help' },
					group.help
				));
			}
			if (groupControls.length) {
				panels.push(createElement(
					components.PanelBody,
					{ title: group.title, initialOpen: false, key: 'music-wave-group-' + index },
					groupControls
				));
			}
		});

		if (!panels.length) {
			return null;
		}

		return createElement(
			blockEditor.InspectorControls,
			null,
			panels
		);
	}

	function editorEmptyState(props, block, options, contextualId) {
		var config = fieldConfig[block.name] || {};
		var children = [
			createElement('span', { className: 'dashicons dashicons-album', 'aria-hidden': 'true', key: 'icon' }),
			createElement('strong', { key: 'title' }, block.title),
			createElement(
				'p',
				{ key: 'message' },
				emptyStateCopy[block.name] || __('This block is ready and will render when its required MusicWave data is available.', 'music-wave-core')
			)
		];

		if (config.releaseId && options.length) {
			children.push(
				createElement(
					'div',
					{ className: 'mw-block-editor-empty__control', key: 'control' },
					releaseSelect(props, options, contextualId)
				)
			);
		}

		return createElement(
			'div',
			{
				className: 'mw-block-editor-empty',
				'data-mw-empty-block': block.name
			},
			children
		);
	}

	function editorPreview(props, block, options, contextualId) {
		var attrs = Object.assign({}, props.attributes);
		var config = fieldConfig[block.name] || {};

		if (config.releaseId && (!attrs.releaseId || attrs.releaseId < 1)) {
			if (contextualId > 0) {
				attrs.releaseId = contextualId;
			} else if (options.length) {
				// Use a real release only for the editor preview. Do not persist it
				// into a contextual template or Query Loop.
				attrs.releaseId = options[0].value;
			}
		}

		return createElement(
			'div',
			{
				className: 'mw-block-editor-shell' + ((props.attributes && props.attributes.align) ? ' align' + props.attributes.align : ''),
				'data-mw-block': block.name
			},
			createElement(serverSideRender, {
				block: block.name,
				attributes: attrs,
				EmptyResponsePlaceholder: function () {
					return editorEmptyState(props, block, options, contextualId);
				},
				ErrorResponsePlaceholder: function () {
					return createElement(
						components.Notice,
						{ status: 'error', isDismissible: false },
						__('MusicWave could not load this preview. Check that MusicWave Core is active and the selected release still exists.', 'music-wave-core')
					);
				},
				LoadingResponsePlaceholder: function () {
					return createElement(components.Spinner);
				}
			})
		);
	}

	dynamicBlocks.forEach(function (block) {
		var existing = blocks.getBlockType(block.name);
		if (existing) {
			// Server hydration from block.json registers a bare dynamic block.
			// Re-registration only adds the editor experience: every schema
			// field the server provided (attributes, supports, context, titles)
			// stays authoritative, so block.json remains the single source of
			// truth (PROJECT_PLAN.md Stage 4 deliverable 3).
			try {
				blocks.unregisterBlockType(block.name);
			} catch (error) {
				return;
			}
		}

		blocks.registerBlockType(block.name, {
			apiVersion: existing && existing.apiVersion ? existing.apiVersion : 3,
			title: (existing && existing.title) || block.title,
			description: (existing && existing.description) || block.description,
			category: (existing && existing.category) || 'music-wave',
			icon: block.icon || (existing && existing.icon) || 'album',
			keywords: block.keywords || [],
			attributes: Object.assign({}, block.attributes || {}, (existing && existing.attributes) || {}),
			usesContext: existing && existing.usesContext && existing.usesContext.length ? existing.usesContext : (block.usesContext || []),
			supports: Object.assign({}, block.supports || {}, (existing && existing.supports) || {}),
			styles: existing && existing.styles ? existing.styles : undefined,
			example: block.example || (existing && existing.example ? existing.example : undefined),
			edit: function (props) {
				var config = fieldConfig[block.name] || {};
				var contextualId = editorReleaseId(props);
				var options = config.releaseId ? releaseOptions(props.attributes.releaseId || 0) : [];

				return createElement(
					Fragment,
					null,
					inspectorControls(props, block.name, options, contextualId),
					editorPreview(props, block, options, contextualId)
				);
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
	window.musicWaveDynamicBlocks || []
);
