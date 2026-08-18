<?php
/**
 * Frontend release rendering module.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Modules;

use ManaCore\MusicWave\Core\Blocks\ReleaseBlocks;
use ManaCore\MusicWave\Core\Blocks\ArtistProfileBlock;
use ManaCore\MusicWave\Core\Blocks\BlockMetadata;
use ManaCore\MusicWave\Core\Blocks\BlockSupport;
use ManaCore\MusicWave\Core\Blocks\PreviewPlayer;
use ManaCore\MusicWave\Core\Contracts\Module;
use ManaCore\MusicWave\Core\Infrastructure\ReleaseRestVisibilityPolicy;
use ManaCore\MusicWave\Core\Playback\PlaybackQueueRoutes;

final class Rendering implements Module {
	/** @var ReleaseBlocks */
	private $blocks;

	/** @var ArtistProfileBlock|null */
	private $artist_profile;

	/** @var PreviewPlayer|null */
	private $preview_player;

	/** @var PlaybackQueueRoutes|null */
	private $playback_queue;

	/** @var ReleaseRestVisibilityPolicy|null */
	private $rest_visibility;

	public function __construct( ReleaseBlocks $blocks, ?ArtistProfileBlock $artist_profile = null, ?PreviewPlayer $preview_player = null, ?PlaybackQueueRoutes $playback_queue = null, ?ReleaseRestVisibilityPolicy $rest_visibility = null ) {
		$this->blocks          = $blocks;
		$this->artist_profile  = $artist_profile;
		$this->preview_player  = $preview_player;
		$this->playback_queue  = $playback_queue;
		$this->rest_visibility = $rest_visibility;
	}

	public function register(): void {
		add_action( 'init', array( $this->blocks, 'register' ), 20 );
		if ( null !== $this->artist_profile ) {
			add_action( 'init', array( $this->artist_profile, 'register' ), 21 );
		}
		if ( null !== $this->preview_player ) {
			add_action( 'init', array( $this->preview_player, 'register' ), 22 );
		}
		if ( null !== $this->playback_queue ) {
			$this->playback_queue->register();
		}
		if ( null !== $this->rest_visibility ) {
			$this->rest_visibility->register();
		}
		add_action( 'init', array( $this, 'register_block_styles' ), 30 );
		add_filter( 'block_categories_all', array( $this, 'register_block_category' ) );
		add_filter( 'the_content', array( $this->blocks, 'filter_content' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_blocks' ) );
	}

	/**
	 * Add the dedicated MusicWave category to the block inserter.
	 *
	 * @param mixed $categories Existing block categories.
	 * @return array<int, array<string, mixed>>
	 */
	public function register_block_category( $categories ): array {
		$categories = is_array( $categories ) ? $categories : array();
		foreach ( $categories as $category ) {
			if ( is_array( $category ) && isset( $category['slug'] ) && 'music-wave' === $category['slug'] ) {
				return $categories;
			}
		}

		array_unshift(
			$categories,
			array(
				'slug'  => 'music-wave',
				'title' => __( 'MusicWave', 'music-wave-core' ),
				'icon'  => 'format-audio',
			)
		);

		return $categories;
	}

	/**
	 * Expose existing layout modifiers as editor style variations.
	 *
	 * The renderers translate the chosen is-style-* class into the same
	 * modifier classes produced by the inspector toggles, so both surfaces
	 * stay visually identical.
	 *
	 * @return void
	 */
	public function register_block_styles(): void {
		if ( ! function_exists( 'register_block_style' ) ) {
			return;
		}

		$styles = array(
			'music-wave/preview-player'  => array(
				array(
					'name'  => 'outline',
					'label' => __( 'Outline', 'music-wave-core' ),
				),
				array(
					'name'  => 'ghost',
					'label' => __( 'Ghost', 'music-wave-core' ),
				),
			),
			'music-wave/preview-button'  => array(
				array(
					'name'  => 'outline',
					'label' => __( 'Outline', 'music-wave-core' ),
				),
				array(
					'name'  => 'ghost',
					'label' => __( 'Ghost', 'music-wave-core' ),
				),
			),
			'music-wave/release-meta'    => array(
				array(
					'name'  => 'inline',
					'label' => __( 'Inline', 'music-wave-core' ),
				),
				array(
					'name'  => 'stack',
					'label' => __( 'Stacked rows', 'music-wave-core' ),
				),
			),
			'music-wave/catalog-filters' => array(
				array(
					'name'  => 'stacked',
					'label' => __( 'Stacked', 'music-wave-core' ),
				),
			),
		);

		foreach ( $styles as $block_name => $variations ) {
			foreach ( $variations as $variation ) {
				register_block_style( $block_name, $variation );
			}
		}
	}

	/**
	 * Register JavaScript editor counterparts for PHP-rendered blocks.
	 *
	 * WordPress 6.6 does not automatically make PHP-only blocks available to
	 * the browser block registry. These counterparts preserve dynamic rendering
	 * while allowing templates to open in the Site Editor without an unsupported
	 * block warning.
	 *
	 * @return void
	 */
	public function enqueue_editor_blocks(): void {
		wp_enqueue_script(
			'music-wave-dynamic-blocks',
			MUSIC_WAVE_CORE_URL . 'assets/blocks.js',
			array( 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
			MUSIC_WAVE_CORE_VERSION,
			true
		);
		wp_localize_script(
			'music-wave-dynamic-blocks',
			'musicWaveDynamicBlocks',
			$this->editor_blocks()
		);
		wp_enqueue_style(
			'music-wave-editor',
			MUSIC_WAVE_CORE_URL . 'assets/editor.css',
			array(),
			MUSIC_WAVE_CORE_VERSION
		);
	}

	/**
	 * Return the client registration metadata for every Core dynamic block.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function editor_blocks(): array {
		$release_attributes         = array(
			'releaseId' => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'compact'   => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);
		$metadata_attributes        = array_merge(
			$release_attributes,
			array(
				'showCatalogNumber' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showReleaseDate'   => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showDuration'      => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showBpm'           => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showKey'           => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showArtist'        => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showGenre'         => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showLibraryButton' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'layout'            => array(
					'type'    => 'string',
					'default' => 'grid',
				),
				'showLabels'        => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'linkTerms'         => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showMood'          => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showReleaseType'   => array(
					'type'    => 'boolean',
					'default' => false,
				),
			)
		);
		$release_id_attributes      = array(
			'releaseId' => array(
				'type'    => 'integer',
				'default' => 0,
			),
		);
		$access_panel_attributes    = array_merge(
			$release_id_attributes,
			array(
				'layout'             => array(
					'type'    => 'string',
					'default' => 'banner',
				),
				'showWhenGranted'    => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'grantedMessage'     => array(
					'type'    => 'string',
					'default' => '',
				),
				'restrictedMessage'  => array(
					'type'    => 'string',
					'default' => '',
				),
				'purchaseMessage'    => array(
					'type'    => 'string',
					'default' => '',
				),
				'purchaseCtaLabel'   => array(
					'type'    => 'string',
					'default' => '',
				),
				'membershipMessage'  => array(
					'type'    => 'string',
					'default' => '',
				),
				'membershipCtaLabel' => array(
					'type'    => 'string',
					'default' => '',
				),
				'membershipCtaUrl'   => array(
					'type'    => 'string',
					'default' => '',
				),
			)
		);
		$credits_attributes         = array_merge(
			$release_id_attributes,
			array(
				'heading'     => array(
					'type'    => 'string',
					'default' => '',
				),
				'showHeading' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showRole'    => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'groupByRole' => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'layout'      => array(
					'type'    => 'string',
					'default' => 'list',
				),
			)
		);
		$collection_attributes      = array_merge(
			$release_id_attributes,
			array(
				'heading'           => array(
					'type'    => 'string',
					'default' => '',
				),
				'showHeading'       => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showPosition'      => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showArtwork'       => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showDuration'      => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showTotalDuration' => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showPreview'       => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showDownload'      => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'groupByDisc'       => array(
					'type'    => 'boolean',
					'default' => false,
				),
			)
		);
		$catalog_filter_attributes  = array(
			'layout'            => array(
				'type'    => 'string',
				'default' => 'inline',
			),
			'showSearch'        => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showArtistFilter'  => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showGenreFilter'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showMoodFilter'    => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showTypeFilter'    => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showSort'          => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showReset'         => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'maxTerms'          => array(
				'type'    => 'integer',
				'default' => 50,
			),
			'searchPlaceholder' => array(
				'type'    => 'string',
				'default' => '',
			),
			'submitLabel'       => array(
				'type'    => 'string',
				'default' => '',
			),
			'resetLabel'        => array(
				'type'    => 'string',
				'default' => '',
			),
		);
		$catalog_results_attributes = array(
			'showCount' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showChips' => array(
				'type'    => 'boolean',
				'default' => true,
			),
		);
		$preview_attributes         = array_merge(
			$release_id_attributes,
			array(
				'label'    => array(
					'type'    => 'string',
					'default' => '',
				),
				'style'    => array(
					'type'    => 'string',
					'default' => 'solid',
				),
				'showIcon' => array(
					'type'    => 'boolean',
					'default' => true,
				),
			)
		);
		$download_attributes        = array_merge(
			$release_attributes,
			array(
				'heading'         => array(
					'type'    => 'string',
					'default' => '',
				),
				'description'     => array(
					'type'    => 'string',
					'default' => '',
				),
				'showHeading'     => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showDescription' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showQuality'     => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showStream'      => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'downloadLabel'   => array(
					'type'    => 'string',
					'default' => '',
				),
				'playLabel'       => array(
					'type'    => 'string',
					'default' => '',
				),
				'loginLabel'      => array(
					'type'    => 'string',
					'default' => '',
				),
			)
		);
		$related_attributes         = array_merge(
			$release_id_attributes,
			array(
				'itemsToShow'       => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'orderBy'           => array(
					'type'    => 'string',
					'default' => 'date',
				),
				'order'             => array(
					'type'    => 'string',
					'default' => 'DESC',
				),
				'sameArtistSection' => array(
					'type'    => 'string',
					'default' => 'inherit',
				),
				'similarSection'    => array(
					'type'    => 'string',
					'default' => 'inherit',
				),
				'matchGenre'        => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'matchMood'         => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'matchType'         => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'sameArtistHeading' => array(
					'type'    => 'string',
					'default' => '',
				),
				'similarHeading'    => array(
					'type'    => 'string',
					'default' => '',
				),
				'showSectionLink'   => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'sectionLinkLabel'  => array(
					'type'    => 'string',
					'default' => '',
				),
				'layout'            => array(
					'type'    => 'string',
					'default' => 'grid',
				),
				'columns'           => array(
					'type'    => 'integer',
					'default' => 4,
				),
				'imageShape'        => array(
					'type'    => 'string',
					'default' => 'square',
				),
				'showArtwork'       => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showArtist'        => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showDate'          => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showExcerpt'       => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showPreview'       => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showAction'        => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'actionLabel'       => array(
					'type'    => 'string',
					'default' => '',
				),
			)
		);
		$preview_button_attributes  = array_merge(
			$preview_attributes,
			array(
				'compact'   => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'fullWidth' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			)
		);
		$artist_profile_attributes  = array(
			'termId'            => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'layout'            => array(
				'type'    => 'string',
				'default' => 'card',
			),
			'showImage'         => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showTitle'         => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showBio'           => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showLink'          => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showLibraryButton' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'imageSize'         => array(
				'type'    => 'string',
				'default' => 'medium',
			),
			'imageShape'        => array(
				'type'    => 'string',
				'default' => 'rounded',
			),
			'ctaLabel'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'bioLength'         => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'showReleaseCount'  => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'accentColor'       => array(
				'type'    => 'string',
				'default' => '',
			),
		);
		$supports                   = BlockSupport::appearance_tools();
		$music_library_attributes   = array(
			'heading'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'showHeading'  => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'intro'        => array(
				'type'    => 'string',
				'default' => '',
			),
			'showFilters'  => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showCounts'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'layout'       => array(
				'type'    => 'string',
				'default' => 'list',
			),
			'columns'      => array(
				'type'    => 'integer',
				'default' => 4,
			),
			'itemsToShow'  => array(
				'type'    => 'integer',
				'default' => 24,
			),
			'showArtist'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showType'     => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showYear'     => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'showRemove'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'emptyMessage' => array(
				'type'    => 'string',
				'default' => '',
			),
		);
		$library_button_attributes  = array(
			'releaseId'  => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'termId'     => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'label'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'addedLabel' => array(
				'type'    => 'string',
				'default' => '',
			),
			'style'      => array(
				'type'    => 'string',
				'default' => 'solid',
			),
			'compact'    => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);

		$editor_blocks = array(
			array(
				'name'        => 'music-wave/release-meta',
				'title'       => __( 'Release metadata', 'music-wave-core' ),
				'description' => __( 'Displays release metadata from MusicWave Core.', 'music-wave-core' ),
				'icon'        => 'list-view',
				'keywords'    => array( __( 'catalog number', 'music-wave-core' ), __( 'bpm', 'music-wave-core' ), __( 'details', 'music-wave-core' ) ),
				'attributes'  => $metadata_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/access-panel',
				'title'       => __( 'Release access panel', 'music-wave-core' ),
				'description' => __( 'Displays the current release access state and actions.', 'music-wave-core' ),
				'icon'        => 'lock',
				'keywords'    => array( __( 'purchase', 'music-wave-core' ), __( 'membership', 'music-wave-core' ), __( 'restricted', 'music-wave-core' ) ),
				'attributes'  => $access_panel_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/release-credits',
				'title'       => __( 'Release credits', 'music-wave-core' ),
				'description' => __( 'Displays release credits.', 'music-wave-core' ),
				'icon'        => 'id',
				'keywords'    => array( __( 'roles', 'music-wave-core' ), __( 'producers', 'music-wave-core' ) ),
				'attributes'  => $credits_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/collection-list',
				'title'       => __( 'Collection track list', 'music-wave-core' ),
				'description' => __( 'Displays child releases in a collection.', 'music-wave-core' ),
				'icon'        => 'playlist-audio',
				'keywords'    => array( __( 'tracks', 'music-wave-core' ), __( 'episodes', 'music-wave-core' ), __( 'discs', 'music-wave-core' ) ),
				'attributes'  => $collection_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/catalog-filters',
				'title'       => __( 'Catalog filters', 'music-wave-core' ),
				'description' => __( 'Displays release catalog filters.', 'music-wave-core' ),
				'icon'        => 'filter',
				'keywords'    => array( __( 'search', 'music-wave-core' ), __( 'archive', 'music-wave-core' ) ),
				'attributes'  => $catalog_filter_attributes,
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/catalog-results',
				'title'       => __( 'Catalog results', 'music-wave-core' ),
				'description' => __( 'Displays the current catalog result summary.', 'music-wave-core' ),
				'icon'        => 'chart-bar',
				'keywords'    => array( __( 'count', 'music-wave-core' ), __( 'active filters', 'music-wave-core' ) ),
				'attributes'  => $catalog_results_attributes,
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/preview-player',
				'title'       => __( 'Release preview player', 'music-wave-core' ),
				'description' => __( 'Displays an audio preview player for a release.', 'music-wave-core' ),
				'icon'        => 'controls-play',
				'keywords'    => array( __( 'audio', 'music-wave-core' ), __( 'listen', 'music-wave-core' ) ),
				'attributes'  => $preview_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/download-button',
				'title'       => __( 'Secure download', 'music-wave-core' ),
				'description' => __( 'Displays authorized download actions for a release.', 'music-wave-core' ),
				'icon'        => 'download',
				'keywords'    => array( __( 'quality', 'music-wave-core' ), __( 'stream', 'music-wave-core' ), __( 'files', 'music-wave-core' ) ),
				'attributes'  => $download_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/related-releases',
				'title'       => __( 'Related releases', 'music-wave-core' ),
				'description' => __( 'Displays related MusicWave releases.', 'music-wave-core' ),
				'icon'        => 'share',
				'keywords'    => array( __( 'similar', 'music-wave-core' ), __( 'same artist', 'music-wave-core' ), __( 'shelf', 'music-wave-core' ) ),
				'attributes'  => $related_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/artist-profile',
				'title'       => __( 'Artist profile', 'music-wave-core' ),
				'description' => __( 'Displays the artist taxonomy profile on archive pages or any other template.', 'music-wave-core' ),
				'icon'        => 'admin-users',
				'keywords'    => array( __( 'biography', 'music-wave-core' ), __( 'artist', 'music-wave-core' ) ),
				'attributes'  => $artist_profile_attributes,
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/account-dashboard',
				'title'       => __( 'User music dashboard', 'music-wave-core' ),
				'description' => __( 'Displays account shortcuts, statistics, and the secure personal music library.', 'music-wave-core' ),
				'icon'        => 'dashboard',
				'keywords'    => array( __( 'library', 'music-wave-core' ), __( 'account', 'music-wave-core' ) ),
				'attributes'  => array(
					'showLibrary'    => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showQuickLinks' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showStats'      => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/music-library',
				'title'       => __( 'Personal music library', 'music-wave-core' ),
				'description' => __( 'Displays the visitor\'s saved songs, albums, podcasts, and followed artists.', 'music-wave-core' ),
				'icon'        => 'albums',
				'keywords'    => array( __( 'favorites', 'music-wave-core' ), __( 'saved', 'music-wave-core' ), __( 'collection', 'music-wave-core' ) ),
				'attributes'  => $music_library_attributes,
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/library-button',
				'title'       => __( 'Add to library button', 'music-wave-core' ),
				'description' => __( 'Lets visitors save a release or follow an artist into their personal library.', 'music-wave-core' ),
				'icon'        => 'plus-alt',
				'keywords'    => array( __( 'save', 'music-wave-core' ), __( 'follow', 'music-wave-core' ), __( 'favorite', 'music-wave-core' ) ),
				'attributes'  => $library_button_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
			array(
				'name'        => 'music-wave/preview-button',
				'title'       => __( 'Preview button', 'music-wave-core' ),
				'description' => __( 'Displays a release audio preview button.', 'music-wave-core' ),
				'icon'        => 'controls-play',
				'keywords'    => array( __( 'play', 'music-wave-core' ), __( 'listen', 'music-wave-core' ) ),
				'attributes'  => $preview_button_attributes,
				'usesContext' => array( 'postId', 'postType' ),
				'supports'    => $supports,
			),
		);

		// Enrich the hand-rolled editor registry with block.json metadata that
		// must stay defined in exactly one place: the metadata file itself.
		foreach ( $editor_blocks as $index => $editor_block ) {
			$metadata = BlockMetadata::load( (string) $editor_block['name'] );
			if ( ! is_array( $metadata ) ) {
				continue;
			}
			if ( isset( $metadata['category'] ) ) {
				$editor_blocks[ $index ]['category'] = (string) $metadata['category'];
			}
			if ( isset( $metadata['example'] ) && is_array( $metadata['example'] ) ) {
				$editor_blocks[ $index ]['example'] = $metadata['example'];
			}
		}

		return $editor_blocks;
	}
}
